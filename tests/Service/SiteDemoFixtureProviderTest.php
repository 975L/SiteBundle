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
use c975L\SiteBundle\Entity\Menu;
use c975L\SiteBundle\Entity\Page;
use c975L\SiteBundle\Repository\MenuRepository;
use c975L\SiteBundle\Repository\PageRepository;
use c975L\SiteBundle\Service\SiteDemoFixtureProvider;
use c975L\UiBundle\Entity\Block;
use c975L\UiBundle\Entity\Media;
use c975L\UiBundle\Entity\Translation;
use c975L\UiBundle\Registry\PlaceholderMediaRegistry;
use c975L\UiBundle\Service\DemoFixtureTranslator;
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
    private function createProvider(array $images = [self::IMAGE], ?Menu $existingNavbar = null): SiteDemoFixtureProvider
    {
        $translator = $this->createStub(TranslatorInterface::class);
        $translator->method('trans')->willReturnArgument(0);

        $registry = $this->createStub(PlaceholderMediaRegistry::class);
        $registry->method('getImages')->willReturn($images);

        return new SiteDemoFixtureProvider(new DemoFixtureTranslator($translator, ['fr'], 'fr'), $translator, $registry, $this->pageRepository(), $this->menuRepository($existingNavbar), $this->projectDir);
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

        $this->assertCount(4, $pages);

        // The home page opens on a hero, its alert and five sections, "nos-services" and "notre-histoire" carry five sections apiece - the collection among them - and the binned page its two
        $expected = ['home' => 7, 'nos-services' => 5, 'notre-histoire' => 5, 'ancienne-offre' => 2];

        foreach ($pages as $page) {
            $this->assertCount($expected[$page->getSlug()], $page->getBlocks(), (string) $page->getSlug());

            if ('ancienne-offre' === $page->getSlug()) {
                continue;
            }

            $this->assertTrue($page->isPublished(), (string) $page->getSlug());
        }
    }

    // A bin that has never been used shows an empty screen where putting a page back and removing it for good both have nothing to act on - and unpublished with it, the way trashing a page unpublishes it
    public function testOnePageIsAlreadyInTheBin(): void
    {
        $binned = array_filter(
            $this->fixtures($this->createProvider()),
            static fn (object $e): bool => $e instanceof Page && $e->isDeleted(),
        );

        $this->assertCount(1, $binned);
        $page = reset($binned);
        $this->assertSame('ancienne-offre', $page->getSlug());
        $this->assertFalse($page->isPublished());
    }

    // A home page laid out as a real one is, each section a kind of its own and every text filled, its title left to the hero's h1
    public function testTheHomePageIsLaidOutInSections(): void
    {
        $pages = array_filter($this->fixtures($this->createProvider()), static fn (object $e): bool => $e instanceof Page && 'home' === $e->getSlug());
        $home = reset($pages);

        $this->assertInstanceOf(Page::class, $home);
        $this->assertFalse($home->isTitleDisplayed());

        $blocks = $home->getBlocks()->toArray();
        $this->assertSame(['hero', 'alert', 'feature_bar', 'section_features', 'process_steps', 'faq', 'cta_band'], array_map(static fn (Block $block): string => (string) $block->getKind(), $blocks));
        $this->assertSame([0, 1, 2, 3, 4, 5, 6], array_map(static fn (Block $block): int => (int) $block->getPosition(), $blocks));

        $this->assertSame('<div>label.site_sample_home_alert</div>', $blocks[1]->getData()['content']);

        $data = $blocks[5]->getData();
        $this->assertCount(4, $data['items']);
        $this->assertSame('label.site_sample_home_faq_install_question', $data['items'][0]['question']);
        $this->assertSame('<div>label.site_sample_home_faq_install_answer</div>', $data['items'][0]['answer']);
        $this->assertSame('bundles/c975lui/icons/pen-ruler.svg', $blocks[3]->getData()['cards'][0]['icon']);
        $this->assertSame('label.site_sample_home_features_edit_title', $blocks[3]->getData()['cards'][0]['title']);
    }

    // Each page reads in its own order, the collection the home page's last button points at kept under its anchor, and both close on a band leading to the other
    public function testTheServicesAndHistoryPagesAreLaidOut(): void
    {
        $pages = [];
        foreach ($this->fixtures($this->createProvider()) as $entity) {
            if ($entity instanceof Page) {
                $pages[$entity->getSlug()] = $entity->getBlocks()->toArray();
            }
        }

        $kinds = static fn (array $blocks): array => array_map(static fn (Block $block): string => (string) $block->getKind(), $blocks);
        $positions = static fn (array $blocks): array => array_map(static fn (Block $block): int => (int) $block->getPosition(), $blocks);

        $this->assertSame(['text_section', 'section_features', 'process_steps', 'collection', 'cta_band'], $kinds($pages['nos-services']));
        $this->assertSame([0, 1, 2, 3, 4], $positions($pages['nos-services']));
        $this->assertSame('realisations', $pages['nos-services'][3]->getData()['anchor']);

        $this->assertSame(['text_section', 'feature_bar', 'process_steps', 'text_section', 'cta_band'], $kinds($pages['notre-histoire']));
        $this->assertSame([0, 1, 2, 3, 4], $positions($pages['notre-histoire']));
    }

    // The buttons are pointed at their pages once the first flush gave them an identifier, a raw path losing the "/demo" prefix
    public function testTheSecondPassPointsTheButtonsAtTheirPages(): void
    {
        $pages = [];
        foreach ($this->fixtures($this->createProvider()) as $entity) {
            if ($entity instanceof Page) {
                $pages[$entity->getSlug()] = $entity;
            }
        }

        $home = $pages['home'];
        $services = $pages['nos-services'];
        new \ReflectionProperty(Page::class, 'id')->setValue($services, 12);
        $history = $pages['notre-histoire'];
        new \ReflectionProperty(Page::class, 'id')->setValue($history, 13);

        $repository = $this->createStub(PageRepository::class);
        $repository->method('findOneBy')->willReturnCallback(static fn (array $criteria): ?Page => match ($criteria['slug'] ?? null) {
            'home' => $home,
            'nos-services' => $services,
            'notre-histoire' => $history,
            default => null,
        });

        $translator = $this->createStub(TranslatorInterface::class);
        $translator->method('trans')->willReturnArgument(0);
        $provider = new SiteDemoFixtureProvider(new DemoFixtureTranslator($translator, ['fr'], 'fr'), $translator, $this->createStub(PlaceholderMediaRegistry::class), $repository, $this->menuRepository(new Menu()), $this->projectDir);

        iterator_to_array($provider->getLinkedDemoFixtures(), false);

        $blocks = $home->getBlocks()->toArray();
        $this->assertSame('page:12', $blocks[0]->getData()['primaryUrl']);
        $this->assertSame('page:13', $blocks[0]->getData()['secondaryUrl']);
        $this->assertSame('page:12#realisations', $blocks[6]->getData()['ctaUrl']);
        $this->assertSame('label.site_sample_home_hero_primary', $blocks[0]->getData()['primaryLabel']);
        $this->assertSame('page:13', $services->getBlocks()->last()->getData()['ctaUrl']);
        $this->assertSame('page:12', $history->getBlocks()->last()->getData()['ctaUrl']);
    }

    // A demo site is public: its pages stand in for 975L's own and have no business in a search engine, where the real ones do
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

    // Each card leads to the real site it shows, an outside address the "/demo" prefix does not touch
    public function testTheItemsLeadToTheirRealSites(): void
    {
        $urls = [];
        foreach ($this->fixtures($this->createProvider()) as $entity) {
            if ($entity instanceof CollectionItem) {
                $urls[$entity->getSlug()] = $entity->getUrl();
            }
        }

        $this->assertSame(['resistance-haute-savoie' => 'https://resistance-haute-savoie.fr', 'papa-calin' => 'https://papa-calin.com', 'run-as' => 'https://run.as'], $urls);
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

    // The navbar's links name their pages by identifier, which only the first flush hands out - so the first pass holds no menu
    public function testTheFirstPassYieldsNoMenu(): void
    {
        foreach ($this->fixtures($this->createProvider()) as $entity) {
            $this->assertNotInstanceOf(Menu::class, $entity);
        }
    }

    // The second pass lays a navbar over the pages the first one wrote, each link with a label of its own for the menu translation screen to offer
    public function testTheSecondPassLaysANavbarLinkingThePages(): void
    {
        $menus = array_values(array_filter(iterator_to_array($this->createProvider()->getLinkedDemoFixtures(), false), static fn (object $row): bool => $row instanceof Menu));

        $this->assertCount(1, $menus);
        $this->assertSame(Menu::LOCATION_NAVBAR, $menus[0]->getLocation());

        $links = $menus[0]->getBlocks()->toArray();
        $this->assertCount(3, $links);
        $this->assertSame(['page:7', 'page:7', 'page:7'], array_map(static fn (Block $block): string => $block->getData()['target'], $links));
        $this->assertSame('label.site_sample_menu_home', $links[0]->getData()['label']);
        $this->assertSame([0, 1, 2], array_map(static fn (Block $block): int => (int) $block->getPosition(), $links));
    }

    // A database already holding a navbar keeps its own: refusing the demo's there would come after its pages are written
    public function testAnExistingNavbarIsLeftInPlace(): void
    {
        $rows = iterator_to_array($this->createProvider(existingNavbar: new Menu())->getLinkedDemoFixtures(), false);

        $this->assertSame([], array_filter($rows, static fn (object $row): bool => $row instanceof Menu));
    }

    // The navbar the database already holds, if any
    private function menuRepository(?Menu $navbar = null): MenuRepository
    {
        $repository = $this->createStub(MenuRepository::class);
        $repository->method('findOneBy')->willReturn($navbar);

        return $repository;
    }

    // Every page the navbar links to, answered as the one written by the first pass
    private function pageRepository(): PageRepository
    {
        $page = new Page();
        new \ReflectionProperty(Page::class, 'id')->setValue($page, 7);

        $repository = $this->createStub(PageRepository::class);
        $repository->method('findOneBy')->willReturn($page);

        return $repository;
    }

    // The second pass says the whole demo in the other languages the site declares, and says a rich text exactly as the block stores it - the same words outside their box would read as another text to whatever compares the two
    public function testTheSecondPassWritesEveryLanguageAndKeepsTheRichTextInItsBox(): void
    {
        $translator = $this->createStub(TranslatorInterface::class);
        $translator->method('trans')->willReturnCallback(
            static fn (string $id, array $parameters = [], ?string $domain = null, ?string $locale = null): string => sprintf('%s[%s]', $id, $locale ?? 'fr')
        );

        $registry = $this->createStub(PlaceholderMediaRegistry::class);
        $registry->method('getImages')->willReturn([]);

        $demoFixtureTranslator = new DemoFixtureTranslator($translator, ['fr', 'en'], 'fr');
        $provider = new SiteDemoFixtureProvider($demoFixtureTranslator, $translator, $registry, $this->pageRepository(), $this->menuRepository(), $this->projectDir);

        $flushed = [];
        foreach ($provider->getDemoFixtures() as $index => $entity) {
            $this->giveAnIdentifier($entity, $index + 1);
            $flushed[] = $entity;
        }

        $rows = iterator_to_array($provider->getLinkedDemoFixtures(), false);

        $this->assertNotSame([], $rows, 'The demo site was not staged for translation at all.');

        $rows = array_values(array_filter($rows, static fn (object $row): bool => $row instanceof Translation));

        foreach ($rows as $row) {
            $this->assertSame('en', $row->getLocale());
        }

        $wrapped = array_values(array_filter($rows, static fn (Translation $row): bool => 'content' === $row->getField()));
        $this->assertNotSame([], $wrapped, 'No rich text was staged.');
        $this->assertStringStartsWith('<div>', (string) $wrapped[0]->getValue());
        $this->assertStringEndsWith('</div>', (string) $wrapped[0]->getValue());
    }

    // The blocks ride the ORM cascade off their page, so nothing yields them and only the page is walked here - each of them is given an identifier the way a flush would
    private function giveAnIdentifier(object $entity, int $id): void
    {
        if ($entity instanceof Page) {
            new \ReflectionProperty(Page::class, 'id')->setValue($entity, $id);

            foreach ($entity->getBlocks() as $position => $block) {
                new \ReflectionProperty(Block::class, 'id')->setValue($block, $id * 100 + $position);

                foreach ($block->getMedias() as $mediaPosition => $media) {
                    new \ReflectionProperty(Media::class, 'id')->setValue($media, $id * 1000 + $mediaPosition);
                }
            }
        }
    }
}
