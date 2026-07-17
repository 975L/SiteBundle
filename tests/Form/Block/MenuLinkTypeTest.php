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
use Doctrine\ORM\QueryBuilder;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Form\Test\TypeTestCase;
use Symfony\Contracts\Translation\TranslatorInterface;

class MenuLinkTypeTest extends TypeTestCase
{
    private PageRepository $pageRepository;
    private LinkableRouteRegistry $linkableRouteRegistry;
    private TranslatorInterface $translator;

    protected function setUp(): void
    {
        $this->pageRepository = $this->createStub(PageRepository::class);
        $this->linkableRouteRegistry = $this->createStub(LinkableRouteRegistry::class);
        $this->linkableRouteRegistry->method('all')->willReturn([]);

        $this->translator = $this->createStub(TranslatorInterface::class);
        $this->translator->method('trans')->willReturnCallback(static fn (string $id) => $id);

        // TypeTestCase would otherwise create a bare, unconfigured mock for this - PHPUnit 13 flags
        // that as a notice ("no expectations configured"); a stub is the correct double for it anyway
        $this->dispatcher = $this->createStub(EventDispatcherInterface::class);

        parent::setUp();
    }

    protected function getTypes(): array
    {
        return [new MenuLinkType($this->linkableRouteRegistry, $this->pageRepository, $this->translator)];
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
        (new \ReflectionProperty($entity::class, 'id'))->setValue($entity, $id);

        return $entity;
    }

    private function blockWithAnchor(string $anchor, ?string $title = null): Block
    {
        $block = $this->withId(new Block(), 7);
        $block->setData(array_filter(['anchor' => $anchor, 'title' => $title]));

        return $block;
    }

    // A published page's own title is used as-is for its "page:ID" choice
    public function testPublishedPageChoiceUsesItsOwnTitle(): void
    {
        $page = $this->withId((new Page())->setTitle('Home')->setIsPublished(true), 1);
        $this->withPages([$page]);

        $form = $this->factory->create(MenuLinkType::class);

        $this->assertSame(['Home' => 'page:1'], $form->get('target')->getConfig()->getOption('choices'));
    }

    // Editors need to wire menu links while still drafting a page - it stays pickable, just flagged so
    // it's not mistaken for a live link (MenuExtension::getMenuLinkUrl() resolves it to '' meanwhile)
    public function testUnpublishedPageChoiceIsFlaggedAsDraft(): void
    {
        $page = $this->withId((new Page())->setTitle('Coming soon')->setIsPublished(false), 2);
        $this->withPages([$page]);

        $form = $this->factory->create(MenuLinkType::class);

        $this->assertSame(['Coming soon (label.draft)' => 'page:2'], $form->get('target')->getConfig()->getOption('choices'));
    }

    // A block carrying an "anchor" in its data (see UiBundle's BlockAnchorSlugger) adds a second, flat
    // choice for that in-page section, alongside the page's own entry
    public function testPageWithAnchoredBlockAddsASectionChoice(): void
    {
        $page = $this->withId((new Page())->setTitle('Home')->setIsPublished(true), 1);
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
        $page = $this->withId((new Page())->setTitle('Home')->setIsPublished(true), 1);
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
        $page = $this->withId((new Page())->setTitle('Home')->setIsPublished(true), 1);
        $page->addBlock($this->blockWithAnchor('cta', '<strong>Call to action</strong>'));
        $this->withPages([$page]);

        $form = $this->factory->create(MenuLinkType::class);

        $this->assertSame(
            ['Home' => 'page:1', 'Home → Call to action' => 'page:1#cta-7'],
            $form->get('target')->getConfig()->getOption('choices')
        );
    }

    // A block whose "anchor" key is blank/missing contributes no extra choice
    public function testBlockWithoutAnchorAddsNoSectionChoice(): void
    {
        $page = $this->withId((new Page())->setTitle('Home')->setIsPublished(true), 1);
        $page->addBlock($this->blockWithAnchor(''));
        $this->withPages([$page]);

        $form = $this->factory->create(MenuLinkType::class);

        $this->assertSame(['Home' => 'page:1'], $form->get('target')->getConfig()->getOption('choices'));
    }

    // The "asCopyright" checkbox (see MenuLink.html.twig) is always present, unrelated to target choices
    public function testAsCopyrightCheckboxIsPresent(): void
    {
        $this->withPages([]);

        $form = $this->factory->create(MenuLinkType::class);

        $this->assertTrue($form->has('asCopyright'));
        $this->assertFalse($form->get('asCopyright')->getConfig()->getRequired());
    }
}
