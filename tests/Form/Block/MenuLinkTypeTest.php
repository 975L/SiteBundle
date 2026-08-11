<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\SiteBundle\Tests\Form\Block;

use c975L\ConfigBundle\Management\LinkableRouteRegistry;
use c975L\SiteBundle\Entity\Page;
use c975L\SiteBundle\Form\Block\MenuLinkType;
use c975L\SiteBundle\Repository\PageRepository;
use c975L\UiBundle\Entity\Block;
use c975L\UiBundle\Service\BlockAnchorCollector;
use Doctrine\ORM\QueryBuilder;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Form\Test\TypeTestCase;
use Symfony\Contracts\Translation\TranslatorInterface;

class MenuLinkTypeTest extends TypeTestCase
{
    private PageRepository $pageRepository;
    private LinkableRouteRegistry $linkableRouteRegistry;
    private TranslatorInterface $translator;

    /** @var array<string, string> Contributed target key => the label the picker shows it under, filled by withLinkableRoutes() */
    private array $linkableRoutes = [];

    protected function setUp(): void
    {
        $this->pageRepository = $this->createStub(PageRepository::class);
        // Read through a callback rather than a fixed value: the type is built once, by getTypes() below, where a test declaring its own targets runs afterwards
        $this->linkableRouteRegistry = $this->createStub(LinkableRouteRegistry::class);
        $this->linkableRouteRegistry->method('all')->willReturnCallback(fn (): array => $this->linkableRoutes);
        $this->linkableRouteRegistry->method('pickerLabel')->willReturnCallback(fn (string $name): string => $this->linkableRoutes[$name] ?? '');

        $this->translator = $this->createStub(TranslatorInterface::class);
        $this->translator->method('trans')->willReturnCallback(static fn (string $id) => $id);

        // TypeTestCase would otherwise create a bare, unconfigured mock for this - PHPUnit 13 flags that as a notice ("no expectations configured"); a stub is the correct double for it anyway
        $this->dispatcher = $this->createStub(EventDispatcherInterface::class);

        parent::setUp();
    }

    #[\Override]
    protected function getTypes(): array
    {
        return [new MenuLinkType($this->linkableRouteRegistry, $this->pageRepository, $this->translator, new BlockAnchorCollector())];
    }

    private function withPages(array $pages): void
    {
        $queryBuilder = $this->createStub(QueryBuilder::class);
        $queryBuilder->method('leftJoin')->willReturnSelf();
        $queryBuilder->method('addSelect')->willReturnSelf();
        $queryBuilder->method('andWhere')->willReturnSelf();
        $queryBuilder->method('setParameter')->willReturnSelf();
        $query = $this->createStub(\Doctrine\ORM\Query::class);
        $query->method('getResult')->willReturn($pages);
        $queryBuilder->method('getQuery')->willReturn($query);
        $this->pageRepository->method('createQueryBuilder')->willReturn($queryBuilder);
    }

    private function withId(object $entity, int $id): object
    {
        new \ReflectionProperty($entity::class, 'id')->setValue($entity, $id);

        return $entity;
    }

    private function blockWithAnchor(string $anchor, ?string $title = null): Block
    {
        $block = $this->withId(new Block(), 7);
        $block->setData(array_filter(['anchor' => $anchor, 'title' => $title]));

        return $block;
    }

    // A contributed target sits in the same flat list as the pages, labelled by the registry - by its picker label where it has one, an entry standing for one of a bundle's own rows saying what it is here where the rendered menu item keeps its bare title
    public function testContributedRouteChoicesUseTheirPickerLabel(): void
    {
        $this->withPages([]);
        $this->linkableRoutes = ['gallery_index' => 'Galerie', 'gallery_category.12' => 'Galerie - Paysages'];

        $form = $this->factory->create(MenuLinkType::class);

        $this->assertSame(
            ['Galerie' => 'route:gallery_index', 'Galerie - Paysages' => 'route:gallery_category.12'],
            $form->get('target')->getConfig()->getOption('choices')
        );
    }

    // Choices are keyed by label: a contributed target sharing a page's title used to silently take its place and make the page unpickable - both stay, the second one numbered
    public function testChoicesSharingALabelAreBothKept(): void
    {
        $this->withPages([
            $this->withId(new Page()->setTitle('Galerie')->setIsPublished(true), 1),
            $this->withId(new Page()->setTitle('Galerie')->setIsPublished(true), 2),
        ]);
        $this->linkableRoutes = ['gallery_index' => 'Galerie'];

        $form = $this->factory->create(MenuLinkType::class);

        $this->assertSame(
            ['Galerie' => 'page:1', 'Galerie (2)' => 'page:2', 'Galerie (3)' => 'route:gallery_index'],
            $form->get('target')->getConfig()->getOption('choices')
        );
    }

    // A published page's own title is used as-is for its "page:ID" choice
    public function testPublishedPageChoiceUsesItsOwnTitle(): void
    {
        $page = $this->withId(new Page()->setTitle('Home')->setIsPublished(true), 1);
        $this->withPages([$page]);

        $form = $this->factory->create(MenuLinkType::class);

        $this->assertSame(['Home' => 'page:1'], $form->get('target')->getConfig()->getOption('choices'));
    }

    // Editors need to wire menu links while still drafting a page - it stays pickable, just flagged so it's not mistaken for a live link (MenuExtension::getMenuLinkUrl() resolves it to '' meanwhile)
    public function testUnpublishedPageChoiceIsFlaggedAsDraft(): void
    {
        $page = $this->withId(new Page()->setTitle('Coming soon')->setIsPublished(false), 2);
        $this->withPages([$page]);

        $form = $this->factory->create(MenuLinkType::class);

        $this->assertSame(['Coming soon (label.draft)' => 'page:2'], $form->get('target')->getConfig()->getOption('choices'));
    }

    // A block carrying an "anchor" in its data (see UiBundle's BlockAnchorSlugger) adds a second, flat choice for that in-page section, alongside the page's own entry
    public function testPageWithAnchoredBlockAddsASectionChoice(): void
    {
        $page = $this->withId(new Page()->setTitle('Home')->setIsPublished(true), 1);
        $page->addBlock($this->blockWithAnchor('services', 'Our services'));
        $this->withPages([$page]);

        $form = $this->factory->create(MenuLinkType::class);

        $this->assertSame(
            ['Home' => 'page:1', 'Home → Our services' => 'page:1#services-7'],
            $form->get('target')->getConfig()->getOption('choices')
        );
    }

    // Falls back to the raw anchor when the block has no title of its own (e.g. no TrixEditorType title)
    public function testAnchoredBlockWithoutTitleFallsBackToTheAnchorItself(): void
    {
        $page = $this->withId(new Page()->setTitle('Home')->setIsPublished(true), 1);
        $page->addBlock($this->blockWithAnchor('contact'));
        $this->withPages([$page]);

        $form = $this->factory->create(MenuLinkType::class);

        $this->assertSame(
            ['Home' => 'page:1', 'Home → contact' => 'page:1#contact-7'],
            $form->get('target')->getConfig()->getOption('choices')
        );
    }

    // A TrixEditorType title may carry inline markup that must not leak into this plain-text select label
    public function testAnchoredBlockTitleIsStrippedOfMarkup(): void
    {
        $page = $this->withId(new Page()->setTitle('Home')->setIsPublished(true), 1);
        $page->addBlock($this->blockWithAnchor('cta', '<strong>Call to action</strong>'));
        $this->withPages([$page]);

        $form = $this->factory->create(MenuLinkType::class);

        $this->assertSame(
            ['Home' => 'page:1', 'Home → Call to action' => 'page:1#cta-7'],
            $form->get('target')->getConfig()->getOption('choices')
        );
    }

    // The sections of a page are often nested in a container ("flex_columns" and its slots), and a "text_section" carries its anchor as an auto-derived "slug" rendered as-is - both stayed invisible in this picker while it only walked top-level blocks reading "anchor"
    public function testAnchorsNestedInAContainerAreListed(): void
    {
        $page = $this->withId(new Page()->setTitle('Home')->setIsPublished(true), 1);
        $container = $this->withId(new Block()->setKind('flex_columns'), 58);
        $section = $this->withId(new Block()->setKind('text_section'), 16);
        $section->setData(['slug' => 'le-manifeste', 'title' => 'Le manifeste']);
        $container->addSlot($section);
        $page->addBlock($container);
        $this->withPages([$page]);

        $form = $this->factory->create(MenuLinkType::class);

        $this->assertSame(
            ['Home' => 'page:1', 'Home → Le manifeste' => 'page:1#le-manifeste'],
            $form->get('target')->getConfig()->getOption('choices')
        );
    }

    // A block whose "anchor" key is blank/missing contributes no extra choice
    public function testBlockWithoutAnchorAddsNoSectionChoice(): void
    {
        $page = $this->withId(new Page()->setTitle('Home')->setIsPublished(true), 1);
        $page->addBlock($this->blockWithAnchor(''));
        $this->withPages([$page]);

        $form = $this->factory->create(MenuLinkType::class);

        $this->assertSame(['Home' => 'page:1'], $form->get('target')->getConfig()->getOption('choices'));
    }

    // The "label" field (see MenuLink.html.twig) overrides the auto-derived page/section title - optional, always present
    public function testLabelFieldIsPresentAndOptional(): void
    {
        $this->withPages([]);

        $form = $this->factory->create(MenuLinkType::class);

        $this->assertTrue($form->has('label'));
        $this->assertFalse($form->get('label')->getConfig()->getRequired());
    }

    // The "primary" field (see MenuLink.html.twig / _menu.scss's .menu-item--primary) renders the link as a filled button - optional, always present
    public function testPrimaryFieldIsPresentAndOptional(): void
    {
        $this->withPages([]);

        $form = $this->factory->create(MenuLinkType::class);

        $this->assertTrue($form->has('primary'));
        $this->assertFalse($form->get('primary')->getConfig()->getRequired());
    }

    // The "strong" field (see MenuLink.html.twig / _menu.scss's .menu-item--strong) bolds the label - optional, always present, and independent from "primary"
    public function testStrongFieldIsPresentAndOptional(): void
    {
        $this->withPages([]);

        $form = $this->factory->create(MenuLinkType::class);

        $this->assertTrue($form->has('strong'));
        $this->assertFalse($form->get('strong')->getConfig()->getRequired());
    }
}
