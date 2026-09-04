<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\SiteBundle\Service;

use c975L\ConfigBundle\Service\SiteLocales;
use c975L\SiteBundle\Entity\Page;
use c975L\UiBundle\Service\ContentTranslator;

// What a page says in another language: its own two texts, the rest being its blocks' business (see ContentTranslator).
// A page is one page in every language - one structure, one set of blocks, one row - and its translations live beside it rather than in a second row. A site declaring a single language never reads any of this.
class PageTranslator
{
    // The vocabulary this bundle's rows are named with, the way Favorite and Rating name theirs
    public const string OWNER = 'site_page';

    // What a translation may cover of the page itself: what a visitor reads, and what a search engine or a social network quotes
    public const array FIELDS = ['title', 'summarySocialNetwork'];

    public function __construct(
        private readonly ContentTranslator $contentTranslator,
        private readonly SiteLocales $siteLocales,
    ) {
    }

    public function isActive(): bool
    {
        return $this->contentTranslator->isActive();
    }

    public function getTitle(Page $page): string
    {
        return (string) $this->translate($page)['title'];
    }

    public function getSummarySocialNetwork(Page $page): ?string
    {
        $summary = $this->translate($page)['summarySocialNetwork'];

        return null === $summary ? null : (string) $summary;
    }

    /**
     * Every language this page has been given, for the screen that writes them.
     *
     * @return array<string, array<string, string|null>> locale => field => value
     */
    public function all(Page $page): array
    {
        return null === $page->getId() ? [] : $this->contentTranslator->all(self::OWNER, $page->getId());
    }

    /**
     * The languages a page may be written in besides the one it was written in.
     *
     * @return list<string>
     */
    public function getTranslatableLocales(): array
    {
        return $this->contentTranslator->getTranslatableLocales();
    }

    /**
     * What a language screen offers for each of the page's own texts: what that language already says, or the source
     * text between brackets where it says nothing yet.
     *
     * @return array<string, string|null> field => value
     */
    public function promptValues(Page $page, string $locale): array
    {
        $written = $this->all($page)[$locale] ?? [];
        $source = ['title' => $page->getTitle(), 'summarySocialNetwork' => $page->getSummarySocialNetwork()];

        $values = [];
        foreach (self::FIELDS as $field) {
            $translated = $written[$field] ?? null;
            $values[$field] = null !== $translated && '' !== $translated
                ? $translated
                : ContentTranslator::prompt($source[$field] ?? null);
        }

        return $values;
    }

    /**
     * Hands what a language screen wrote over to be stored on the flush that saves the page, a field left holding the
     * bracketed source counting as nothing written (see ContentTranslator::stage).
     *
     * @param array<string, string|null> $values field => value
     */
    public function stage(Page $page, string $locale, array $values): void
    {
        $id = $page->getId();
        if (null === $id) {
            return;
        }

        $source = ['title' => $page->getTitle(), 'summarySocialNetwork' => $page->getSummarySocialNetwork()];

        $staged = [];
        foreach (self::FIELDS as $field) {
            if (!array_key_exists($field, $values)) {
                continue;
            }

            $staged[$field] = ContentTranslator::untouched($values[$field], $source[$field] ?? null) ? null : $values[$field];
        }

        if ([] !== $staged) {
            $this->contentTranslator->stage(self::OWNER, $id, $locale, $staged);
        }
    }

    /**
     * Reads ahead the translations of a whole set of pages, so asking translatedLocales() of each of them costs one
     * query per language rather than one per page - what the sitemap does over every page of the site.
     *
     * @param iterable<Page> $pages
     */
    public function preload(iterable $pages): void
    {
        $ids = [];
        foreach ($pages as $page) {
            $id = $page->getId();
            if (null !== $id) {
                $ids[] = $id;
            }
        }

        foreach ($this->contentTranslator->getTranslatableLocales() as $locale) {
            $this->contentTranslator->preload(self::OWNER, $ids, $locale);
        }
    }

    /**
     * The languages this page really exists in, its own included: the ones a "hreflang" group may name and the ones a
     * localised url may answer on.
     *
     * A language counts as written when the page's title has been translated into it, and not merely when the site
     * declares it. Declaring the whole list instead is what had "/en/" serve a page written in French under
     * lang="en" - an alternate a search engine reads as duplicated content rather than as a translation. The title
     * and not the blocks: a page whose blocks were translated while its title stayed French still renders a French
     * <title>, which is the first thing a result page shows.
     *
     * Read through ContentTranslator::values(), whose preload() caches a whole set of pages in one query - the sitemap
     * asks this of every page of the site (see SitePageSitemapProvider).
     *
     * @return list<string>
     */
    public function translatedLocales(Page $page): array
    {
        $id = $page->getId();
        $locales = [$this->siteLocales->getDefaultLocale()];
        if (null === $id) {
            return $locales;
        }

        foreach ($this->contentTranslator->getTranslatableLocales() as $locale) {
            $title = $this->contentTranslator->values(self::OWNER, $id, $locale)['title'] ?? null;
            if (null !== $title && '' !== $title) {
                $locales[] = $locale;
            }
        }

        return $locales;
    }

    /**
     * @return array<string, mixed>
     */
    private function translate(Page $page): array
    {
        return $this->contentTranslator->translate(
            self::OWNER,
            $page->getId(),
            ['title' => $page->getTitle(), 'summarySocialNetwork' => $page->getSummarySocialNetwork()],
            self::FIELDS,
        );
    }
}
