<?php
/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\SiteBundle\Tests\Twig;

use c975L\ConfigBundle\Management\LinkableRouteRegistry;
use c975L\ConfigBundle\Service\ConfigServiceInterface;
use c975L\SiteBundle\Entity\Menu;
use c975L\SiteBundle\Entity\Page;
use c975L\SiteBundle\Repository\MenuRepository;
use c975L\SiteBundle\Repository\PageRepository;
use c975L\SiteBundle\Service\DefaultPagesImporter;
use c975L\SiteBundle\Twig\CopyrightExtension;
use c975L\SiteBundle\Twig\MenuExtension;
use c975L\UiBundle\Entity\Block;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Cache\Adapter\TagAwareAdapter;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\Cache\TagAwareCacheInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class MenuExtensionTest extends TestCase
{
    // Real in-memory tag-aware pool (not a stub): storeSerialized stays at its default (true), the same as the production filesystem-backed pool, so a test would catch a Block that doesn't actually survive a cache round-trip
    private function createCache(): TagAwareCacheInterface
    {
        return new TagAwareAdapter(new ArrayAdapter());
    }

    // Builds a MenuRepository double whose findOneByLocation() answers $menu (null if not given)
    private function createMenuRepository(?Menu $menu = null): MenuRepository
    {
        $repository = $this->createStub(MenuRepository::class);
        $repository->method('findOneByLocation')->willReturn($menu);

        return $repository;
    }

    // Builds a PageRepository double whose find() answers $pagesById, keyed by (string) id
    private function createPageRepository(array $pagesById = []): PageRepository
    {
        $repository = $this->createStub(PageRepository::class);
        $repository->method('find')->willReturnCallback(
            static fn (mixed $id): ?Page => $pagesById[(string) $id] ?? null
        );

        return $repository;
    }

    private function createRegistry(array $registeredRoutes): LinkableRouteRegistry
    {
        $registry = $this->createStub(LinkableRouteRegistry::class);
        $registry->method('has')->willReturnCallback(static fn (string $name): bool => \in_array($name, array_keys($registeredRoutes), true));
        $registry->method('get')->willReturnCallback(static fn (string $name): ?array => $registeredRoutes[$name] ?? null);

        return $registry;
    }

    private function createRouter(): UrlGeneratorInterface
    {
        $router = $this->createStub(UrlGeneratorInterface::class);
        $router->method('generate')->willReturnCallback(
            static fn (string $name, array $params = []): string => '/' . $name . (isset($params['page']) ? '/' . $params['page'] : '')
        );

        return $router;
    }

    private function createTranslator(): TranslatorInterface
    {
        $translator = $this->createStub(TranslatorInterface::class);
        $translator->method('trans')->willReturnCallback(static fn (string $id): string => $id);

        return $translator;
    }

    // "site-menu-link-copyright-auto" answers $copyrightAuto, every other slug null
    private function createConfigService(bool $copyrightAuto = true): ConfigServiceInterface
    {
        $configService = $this->createStub(ConfigServiceInterface::class);
        $configService->method('get')->willReturnCallback(
            static fn (string $slug): mixed => 'site-menu-link-copyright-auto' === $slug ? $copyrightAuto : null
        );

        return $configService;
    }

    // getLegalPageSlugsByModel() answers a single "france/copyright" => $copyrightSlug entry, or none at all when $copyrightSlug is null
    private function createDefaultPagesImporter(?string $copyrightSlug = null): DefaultPagesImporter
    {
        $importer = $this->createStub(DefaultPagesImporter::class);
        $importer->method('getLegalPageSlugsByModel')->willReturn(
            null === $copyrightSlug ? [] : ['france/copyright' => $copyrightSlug]
        );

        return $importer;
    }

    private function createCopyrightExtension(string $copyright = '© 2026'): CopyrightExtension
    {
        $extension = $this->createStub(CopyrightExtension::class);
        $extension->method('getCopyright')->willReturn($copyright);

        return $extension;
    }

    private function createExtension(
        LinkableRouteRegistry $registry,
        array $pagesById = [],
        ?MenuRepository $menuRepository = null,
        ?TagAwareCacheInterface $cache = null,
        ?ConfigServiceInterface $configService = null,
        ?DefaultPagesImporter $defaultPagesImporter = null,
        ?CopyrightExtension $copyrightExtension = null,
    ): MenuExtension {
        return new MenuExtension(
            $menuRepository ?? $this->createMenuRepository(),
            $this->createPageRepository($pagesById),
            $registry,
            $this->createRouter(),
            $this->createTranslator(),
            $cache ?? $this->createCache(),
            $configService ?? $this->createConfigService(),
            $defaultPagesImporter ?? $this->createDefaultPagesImporter(),
            $copyrightExtension ?? $this->createCopyrightExtension(),
        );
    }

    // The email-footer location has no dedicated navigation entity, only Blocks attached to its Menu row
    public function testGetMenuBlocksReturnsBlocksOfTheMatchingMenu(): void
    {
        $block = (new Block())->setKind('menu_link')->setData(['target' => 'route:gone']);
        $menu = (new Menu())->setLocation(Menu::LOCATION_EMAIL_FOOTER);
        $menu->addBlock($block);
        $extension = $this->createExtension($this->createRegistry([]), [], $this->createMenuRepository($menu));

        $blocks = $extension->getMenuBlocks(Menu::LOCATION_EMAIL_FOOTER);

        $this->assertCount(1, $blocks);
        // Not assertSame(): the cached-and-reconstructed Block is a fresh object after the pool's (de)serialization round-trip, equal in value but no longer the same instance
        $this->assertEquals($block, $blocks->first());
    }

    // Same as above for email-header - its own Menu row, independent from email-footer's
    public function testGetMenuBlocksReturnsBlocksOfTheMatchingMenuForEmailHeader(): void
    {
        $block = (new Block())->setKind('menu_link')->setData(['target' => 'route:gone']);
        $menu = (new Menu())->setLocation(Menu::LOCATION_EMAIL_HEADER);
        $menu->addBlock($block);
        $extension = $this->createExtension($this->createRegistry([]), [], $this->createMenuRepository($menu));

        $blocks = $extension->getMenuBlocks(Menu::LOCATION_EMAIL_HEADER);

        $this->assertCount(1, $blocks);
        $this->assertEquals($block, $blocks->first());
    }

    // A fresh MenuExtension instance (simulating a new request) sharing the same cache pool must not hit the repository again - the whole point of caching across requests
    public function testGetMenuBlocksSurvivesAcrossInstancesSharingTheSameCachePool(): void
    {
        $block = (new Block())->setKind('menu_link')->setData(['target' => 'route:gone']);
        $menu = (new Menu())->setLocation(Menu::LOCATION_NAVBAR);
        $menu->addBlock($block);

        $menuRepository = $this->createMock(MenuRepository::class);
        $menuRepository->expects($this->once())->method('findOneByLocation')->willReturn($menu);

        $cache = $this->createCache();
        $firstRequest = $this->createExtension($this->createRegistry([]), [], $menuRepository, $cache);
        $this->assertCount(1, $firstRequest->getMenuBlocks(Menu::LOCATION_NAVBAR));

        $secondRequest = $this->createExtension($this->createRegistry([]), [], $menuRepository, $cache);
        $this->assertCount(1, $secondRequest->getMenuBlocks(Menu::LOCATION_NAVBAR));
    }

    // No Menu row yet for that location: an empty collection is returned instead of erroring out
    public function testGetMenuBlocksReturnsEmptyCollectionWhenMenuDoesNotExist(): void
    {
        $extension = $this->createExtension($this->createRegistry([]), [], $this->createMenuRepository(null));

        $this->assertCount(0, $extension->getMenuBlocks(Menu::LOCATION_EMAIL_FOOTER));
    }

    // Navbar.html.twig calls menu_blocks('navbar') twice in the same render (an "is not empty" guard, then the actual render) - only the first call should hit the repository
    public function testGetMenuBlocksMemoizesPerLocation(): void
    {
        $block = new Block();
        $menu = (new Menu())->setLocation(Menu::LOCATION_NAVBAR);
        $menu->addBlock($block);

        $menuRepository = $this->createMock(MenuRepository::class);
        $menuRepository->expects($this->once())->method('findOneByLocation')->with(Menu::LOCATION_NAVBAR)->willReturn($menu);

        $extension = new MenuExtension(
            $menuRepository,
            $this->createPageRepository(),
            $this->createRegistry([]),
            $this->createRouter(),
            $this->createTranslator(),
            $this->createCache(),
            $this->createConfigService(),
            $this->createDefaultPagesImporter(),
            $this->createCopyrightExtension(),
        );

        $first = $extension->getMenuBlocks(Menu::LOCATION_NAVBAR);
        $second = $extension->getMenuBlocks(Menu::LOCATION_NAVBAR);

        $this->assertSame($first, $second);
    }

    // getMenuBlocks() preloads every "menu_link" block's target Page in one findBy() call, so the per-link getMenuLinkUrl()/getMenuLinkLabel() calls that follow (as a template would make, one pair per link) resolve from memory instead of one find() call each
    public function testGetMenuBlocksPreloadsAllMenuLinkTargetPagesInOneBatchQuery(): void
    {
        $pageA = (new Page())->setTitle('About')->setSlug('about')->setIsPublished(true);
        (new \ReflectionProperty(Page::class, 'id'))->setValue($pageA, 42);
        $pageB = (new Page())->setTitle('Contact')->setSlug('contact')->setIsPublished(true);
        (new \ReflectionProperty(Page::class, 'id'))->setValue($pageB, 43);

        $menu = (new Menu())->setLocation(Menu::LOCATION_NAVBAR);
        $menu->addBlock((new Block())->setKind('menu_link')->setData(['target' => 'page:42']));
        $menu->addBlock((new Block())->setKind('menu_link')->setData(['target' => 'page:43#services-7']));

        $pageRepository = $this->createMock(PageRepository::class);
        $pageRepository->expects($this->once())
            ->method('findBy')
            ->with($this->callback(fn (array $criteria) => [] === array_diff(['42', '43'], $criteria['id']) && [] === array_diff($criteria['id'], ['42', '43'])))
            ->willReturn([$pageA, $pageB]);
        $pageRepository->expects($this->never())->method('find');

        $extension = new MenuExtension(
            $this->createMenuRepository($menu),
            $pageRepository,
            $this->createRegistry([]),
            $this->createRouter(),
            $this->createTranslator(),
            $this->createCache(),
            $this->createConfigService(),
            $this->createDefaultPagesImporter(),
            $this->createCopyrightExtension(),
        );

        $extension->getMenuBlocks(Menu::LOCATION_NAVBAR);

        $this->assertSame('/page_display/about', $extension->getMenuLinkUrl('page:42'));
        $this->assertSame('/page_display/contact#services-7', $extension->getMenuLinkUrl('page:43#services-7'));
        $this->assertSame('About', $extension->getMenuLinkLabel('page:42'));
    }

    // A target whose Page id yields no row (deleted since the menu_link was saved) must still be cached as null - otherwise it would fall back to one individual find() per resolution call instead of being resolved once for the whole request
    public function testGetMenuBlocksCachesAMissingPreloadedPageAsNull(): void
    {
        $menu = (new Menu())->setLocation(Menu::LOCATION_NAVBAR);
        $menu->addBlock((new Block())->setKind('menu_link')->setData(['target' => 'page:99']));

        $pageRepository = $this->createMock(PageRepository::class);
        $pageRepository->expects($this->once())->method('findBy')->willReturn([]);
        $pageRepository->expects($this->never())->method('find');

        $extension = new MenuExtension(
            $this->createMenuRepository($menu),
            $pageRepository,
            $this->createRegistry([]),
            $this->createRouter(),
            $this->createTranslator(),
            $this->createCache(),
            $this->createConfigService(),
            $this->createDefaultPagesImporter(),
            $this->createCopyrightExtension(),
        );

        $extension->getMenuBlocks(Menu::LOCATION_NAVBAR);

        $this->assertSame('', $extension->getMenuLinkUrl('page:99'));
        $this->assertSame('', $extension->getMenuLinkLabel('page:99'));
    }

    // A "page:ID" target resolves via the page_display route, using the page's slug
    public function testGetMenuLinkUrlResolvesPageDisplayRouteForPageTargets(): void
    {
        $page = (new Page())->setTitle('About')->setSlug('about')->setIsPublished(true);
        $extension = $this->createExtension($this->createRegistry([]), ['42' => $page]);

        $this->assertSame('/page_display/about', $extension->getMenuLinkUrl('page:42'));
    }

    // A "page:ID" target whose page is unpublished or deleted no longer resolves - the block template skips rendering it, same as the removed MenuExtension::getMenuItems() live filtering did
    public function testGetMenuLinkUrlReturnsEmptyStringForUnpublishedPageTarget(): void
    {
        $page = (new Page())->setTitle('Draft')->setSlug('draft')->setIsPublished(false);
        $extension = $this->createExtension($this->createRegistry([]), ['42' => $page]);

        $this->assertSame('', $extension->getMenuLinkUrl('page:42'));
    }

    // A "route:NAME" target resolves via the router directly, using its route name
    public function testGetMenuLinkUrlResolvesDirectRouteForRouteTargets(): void
    {
        $registry = $this->createRegistry(['contactform_display' => ['label' => 'label.contact', 'translation_domain' => 'contactform']]);
        $extension = $this->createExtension($registry);

        $this->assertSame('/contactform_display', $extension->getMenuLinkUrl('route:contactform_display'));
    }

    // A "route:NAME" target whose route disappeared from the registry (contributing bundle removed) no longer resolves
    public function testGetMenuLinkUrlReturnsEmptyStringForUnregisteredRouteTarget(): void
    {
        $extension = $this->createExtension($this->createRegistry([]));

        $this->assertSame('', $extension->getMenuLinkUrl('route:gone'));
    }

    // A "page:ID#anchor-blockId" target (see MenuLinkType's anchor choices, UiBundle's BlockAnchorSlugger) resolves the page normally and appends the fragment as-is
    public function testGetMenuLinkUrlAppendsFragmentForPageTargetWithAnchor(): void
    {
        $page = (new Page())->setTitle('Home')->setSlug('home')->setIsPublished(true);
        $extension = $this->createExtension($this->createRegistry([]), ['42' => $page]);

        $this->assertSame('/page_display/home#services-7', $extension->getMenuLinkUrl('page:42#services-7'));
    }

    // A "page:ID" target's label is the page's own title
    public function testGetMenuLinkLabelReturnsPageTitle(): void
    {
        $page = (new Page())->setTitle('Contact us')->setSlug('contact');
        $extension = $this->createExtension($this->createRegistry([]), ['42' => $page]);

        $this->assertSame('Contact us', $extension->getMenuLinkLabel('page:42'));
    }

    // A "route:NAME" target's label is translated using the label/domain declared by the contributing provider
    public function testGetMenuLinkLabelTranslatesRegisteredRouteLabel(): void
    {
        $registry = $this->createRegistry(['contactform_display' => ['label' => 'label.contact', 'translation_domain' => 'contactform']]);
        $extension = $this->createExtension($registry);

        $this->assertSame('label.contact', $extension->getMenuLinkLabel('route:contactform_display'));
    }

    // A "route:NAME" target whose route disappeared from the registry yields an empty label
    public function testGetMenuLinkLabelReturnsEmptyStringForUnregisteredRouteTarget(): void
    {
        $extension = $this->createExtension($this->createRegistry([]));

        $this->assertSame('', $extension->getMenuLinkLabel('route:gone'));
    }

    // A "page:ID#anchor-blockId" target resolves to that section's own label (its block's title, see MenuLinkType's picker), not the page's own title - otherwise two different anchored links on the same page would render with the same identical label
    public function testGetMenuLinkLabelReturnsTheSectionsOwnTitleForTargetWithAnchor(): void
    {
        $block = new Block();
        (new \ReflectionProperty(Block::class, 'id'))->setValue($block, 7);
        $block->setData(['anchor' => 'services', 'title' => 'Our services']);

        $page = (new Page())->setTitle('Home')->setSlug('home');
        $page->addBlock($block);
        $extension = $this->createExtension($this->createRegistry([]), ['42' => $page]);

        $this->assertSame('Our services', $extension->getMenuLinkLabel('page:42#services-7'));
    }

    // No title set on the block (e.g. a kind whose "title" isn't a TrixEditorType) falls back to the raw anchor, same fallback as MenuLinkType's own picker choices
    public function testGetMenuLinkLabelFallsBackToTheAnchorWhenBlockHasNoTitle(): void
    {
        $block = new Block();
        (new \ReflectionProperty(Block::class, 'id'))->setValue($block, 7);
        $block->setData(['anchor' => 'contact']);

        $page = (new Page())->setTitle('Home')->setSlug('home');
        $page->addBlock($block);
        $extension = $this->createExtension($this->createRegistry([]), ['42' => $page]);

        $this->assertSame('contact', $extension->getMenuLinkLabel('page:42#contact-7'));
    }

    // The section's block was since removed/moved off the page - falls back to the page's own title rather than an empty/broken label
    public function testGetMenuLinkLabelFallsBackToPageTitleWhenAnchoredBlockNoLongerExists(): void
    {
        $page = (new Page())->setTitle('Home')->setSlug('home');
        $extension = $this->createExtension($this->createRegistry([]), ['42' => $page]);

        $this->assertSame('Home', $extension->getMenuLinkLabel('page:42#services-7'));
    }

    // The matched block still exists but had both its title and anchor cleared since the menu_link was saved - must fall back to the page's own title, not render an empty label
    public function testGetMenuLinkLabelFallsBackToPageTitleWhenAnchoredBlockHasNoTitleOrAnchor(): void
    {
        $block = new Block();
        (new \ReflectionProperty(Block::class, 'id'))->setValue($block, 7);
        $block->setData([]);

        $page = (new Page())->setTitle('Home')->setSlug('home');
        $page->addBlock($block);
        $extension = $this->createExtension($this->createRegistry([]), ['42' => $page]);

        $this->assertSame('Home', $extension->getMenuLinkLabel('page:42#services-7'));
    }

    // A "page:ID" target whose page is the site's own "Copyright" page (see DefaultPagesImporter's "france/copyright" model) shows the live computed copyright instead of the page's own title
    public function testGetMenuLinkLabelReturnsComputedCopyrightForTheCopyrightPage(): void
    {
        $page = (new Page())->setTitle('Copyright')->setSlug('copyright');
        $extension = $this->createExtension(
            $this->createRegistry([]),
            ['42' => $page],
            defaultPagesImporter: $this->createDefaultPagesImporter('copyright'),
            copyrightExtension: $this->createCopyrightExtension('© 2020 - 2026'),
        );

        $this->assertSame('© 2020 - 2026', $extension->getMenuLinkLabel('page:42'));
    }

    // "site-menu-link-copyright-auto" disabled: even the Copyright page itself falls back to its own title
    public function testGetMenuLinkLabelReturnsPageTitleWhenCopyrightAutoConfigIsDisabled(): void
    {
        $page = (new Page())->setTitle('Copyright')->setSlug('copyright');
        $extension = $this->createExtension(
            $this->createRegistry([]),
            ['42' => $page],
            configService: $this->createConfigService(false),
            defaultPagesImporter: $this->createDefaultPagesImporter('copyright'),
        );

        $this->assertSame('Copyright', $extension->getMenuLinkLabel('page:42'));
    }

    // An anchored target on the Copyright page still labels that specific section, not the computed copyright - only the page's own "whole page" link doubles as the copyright notice
    public function testGetMenuLinkLabelReturnsSectionTitleOnAnchoredCopyrightPageTarget(): void
    {
        $block = new Block();
        (new \ReflectionProperty(Block::class, 'id'))->setValue($block, 7);
        $block->setData(['anchor' => 'notice', 'title' => 'Notice']);

        $page = (new Page())->setTitle('Copyright')->setSlug('copyright');
        $page->addBlock($block);
        $extension = $this->createExtension(
            $this->createRegistry([]),
            ['42' => $page],
            defaultPagesImporter: $this->createDefaultPagesImporter('copyright'),
        );

        $this->assertSame('Notice', $extension->getMenuLinkLabel('page:42#notice-7'));
    }

    // menu_link_is_copyright() (see Footer.html.twig's "hasCopyrightLink") mirrors getMenuLinkLabel()'s own copyright detection
    public function testIsMenuLinkCopyrightReturnsTrueForTheCopyrightPageTarget(): void
    {
        $page = (new Page())->setTitle('Copyright')->setSlug('copyright');
        $extension = $this->createExtension(
            $this->createRegistry([]),
            ['42' => $page],
            defaultPagesImporter: $this->createDefaultPagesImporter('copyright'),
        );

        $this->assertTrue($extension->isMenuLinkCopyright('page:42'));
    }

    public function testIsMenuLinkCopyrightReturnsFalseForANonCopyrightPageTarget(): void
    {
        $page = (new Page())->setTitle('About')->setSlug('about');
        $extension = $this->createExtension(
            $this->createRegistry([]),
            ['42' => $page],
            defaultPagesImporter: $this->createDefaultPagesImporter('copyright'),
        );

        $this->assertFalse($extension->isMenuLinkCopyright('page:42'));
    }

    public function testIsMenuLinkCopyrightReturnsFalseForARouteTarget(): void
    {
        $extension = $this->createExtension($this->createRegistry(['contactform_display' => ['label' => 'label.contact', 'translation_domain' => 'contactform']]));

        $this->assertFalse($extension->isMenuLinkCopyright('route:contactform_display'));
    }
}
