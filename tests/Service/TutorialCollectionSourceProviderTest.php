<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\SiteBundle\Tests\Service;

use c975L\SiteBundle\Entity\Page;
use c975L\SiteBundle\Repository\PageRepository;
use c975L\SiteBundle\Service\TutorialCatalog;
use c975L\SiteBundle\Service\TutorialCollectionSourceProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

class TutorialCollectionSourceProviderTest extends TestCase
{
    private function provider(?Page $contact, ?string &$askedLocale = null): TutorialCollectionSourceProvider
    {
        $film = static fn (string $slug): array => ['slug' => $slug, 'label' => ucfirst($slug), 'description' => null, 'poster' => '/medias/films/fr/' . $slug . '.jpg'];

        $catalog = $this->createStub(TutorialCatalog::class);
        $catalog->method('all')->willReturnCallback(static function (string $locale) use ($film, &$askedLocale): array {
            $askedLocale = $locale;

            return [$film('one'), $film('two'), $film('three')];
        });

        $pageRepository = $this->createStub(PageRepository::class);
        $pageRepository->method('findOneByFormBlockName')->willReturn($contact);
        $request = new Request();
        $request->setLocale('en');
        $requestStack = new RequestStack([$request]);

        return new TutorialCollectionSourceProvider($catalog, $pageRepository, $requestStack, 'fr');
    }

    // One source, labelled in the ui domain CollectionType translates its choices in, drawn by its own card
    public function testItDeclaresTheTutorialsSource(): void
    {
        $sources = $this->provider(null)->getSources();

        $this->assertSame([TutorialCollectionSourceProvider::SOURCE], array_keys($sources));
        $this->assertSame(TutorialCollectionSourceProvider::ITEM_TEMPLATE, $sources[TutorialCollectionSourceProvider::SOURCE]['itemTemplate']);
        $this->assertSame('label.tutorials_collection_source', $sources[TutorialCollectionSourceProvider::SOURCE]['label']);
    }

    // The films come in the visitor's language, numbered in parcours order, each knowing its neighbours for the dialog's own navigation
    public function testItemsAreNumberedAndKnowTheirNeighbours(): void
    {
        $items = $this->provider(null, $locale)->getSources()[TutorialCollectionSourceProvider::SOURCE]['items'](null);

        $this->assertSame('en', $locale);
        $this->assertSame(['One', 'Two', 'Three'], array_map(static fn ($item): string => $item->title, $items));
        $this->assertSame(2, $items[1]->data['number']);
        $this->assertSame('one', $items[1]->data['previous']['slug']);
        $this->assertSame('three', $items[1]->data['next']['slug']);
        $this->assertNull($items[0]->data['previous']);
        $this->assertNull($items[2]->data['next']);
        $this->assertSame('/medias/films/fr/one.jpg', $items[0]->imageUrl);
    }

    public function testTheLimitCutsTheList(): void
    {
        $items = $this->provider(null)->getSources()[TutorialCollectionSourceProvider::SOURCE]['items'](2);

        $this->assertCount(2, $items);
        $this->assertNull($items[1]->data['next']);
    }

    // A report is written in the contact form: no page holding it, no report link
    public function testReportsNeedAContactPage(): void
    {
        $source = static fn (TutorialCollectionSourceProvider $provider): array => $provider->getSources()[TutorialCollectionSourceProvider::SOURCE]['items'](null);

        $this->assertFalse($source($this->provider(null))[0]->data['reportable']);
        $this->assertTrue($source($this->provider(new Page()))[0]->data['reportable']);
    }
}
