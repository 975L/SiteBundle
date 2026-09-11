<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\SiteBundle\Service;

use c975L\SiteBundle\Entity\CollectionItem;
use c975L\UiBundle\Service\ContentTranslator;

// What a collection item says in another language: its title and its description, laid over the card a "collection" block prints and over the detail page it opens (see CollectionItemSourceProvider). Its image, its link, its slug and its place are the same in every language. A site declaring a single language never reads any of this
class CollectionItemTranslator
{
    // The vocabulary this bundle's rows are named with, the way PageTranslator names a page
    public const string OWNER = 'site_collection_item';

    // What a translation may cover of an item: what a visitor reads on its card
    public const array FIELDS = ['title', 'description'];

    public function __construct(
        private readonly ContentTranslator $contentTranslator,
    ) {
    }

    public function isActive(): bool
    {
        return $this->contentTranslator->isActive();
    }

    // The languages an item may be written in besides the one it was written in
    /** @return list<string> */
    public function getTranslatableLocales(): array
    {
        return $this->contentTranslator->getTranslatableLocales();
    }

    // Reads ahead a whole collection, so a listing of a dozen cards costs one query rather than a dozen
    /** @param iterable<CollectionItem> $items */
    public function preload(iterable $items): void
    {
        if (!$this->contentTranslator->isActive()) {
            return;
        }

        $ids = [];
        foreach ($items as $item) {
            $id = $item->getId();
            if (null !== $id) {
                $ids[] = $id;
            }
        }

        $this->contentTranslator->preload(self::OWNER, $ids);
    }

    // The item's two texts in the language being rendered, each one falling back on the words it was written in
    /** @return array<string, string|null> field => value */
    public function translate(CollectionItem $item): array
    {
        $values = $this->contentTranslator->translate(self::OWNER, $item->getId(), $this->source($item), self::FIELDS);

        return [
            'title' => \is_string($values['title'] ?? null) ? $values['title'] : $item->getTitle(),
            'description' => \is_string($values['description'] ?? null) ? $values['description'] : $item->getDescription(),
        ];
    }

    // What a language screen offers for each text: what that language already says, or the source text between brackets where it says nothing yet
    /** @return array<string, string|null> field => value */
    public function promptValues(CollectionItem $item, string $locale): array
    {
        $id = $item->getId();
        $written = null === $id ? [] : ($this->contentTranslator->all(self::OWNER, $id)[$locale] ?? []);
        $source = $this->source($item);

        $values = [];
        foreach (self::FIELDS as $field) {
            $translated = $written[$field] ?? null;
            $values[$field] = null !== $translated && '' !== $translated
                ? $translated
                : ContentTranslator::prompt($source[$field]);
        }

        return $values;
    }

    // Hands what a language screen wrote over to be stored on the flush that saves the item, a field left holding the bracketed source counting as nothing written (see ContentTranslator::stage)
    /** @param array<string, string|null> $values field => value */
    public function stage(CollectionItem $item, string $locale, array $values): void
    {
        $id = $item->getId();
        if (null === $id) {
            return;
        }

        $source = $this->source($item);

        $staged = [];
        foreach (self::FIELDS as $field) {
            if (\array_key_exists($field, $values)) {
                $staged[$field] = ContentTranslator::untouched($values[$field], $source[$field]) ? null : $values[$field];
            }
        }

        if ([] !== $staged) {
            $this->contentTranslator->stage(self::OWNER, $id, $locale, $staged);
        }
    }

    /** @return array{title: string|null, description: string|null} */
    private function source(CollectionItem $item): array
    {
        return ['title' => $item->getTitle(), 'description' => $item->getDescription()];
    }
}
