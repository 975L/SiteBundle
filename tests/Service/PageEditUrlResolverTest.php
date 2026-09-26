<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\SiteBundle\Tests\Service;

use c975L\ConfigBundle\Service\SiteLocales;
use c975L\SiteBundle\Controller\Management\PageCrudController;
use c975L\SiteBundle\Entity\Page;
use c975L\SiteBundle\Service\PageEditUrlResolver;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGeneratorInterface;
use PHPUnit\Framework\TestCase;

class PageEditUrlResolverTest extends TestCase
{
    private function createPage(int $id): Page
    {
        $page = new Page();
        new \ReflectionProperty($page, 'id')->setValue($page, $id);

        return $page;
    }

    public function testResolvesToThePageCrudControllerEditUrl(): void
    {
        $urlGenerator = $this->createMock(AdminUrlGeneratorInterface::class);
        $urlGenerator->method('unsetAll')->willReturnSelf();
        $urlGenerator->expects($this->once())->method('setController')->with(PageCrudController::class)->willReturnSelf();
        $urlGenerator->expects($this->once())->method('setAction')->with(Action::EDIT)->willReturnSelf();
        $urlGenerator->expects($this->once())->method('setEntityId')->with(42)->willReturnSelf();
        $urlGenerator->method('generateUrl')->willReturn('/management/page/42/edit');

        $resolver = new PageEditUrlResolver($urlGenerator, new SiteLocales(['fr', 'en'], 'fr'));

        $this->assertSame('/management/page/42/edit', $resolver->resolve($this->createPage(42)));
    }

    // A health check row about the English page sends its reader to the screen that language is written on, not to the one the page was written in
    public function testALanguageOpensTheScreenThatLanguageIsWrittenOn(): void
    {
        $query = [];
        $urlGenerator = $this->createStub(AdminUrlGeneratorInterface::class);
        $urlGenerator->method('unsetAll')->willReturnSelf();
        $urlGenerator->method('setController')->willReturnSelf();
        $urlGenerator->method('setAction')->willReturnSelf();
        $urlGenerator->method('setEntityId')->willReturnSelf();
        $urlGenerator->method('set')->willReturnCallback(function (string $name, mixed $value) use (&$query, $urlGenerator) {
            $query[$name] = $value;

            return $urlGenerator;
        });
        $urlGenerator->method('generateUrl')->willReturn('/management/page/42/edit');

        $resolver = new PageEditUrlResolver($urlGenerator, new SiteLocales(['fr', 'en'], 'fr'));
        $page = $this->createPage(42);

        $resolver->resolve($page, 'en');
        $this->assertSame(['contenu' => 'en'], $query);

        // The writing language keeps the url it always had
        $query = [];
        $resolver->resolve($page, 'fr');
        $this->assertSame([], $query);
    }

    // A content zone missing its page hands an editor the new page screen, the slug it reads prefilled
    public function testResolveNewOpensTheNewPageScreenWithTheSlug(): void
    {
        $urlGenerator = $this->createMock(AdminUrlGeneratorInterface::class);
        $urlGenerator->method('unsetAll')->willReturnSelf();
        $urlGenerator->expects($this->once())->method('setController')->with(PageCrudController::class)->willReturnSelf();
        $urlGenerator->expects($this->once())->method('setAction')->with(Action::NEW)->willReturnSelf();
        $urlGenerator->expects($this->once())->method('set')->with('slug', 'shortcut-preview')->willReturnSelf();
        $urlGenerator->method('generateUrl')->willReturn('/management/page/new?slug=shortcut-preview');

        $resolver = new PageEditUrlResolver($urlGenerator, new SiteLocales(['fr'], 'fr'));

        $this->assertSame('/management/page/new?slug=shortcut-preview', $resolver->resolveNew('shortcut-preview'));
    }
}
