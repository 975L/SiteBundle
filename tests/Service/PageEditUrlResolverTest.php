<?php
/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\SiteBundle\Tests\Service;

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
        (new \ReflectionProperty($page, 'id'))->setValue($page, $id);

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

        $resolver = new PageEditUrlResolver($urlGenerator);

        $this->assertSame('/management/page/42/edit', $resolver->resolve($this->createPage(42)));
    }
}
