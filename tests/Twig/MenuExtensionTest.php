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
use c975L\SiteBundle\Entity\Menu;
use c975L\SiteBundle\Entity\Page;
use c975L\SiteBundle\Repository\MenuRepository;
use c975L\SiteBundle\Repository\PageRepository;
use c975L\SiteBundle\Twig\MenuExtension;
use c975L\UiBundle\Entity\Block;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class MenuExtensionTest extends TestCase
{
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

    private function createExtension(
        LinkableRouteRegistry $registry,
        array $pagesById = [],
        ?MenuRepository $menuRepository = null,
    ): MenuExtension {
        return new MenuExtension(
            $menuRepository ?? $this->createMenuRepository(),
            $this->createPageRepository($pagesById),
            $registry,
            $this->createRouter(),
            $this->createTranslator()
        );
    }

    // The email-footer location has no dedicated navigation entity, only Blocks attached to its Menu row
    public function testGetMenuBlocksReturnsBlocksOfTheMatchingMenu(): void
    {
        $block = new Block();
        $menu = (new Menu())->setLocation(Menu::LOCATION_EMAIL_FOOTER);
        $menu->addBlock($block);
        $extension = $this->createExtension($this->createRegistry([]), [], $this->createMenuRepository($menu));

        $blocks = $extension->getMenuBlocks(Menu::LOCATION_EMAIL_FOOTER);

        $this->assertCount(1, $blocks);
        $this->assertSame($block, $blocks->first());
    }

    // Same as above for email-header - its own Menu row, independent from email-footer's
    public function testGetMenuBlocksReturnsBlocksOfTheMatchingMenuForEmailHeader(): void
    {
        $block = new Block();
        $menu = (new Menu())->setLocation(Menu::LOCATION_EMAIL_HEADER);
        $menu->addBlock($block);
        $extension = $this->createExtension($this->createRegistry([]), [], $this->createMenuRepository($menu));

        $blocks = $extension->getMenuBlocks(Menu::LOCATION_EMAIL_HEADER);

        $this->assertCount(1, $blocks);
        $this->assertSame($block, $blocks->first());
    }

    // No Menu row yet for that location: an empty collection is returned instead of erroring out
    public function testGetMenuBlocksReturnsEmptyCollectionWhenMenuDoesNotExist(): void
    {
        $extension = $this->createExtension($this->createRegistry([]), [], $this->createMenuRepository(null));

        $this->assertCount(0, $extension->getMenuBlocks(Menu::LOCATION_EMAIL_FOOTER));
    }

    // A "page:ID" target resolves via the page_display route, using the page's slug
    public function testGetMenuLinkUrlResolvesPageDisplayRouteForPageTargets(): void
    {
        $page = (new Page())->setTitle('About')->setSlug('about')->setIsPublished(true);
        $extension = $this->createExtension($this->createRegistry([]), ['42' => $page]);

        $this->assertSame('/page_display/about', $extension->getMenuLinkUrl('page:42'));
    }

    // A "page:ID" target whose page is unpublished or deleted no longer resolves - the block template
    // skips rendering it, same as the removed MenuExtension::getMenuItems() live filtering did
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

    // A "page:ID#anchor-blockId" target (see MenuLinkType's anchor choices, UiBundle's
    // BlockAnchorSlugger) resolves the page normally and appends the fragment as-is
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

    // A "page:ID#anchor-blockId" target resolves to that section's own label (its block's title, see
    // MenuLinkType's picker), not the page's own title - otherwise two different anchored links on the
    // same page would render with the same identical label
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

    // No title set on the block (e.g. a kind whose "title" isn't a TrixEditorType) falls back to the
    // raw anchor, same fallback as MenuLinkType's own picker choices
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

    // The section's block was since removed/moved off the page - falls back to the page's own title
    // rather than an empty/broken label
    public function testGetMenuLinkLabelFallsBackToPageTitleWhenAnchoredBlockNoLongerExists(): void
    {
        $page = (new Page())->setTitle('Home')->setSlug('home');
        $extension = $this->createExtension($this->createRegistry([]), ['42' => $page]);

        $this->assertSame('Home', $extension->getMenuLinkLabel('page:42#services-7'));
    }

    // The matched block still exists but had both its title and anchor cleared since the menu_link
    // was saved - must fall back to the page's own title, not render an empty label
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
}
