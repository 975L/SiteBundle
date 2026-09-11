<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\SiteBundle\Tests\Service;

use c975L\SiteBundle\Entity\CollectionItem;
use c975L\SiteBundle\Service\CollectionItemTranslator;
use c975L\UiBundle\Service\ContentTranslator;
use PHPUnit\Framework\TestCase;

class CollectionItemTranslatorTest extends TestCase
{
    private function createItem(?int $id = 7): CollectionItem
    {
        $item = new CollectionItem();
        $item->setTitle('Nos ateliers');
        $item->setDescription('Un atelier par mois');
        if (null !== $id) {
            new \ReflectionProperty(CollectionItem::class, 'id')->setValue($item, $id);
        }

        return $item;
    }

    // A text with no translation in the language being rendered falls back on the words the item was written in
    public function testAnUntranslatedTextFallsBackOnTheItemsOwnWords(): void
    {
        $contentTranslator = $this->createStub(ContentTranslator::class);
        $contentTranslator->method('translate')->willReturn(['title' => 'Our workshops']);

        $values = new CollectionItemTranslator($contentTranslator)->translate($this->createItem());

        $this->assertSame(['title' => 'Our workshops', 'description' => 'Un atelier par mois'], $values);
    }

    // What a language screen offers: what that language already says, and the bracketed source where it says nothing yet
    public function testALanguageScreenIsOfferedTheTranslationOrTheBracketedSource(): void
    {
        $contentTranslator = $this->createStub(ContentTranslator::class);
        $contentTranslator->method('all')->willReturn(['es' => ['title' => 'Nuestros talleres', 'description' => '']]);

        $values = new CollectionItemTranslator($contentTranslator)->promptValues($this->createItem(), 'es');

        $this->assertSame('Nuestros talleres', $values['title']);
        $this->assertSame('[Un atelier par mois]', $values['description']);
    }

    // Left as it was offered, the bracketed source is a prompt rather than a translation, and is staged as nothing
    public function testAFieldHandedBackHoldingItsBracketsIsStagedAsNothing(): void
    {
        $contentTranslator = $this->createMock(ContentTranslator::class);
        $contentTranslator->expects($this->once())
            ->method('stage')
            ->with(CollectionItemTranslator::OWNER, 7, 'es', ['title' => 'Nuestros talleres', 'description' => null]);

        new CollectionItemTranslator($contentTranslator)->stage($this->createItem(), 'es', [
            'title' => 'Nuestros talleres',
            'description' => '[Un atelier par mois]',
        ]);
    }

    // An item never saved has no id to name in the translation table
    public function testAnItemWithNoIdStagesNothing(): void
    {
        $contentTranslator = $this->createMock(ContentTranslator::class);
        $contentTranslator->expects($this->never())->method('stage');

        new CollectionItemTranslator($contentTranslator)->stage($this->createItem(null), 'es', ['title' => 'x']);
    }

    // A site declaring a single language reads nothing ahead
    public function testASiteInOneLanguagePreloadsNothing(): void
    {
        $contentTranslator = $this->createMock(ContentTranslator::class);
        $contentTranslator->method('isActive')->willReturn(false);
        $contentTranslator->expects($this->never())->method('preload');

        new CollectionItemTranslator($contentTranslator)->preload([$this->createItem()]);
    }

    // A listing reads its saved items ahead in one go, an unsaved one having nothing to read
    public function testAListingPreloadsTheIdsOfItsSavedItems(): void
    {
        $contentTranslator = $this->createMock(ContentTranslator::class);
        $contentTranslator->method('isActive')->willReturn(true);
        $contentTranslator->expects($this->once())->method('preload')->with(CollectionItemTranslator::OWNER, [7, 9]);

        new CollectionItemTranslator($contentTranslator)->preload([$this->createItem(), $this->createItem(null), $this->createItem(9)]);
    }
}
