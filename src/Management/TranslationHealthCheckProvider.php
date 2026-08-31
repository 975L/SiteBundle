<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\SiteBundle\Management;

use c975L\ConfigBundle\Entity\HealthCheckResult;
use c975L\ConfigBundle\Management\HealthCheckProviderInterface;
use c975L\ConfigBundle\Service\SiteUrlResolver;
use c975L\SiteBundle\Controller\Management\MenuCrudController;
use c975L\SiteBundle\Entity\Menu;
use c975L\SiteBundle\Entity\Page;
use c975L\SiteBundle\Repository\MenuRepository;
use c975L\SiteBundle\Repository\PageRepository;
use c975L\SiteBundle\Service\PageEditUrlResolver;
use c975L\SiteBundle\Service\PagePublicUrlResolver;
use c975L\SiteBundle\Service\PageTranslator;
use c975L\UiBundle\Entity\Block;
use c975L\UiBundle\Entity\Translation;
use c975L\UiBundle\Registry\BlockRegistry;
use c975L\UiBundle\Service\ContentTranslator;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGeneratorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

// What is published in one language and not in the others.
// A half-translated page is not broken - a text nobody translated keeps the one it was written in (see ContentTranslator) - so it says nothing on its own, and a site could publish "hreflang" groups pointing at pages still mostly in the first language. This is what says so.
// Menus get a row of their own, one per menu rather than one per page: their items' labels are read on every page of the site, so a navbar left in the writing language shows up on all of them at once.
// Nothing to report on a site declaring a single language: it returns an empty list, and the Health check dashboard shows no such rows at all.
class TranslationHealthCheckProvider implements HealthCheckProviderInterface
{
    // Named here rather than restated wherever a row of this kind is picked out
    public const string KIND = 'translations';

    public function __construct(
        private readonly PageRepository $pageRepository,
        private readonly MenuRepository $menuRepository,
        private readonly ContentTranslator $contentTranslator,
        private readonly PageTranslator $pageTranslator,
        private readonly BlockRegistry $blockRegistry,
        private readonly PagePublicUrlResolver $pagePublicUrlResolver,
        private readonly PageEditUrlResolver $pageEditUrlResolver,
        private readonly SiteUrlResolver $siteUrlResolver,
        private readonly AdminUrlGeneratorInterface $adminUrlGenerator,
        private readonly TranslatorInterface $translator,
    ) {
    }

    public function getKind(): string
    {
        return self::KIND;
    }

    public function runChecks(): array
    {
        $locales = $this->contentTranslator->getTranslatableLocales();
        if ([] === $locales) {
            return [];
        }

        $results = [];
        foreach ($this->pageRepository->findAllOrdered() as $page) {
            $url = $this->pagePublicUrlResolver->resolve($page);
            if (null === $url || !$page->isPublished()) {
                continue;
            }

            $results[] = $this->checkPage($page, $url, $locales);
        }

        $siteRoot = $this->siteUrlResolver->siteRoot();
        if (null !== $siteRoot) {
            foreach ($this->menuRepository->findAll() as $menu) {
                $results[] = $this->checkMenu($menu, $siteRoot, $locales);
            }
        }

        return $results;
    }

    /**
     * @param list<string> $locales
     *
     * @return array{url: string, label: string, status: string, summary: string, details: array<string, mixed>, editUrl: ?string}
     */
    private function checkPage(Page $page, string $url, array $locales): array
    {
        $blockKeys = [];
        $this->collectBlockKeys($page->getBlocks(), $blockKeys);

        $own = [];
        foreach (PageTranslator::FIELDS as $field) {
            $value = 'title' === $field ? $page->getTitle() : $page->getSummarySocialNetwork();
            if (\is_string($value) && '' !== trim($value)) {
                $own[] = 'page.' . $field;
            }
        }

        return $this->row(
            $url,
            (string) $page->getTitle(),
            $this->pageEditUrlResolver->resolve($page),
            $own,
            $blockKeys,
            $this->pageTranslator->all($page),
            $locales,
        );
    }

    /**
     * A menu holds no text of its own: only its items' labels, which are blocks like any other.
     *
     * @param list<string> $locales
     *
     * @return array{url: string, label: string, status: string, summary: string, details: array<string, mixed>, editUrl: ?string}
     */
    private function checkMenu(Menu $menu, string $siteRoot, array $locales): array
    {
        $blockKeys = [];
        $this->collectBlockKeys($menu->getBlocks(), $blockKeys);

        // The site root with the menu's own location as a fragment: a menu is read on every page and has no url of its own, and the rows of one kind are told apart by their url (see HealthCheckRunner)
        return $this->row(
            $siteRoot . '#' . (string) $menu->getLocation(),
            $this->translator->trans(MenuCrudController::LOCATION_LABELS[$menu->getLocation()] ?? 'label.menu', [], 'site'),
            $this->adminUrlGenerator->unsetAll()
                ->setController(MenuCrudController::class)
                ->setAction(Action::EDIT)
                ->setEntityId($menu->getId())
                ->generateUrl(),
            [],
            $blockKeys,
            [],
            $locales,
        );
    }

    /**
     * One row of the dashboard, whatever it is that carries the texts.
     *
     * @param list<string>                              $ownKeys         the subject's own texts, in expected()'s vocabulary
     * @param list<string>                              $blockKeys       its blocks' texts, walked once and read by every language
     * @param array<string, array<string, string|null>> $ownTranslations locale => field => value, empty for a subject holding no text of its own
     * @param list<string>                              $locales
     *
     * @return array{url: string, label: string, status: string, summary: string, details: array<string, mixed>, editUrl: ?string}
     */
    private function row(string $url, string $label, ?string $editUrl, array $ownKeys, array $blockKeys, array $ownTranslations, array $locales): array
    {
        $expected = array_merge($ownKeys, $blockKeys);

        // Read once for the whole subject rather than once per language: ContentTranslator::all() hands back every locale a block was given, so each language below reads the same rows instead of asking for them again
        $blockTranslations = [];
        foreach ($blockKeys as $key) {
            [, $id] = explode('.', $key, 3) + [null, null, null];
            $blockTranslations[(int) $id] ??= $this->contentTranslator->all(Translation::OWNER_BLOCK, (int) $id);
        }

        $missing = [];
        foreach ($locales as $locale) {
            $written = $this->written($blockKeys, $blockTranslations, $ownTranslations, $locale);
            $left = array_values(array_diff($expected, $written));

            if ([] !== $left) {
                $missing[$locale] = \count($left);
            }
        }

        return [
            'url' => $url,
            'label' => $label,
            'status' => $this->status($missing, $locales),
            'summary' => [] === $missing
                ? $this->translator->trans('label.page_translation_complete', [], 'site')
                : $this->translator->trans('label.page_translation_missing', ['%missing%' => implode(', ', array_map(
                    static fn (string $locale, int $count) => sprintf('%s: %d', $locale, $count),
                    array_keys($missing),
                    $missing,
                ))], 'site'),
            'details' => ['texts' => \count($expected), 'missing' => $missing],
            'editUrl' => $editUrl,
        ];
    }

    /**
     * Nothing to translate at all counts as translated: a page carrying only images has no business staying red.
     *
     * @param array<string, int> $missing
     * @param list<string>       $locales
     */
    private function status(array $missing, array $locales): string
    {
        if ([] === $missing) {
            return HealthCheckResult::STATUS_OK;
        }

        // A language started then left halfway is a warning; none of the declared languages started at all is an error
        return \count($missing) === \count($locales)
            ? HealthCheckResult::STATUS_ERROR
            : HealthCheckResult::STATUS_WARNING;
    }

    /**
     * @param iterable<Block>  $blocks
     * @param list<string>     $keys
     * @param array<int, bool> $seen
     */
    private function collectBlockKeys(iterable $blocks, array &$keys, array &$seen = []): void
    {
        foreach ($blocks as $block) {
            $id = $block->getId();
            $kind = $block->getKind();

            if (null === $id || null === $kind || isset($seen[$id])) {
                continue;
            }
            $seen[$id] = true;

            $data = $block->getData();
            foreach ($this->blockRegistry->getTranslatable($kind) as $field) {
                $value = $data[$field] ?? null;
                if (\is_string($value) && '' !== trim($value)) {
                    $keys[] = 'block.' . $id . '.' . $field;
                }
            }

            $this->collectBlockKeys($block->getSlots(), $keys, $seen);
        }
    }

    /**
     * The keys actually written in that language, in the same vocabulary the expected ones use.
     *
     * @param list<string>                                          $blockKeys
     * @param array<int, array<string, array<string, string|null>>> $blockTranslations block id => locale => field => value
     * @param array<string, array<string, string|null>>             $ownTranslations   locale => field => value
     *
     * @return list<string>
     */
    private function written(array $blockKeys, array $blockTranslations, array $ownTranslations, string $locale): array
    {
        $keys = [];

        foreach ($ownTranslations[$locale] ?? [] as $field => $value) {
            if (\is_string($value) && '' !== trim($value)) {
                $keys[] = 'page.' . $field;
            }
        }

        foreach ($blockKeys as $key) {
            [, $id, $field] = explode('.', $key, 3) + [null, null, null];
            $written = $blockTranslations[(int) $id][$locale][$field] ?? null;

            if (\is_string($written) && '' !== trim($written)) {
                $keys[] = $key;
            }
        }

        return $keys;
    }
}
