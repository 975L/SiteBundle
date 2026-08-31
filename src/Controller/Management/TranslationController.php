<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\SiteBundle\Controller\Management;

use c975L\ConfigBundle\Service\ConfigServiceInterface;
use c975L\SiteBundle\Repository\MenuRepository;
use c975L\UiBundle\Entity\Block;
use c975L\UiBundle\Entity\Translation;
use c975L\UiBundle\Registry\BlockRegistry;
use c975L\UiBundle\Service\ContentTranslator;
use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminRoute;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Intl\Locales;
use Symfony\Contracts\Translation\TranslatorInterface;

// The screen that says a menu again in another language.
// A page has its own: the very edit screen it is composed on, opened on another language, each text in the place it is read (see PageCrudController::translationFields). A menu has no such screen - it is a list of labels and nothing else - so it keeps this one, flat on purpose: one field per label, the original underneath as a landmark.
// Its items' labels are read on every page of the site, so a site translated everywhere but in its navbar would still be half in the language it was written in. One screen per menu, not per page: a menu is site-wide, and translated once.
class TranslationController extends AbstractController
{
    public function __construct(
        private readonly MenuRepository $menuRepository,
        private readonly ContentTranslator $contentTranslator,
        private readonly BlockRegistry $blockRegistry,
        private readonly ConfigServiceInterface $configService,
        private readonly TranslatorInterface $translator,
    ) {
    }

    #[AdminRoute(path: '/menu-translate/{id}/{locale}', name: 'menu_translate', options: ['defaults' => ['locale' => null]])]
    public function menu(Request $request, int $id, ?string $locale = null): Response
    {
        $menu = $this->menuRepository->find($id);
        if (null === $menu) {
            throw $this->createNotFoundException();
        }

        $rows = [];
        $this->collectBlockRows($menu->getBlocks(), $rows);

        return $this->screen(
            $request,
            $locale,
            $rows,
            [
                'title' => $this->translator->trans(MenuCrudController::LOCATION_LABELS[$menu->getLocation()] ?? 'label.menu', [], 'site'),
                'heading' => 'label.menu_translation',
                'description' => 'description.menu_translation',
                'route' => 'management_menu_translate',
                'id' => $id,
                'backController' => MenuCrudController::class,
            ],
        );
    }

    /**
     * The screen the action above renders: the language to write in, then a field per text.
     *
     * @param list<array{name: string, owner: string, ownerId: int, field: string, label: string, reference: string, rich: bool}> $rows
     * @param array{title: string, heading: string, description: string, route: string, id: int, backController: class-string}    $subject
     */
    private function screen(Request $request, ?string $locale, array $rows, array $subject): Response
    {
        $this->denyAccessUnlessGranted((string) $this->configService->get('site-role-editor'));

        $locales = $this->contentTranslator->getTranslatableLocales();
        if ([] === $locales) {
            throw $this->createNotFoundException('This site declares a single language.');
        }

        // Without a language in the url, the first one the site declares after its own: the screen opens on something rather than on a choice
        $locale ??= $locales[0];
        if (!\in_array($locale, $locales, true)) {
            throw $this->createNotFoundException();
        }

        if ($request->isMethod('POST')) {
            return $this->save($request, $locale, $rows, $subject);
        }

        return $this->render('@c975LSite/management/translation.html.twig', [
            'subject' => $subject,
            'locale' => $locale,
            'locales' => array_combine($locales, array_map(static fn (string $one) => Locales::getName($one, $one), $locales)),
            'rows' => $rows,
            // What is already written in that language, keyed by row: the template reads each field's value from it
            'written' => $this->written($rows, $locale),
        ]);
    }

    /**
     * What the form sent back, handed to each owner it belongs to: one row per text, the empty ones erased rather than stored empty.
     *
     * @param list<array{name: string, owner: string, ownerId: int, field: string}>                                            $rows
     * @param array{title: string, heading: string, description: string, route: string, id: int, backController: class-string} $subject
     */
    private function save(Request $request, string $locale, array $rows, array $subject): Response
    {
        if (!$this->isCsrfTokenValid($this->csrfTokenId($subject), (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }

        $posted = (array) $request->request->all('values');

        $values = [];
        foreach ($rows as $row) {
            $value = $posted[$row['name']] ?? null;
            $values[$row['owner']][$row['ownerId']][$row['field']] = \is_string($value) ? $value : null;
        }

        foreach ($values as $owner => $owned) {
            foreach ($owned as $ownerId => $fields) {
                $this->contentTranslator->store($owner, $ownerId, $locale, $fields);
            }
        }

        $this->addFlash('success', $this->translator->trans('label.page_translation_saved', [], 'site'));

        return $this->redirectToRoute($subject['route'], ['id' => $subject['id'], 'locale' => $locale]);
    }

    /**
     * @param iterable<Block>   $blocks
     * @param array<int, array> $rows
     */
    private function collectBlockRows(iterable $blocks, array &$rows, array &$seen = []): void
    {
        foreach ($blocks as $block) {
            $id = $block->getId();
            $kind = $block->getKind();

            // The same guard as everywhere else: a container and a slot pointing at one another
            if (null === $id || null === $kind || isset($seen[$id])) {
                continue;
            }
            $seen[$id] = true;

            $data = $block->getData();
            foreach ($this->blockRegistry->getTranslatable($kind) as $field) {
                $reference = $data[$field] ?? null;

                // A field left empty in the writing language has nothing to translate: there is no text behind it
                if (!\is_string($reference) || '' === trim($reference)) {
                    continue;
                }

                $rows[] = [
                    'name' => 'block_' . $id . '_' . $field,
                    'owner' => Translation::OWNER_BLOCK,
                    'ownerId' => $id,
                    'field' => $field,
                    'label' => $this->blockRegistry->getLabel($kind) . ' — ' . $field,
                    'reference' => $reference,
                    // A rich text is written with its tags: the field is taller, and what is pasted in it keeps its formatting
                    'rich' => str_contains($reference, '<'),
                ];
            }

            $this->collectBlockRows($block->getSlots(), $rows, $seen);
        }
    }

    /**
     * What has already been written in that language, one entry per row of the form.
     *
     * @param list<array{name: string, owner: string, ownerId: int, field: string}> $rows
     *
     * @return array<string, string|null> row name => value
     */
    private function written(array $rows, string $locale): array
    {
        $written = [];
        $values = [];

        foreach ($rows as $row) {
            $values[$row['owner']][$row['ownerId']] ??= $this->contentTranslator->all($row['owner'], $row['ownerId'])[$locale] ?? [];
            $written[$row['name']] = $values[$row['owner']][$row['ownerId']][$row['field']] ?? null;
        }

        return $written;
    }

    /**
     * @param array{route: string, id: int} $subject
     */
    private function csrfTokenId(array $subject): string
    {
        return $subject['route'] . '_' . $subject['id'];
    }
}
