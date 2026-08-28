<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\SiteBundle\Tests\Service;

use c975L\SiteBundle\Entity\CollectionGroup;
use c975L\SiteBundle\Entity\CollectionItem;
use c975L\SiteBundle\Entity\Page;
use c975L\SiteBundle\Service\SiteDemoFixtureProvider;
use c975L\UiBundle\Entity\Block;
use c975L\UiBundle\Registry\PlaceholderMediaRegistry;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Contracts\Translation\TranslatorInterface;

class SiteDemoFixtureProviderTest extends TestCase
{
    private const string IMAGE = 'showcase/photo.webp';

    private string $projectDir;

    /** @var list<string> */
    private array $temporaryCopies = [];

    protected function setUp(): void
    {
        $this->projectDir = sys_get_temp_dir() . '/site-demo-test-' . uniqid();
        new Filesystem()->mkdir($this->projectDir . '/public/showcase');
        file_put_contents($this->projectDir . '/public/' . self::IMAGE, 'image');
    }

    // The copies handed to VichUploader live in the system's temp directory, where a real load has them moved away - nothing moves them here, so the test takes them back itself
    protected function tearDown(): void
    {
        new Filesystem()->remove([$this->projectDir, ...$this->temporaryCopies]);
    }

    /** @param list<string> $images */
    private function createProvider(array $images = [self::IMAGE]): SiteDemoFixtureProvider
    {
        $translator = $this->createStub(TranslatorInterface::class);
        $translator->method('trans')->willReturnArgument(0);

        $registry = $this->createStub(PlaceholderMediaRegistry::class);
        $registry->method('getImages')->willReturn($images);

        return new SiteDemoFixtureProvider($translator, $registry, $this->projectDir);
    }

    /** @return list<object> */
    private function fixtures(SiteDemoFixtureProvider $provider): array
    {
        $fixtures = iterator_to_array($provider->getDemoFixtures(), false);

        foreach ($fixtures as $entity) {
            if ($entity instanceof CollectionItem) {
                $this->temporaryCopies[] = (string) $entity->getFile()?->getPathname();
            }

            // The hero's picture is a Media hanging off a block, copied aside the same way
            if ($entity instanceof Page) {
                foreach ($entity->getBlocks() as $block) {
                    foreach ($block->getMedia() as $media) {
                        $this->temporaryCopies[] = (string) $media->getFile()?->getPathname();
                    }
                }
            }
        }

        return $fixtures;
    }

    // SiteBundle serves "/" from the page slugged "home": without it a demo answers 404 at its own front door
    public function testADemoHasAHomePage(): void
    {
        $slugs = array_map(
            static fn (object $e): ?string => $e instanceof Page ? $e->getSlug() : null,
            $this->fixtures($this->createProvider()),
        );

        $this->assertContains('home', $slugs);
    }

    // The page a visitor lands on carries a picture, which no other one does
    public function testTheHomePageOpensOnAHeroWithItsPicture(): void
    {
        foreach ($this->fixtures($this->createProvider()) as $entity) {
            if ($entity instanceof Page && 'home' === $entity->getSlug()) {
                $hero = $entity->getBlocks()->first();

                $this->assertSame('hero', $hero->getKind());
                $this->assertCount(1, $hero->getMedia());

                return;
            }
        }

        $this->fail('no home page');
    }

    public function testThePagesArePublishedAndCarryTheirBlocks(): void
    {
        $pages = array_filter($this->fixtures($this->createProvider()), static fn (object $e): bool => $e instanceof Page);

        $this->assertCount(3, $pages);

        // The home page opens on a hero and carries two alerts under it, the others two sections apiece - "nos-services" with its collection block on top of them
        $expected = ['home' => 3, 'nos-services' => 3, 'notre-histoire' => 2];

        foreach ($pages as $page) {
            $this->assertTrue($page->isPublished(), (string) $page->getSlug());
            $this->assertCount($expected[$page->getSlug()], $page->getBlocks(), (string) $page->getSlug());
        }
    }

    // What a demo says about itself is a block like any other, so a visitor can open it in the editor and change it
    public function testTheHomePageSaysWhatADemoIsInTwoAlerts(): void
    {
        $pages = array_filter($this->fixtures($this->createProvider()), static fn (object $e): bool => $e instanceof Page && 'home' === $e->getSlug());
        $home = reset($pages);

        $this->assertInstanceOf(Page::class, $home);

        $alerts = array_values(array_filter($home->getBlocks()->toArray(), static fn (Block $block): bool => 'alert' === $block->getKind()));

        $this->assertCount(2, $alerts);
        $this->assertSame('info', $alerts[0]->getData()['type']);
        $this->assertSame('warning', $alerts[1]->getData()['type']);

        foreach ($alerts as $alert) {
            $this->assertNotSame('', trim(strip_tags((string) $alert->getData()['content'])));
        }
    }

    // A demo site is public: its made-up pages have no business in a search engine, where the site's own do
    public function testThePagesAreNotIndexable(): void
    {
        foreach ($this->fixtures($this->createProvider()) as $entity) {
            if ($entity instanceof Page) {
                $this->assertFalse($entity->isIndexable(), (string) $entity->getSlug());
            }
        }
    }

    // Written down rather than computed: a demo reloaded between two takes of the same recorded sequence reads the same dates back
    public function testEveryPageCarriesAFrozenCreationDate(): void
    {
        $dates = [];

        foreach ($this->fixtures($this->createProvider()) as $entity) {
            if ($entity instanceof Page) {
                $this->assertNotNull($entity->getCreation(), (string) $entity->getSlug());
                $dates[] = $entity->getCreation()->format('Y-m-d');
            }
        }

        $this->assertSame($dates, array_unique($dates));
    }

    // Nothing cascades off a CollectionGroup, so each item is yielded on its own - what is not yielded is never recorded, and so never taken back
    public function testTheGroupComesBeforeItsItems(): void
    {
        $fixtures = $this->fixtures($this->createProvider());

        $group = null;
        foreach ($fixtures as $entity) {
            if ($entity instanceof CollectionGroup) {
                $group = $entity;

                continue;
            }

            if ($entity instanceof CollectionItem) {
                $this->assertNotNull($group, 'an item is yielded before its group');
                $this->assertSame($group, $entity->getCollectionGroup());
            }
        }

        $this->assertNotNull($group);
    }

    public function testTheItemsCarryAPictureCopiedAside(): void
    {
        foreach ($this->fixtures($this->createProvider()) as $entity) {
            if ($entity instanceof CollectionItem) {
                $this->assertNotNull($entity->getFile(), (string) $entity->getSlug());
                $this->assertNotSame($this->projectDir . '/public/' . self::IMAGE, $entity->getFile()?->getPathname());
            }
        }
    }

    // "#" would render as a button labelled with it, and the portfolio variant would take it for a real link
    public function testTheItemsCarryNoUrl(): void
    {
        foreach ($this->fixtures($this->createProvider()) as $entity) {
            if ($entity instanceof CollectionItem) {
                $this->assertNull($entity->getUrl(), (string) $entity->getSlug());
            }
        }
    }

    // A collection nothing renders is back-office material only: it is browsed through a block naming it as its source
    public function testTheCollectionIsReadByABlockOnAPage(): void
    {
        $fixtures = $this->fixtures($this->createProvider());

        $group = null;
        $sources = [];

        foreach ($fixtures as $entity) {
            if ($entity instanceof CollectionGroup) {
                $group = $entity;
            }

            if ($entity instanceof Page) {
                foreach ($entity->getBlocks() as $block) {
                    if ('collection' === $block->getKind()) {
                        $sources[] = $block->getData()['source'];
                    }
                }
            }
        }

        $this->assertNotNull($group);
        $this->assertSame(['site.collection.' . $group->getSlug()], $sources);
    }

    // A site declaring no placeholder still gets its collection: a card without its picture beats no collection at all
    public function testWithoutAPlaceholderTheItemsAreStillYielded(): void
    {
        $items = array_filter($this->fixtures($this->createProvider([])), static fn (object $e): bool => $e instanceof CollectionItem);

        $this->assertCount(3, $items);

        foreach ($items as $item) {
            $this->assertNull($item->getFile());
        }
    }

    // A demo site holds one navbar and one footer of its own: a dataset adding a menu would fight the site's navigation
    public function testNoMenuIsYielded(): void
    {
        foreach ($this->fixtures($this->createProvider()) as $entity) {
            $this->assertNotInstanceOf(\c975L\SiteBundle\Entity\Menu::class, $entity);
        }
    }
}
