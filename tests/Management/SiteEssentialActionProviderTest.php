<?php
/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\SiteBundle\Tests\Management;

use c975L\SiteBundle\Entity\Menu;
use c975L\SiteBundle\Entity\Page;
use c975L\SiteBundle\Management\SiteEssentialActionProvider;
use c975L\SiteBundle\Repository\FontRepository;
use c975L\SiteBundle\Repository\MenuRepository;
use c975L\SiteBundle\Repository\PageRepository;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGeneratorInterface;
use PHPUnit\Framework\TestCase;

class SiteEssentialActionProviderTest extends TestCase
{
    private function createPageRepository(bool $homeExists): PageRepository
    {
        $repository = $this->createStub(PageRepository::class);
        $repository->method('findOneBySlug')->willReturnCallback(
            static function (string $slug) use ($homeExists): ?Page {
                if ('home' !== $slug || !$homeExists) {
                    return null;
                }

                $page = new Page();
                (new \ReflectionProperty(Page::class, 'id'))->setValue($page, 1);

                return $page;
            }
        );

        return $repository;
    }

    private function createMenuRepository(array $existingLocations = []): MenuRepository
    {
        $repository = $this->createStub(MenuRepository::class);
        $repository->method('findOneByLocation')->willReturnCallback(
            static fn (string $location): ?Menu => \in_array($location, $existingLocations, true) ? new Menu() : null
        );

        return $repository;
    }

    private function createFontRepository(bool $hasAny): FontRepository
    {
        $repository = $this->createStub(FontRepository::class);
        $repository->method('findAllOrdered')->willReturn($hasAny ? [['name' => 'Roboto']] : []);

        return $repository;
    }

    private function createAdminUrlGenerator(array &$calls = []): AdminUrlGeneratorInterface
    {
        $generator = $this->createStub(AdminUrlGeneratorInterface::class);
        $generator->method('unsetAll')->willReturnSelf();
        $generator->method('setController')->willReturnSelf();
        $generator->method('setAction')->willReturnCallback(function (string $action) use ($generator, &$calls) {
            $calls[] = ['action' => $action];

            return $generator;
        });
        $generator->method('setEntityId')->willReturnCallback(function (int|string $entityId) use ($generator, &$calls) {
            $calls[array_key_last($calls)]['entityId'] = $entityId;

            return $generator;
        });
        $generator->method('generateUrl')->willReturn('/management/x');

        return $generator;
    }

    private function createProvider(bool $homeExists, array $menuLocations, bool $hasFont, array &$generatorCalls = []): SiteEssentialActionProvider
    {
        return new SiteEssentialActionProvider(
            $this->createPageRepository($homeExists),
            $this->createMenuRepository($menuLocations),
            $this->createFontRepository($hasFont),
            $this->createAdminUrlGenerator($generatorCalls),
        );
    }

    public function testGetEssentialActionsReturnsThreeActionsContinuingConfigBundlesOrderSequence(): void
    {
        $provider = $this->createProvider(false, [], false);

        $actions = $provider->getEssentialActions();

        $this->assertSame(['pages', 'menus', 'fonts'], array_column($actions, 'slug'));
        $this->assertSame([50, 60, 70], array_column($actions, 'order'));
    }

    public function testPagesActionIsDoneOnlyWhenTheHomepageExists(): void
    {
        $this->assertTrue($this->createProvider(true, [], false)->getEssentialActions()[0]['isDone']);
        $this->assertFalse($this->createProvider(false, [], false)->getEssentialActions()[0]['isDone']);
    }

    public function testPagesActionLinksStraightToTheHomepageEditScreenWhenItExists(): void
    {
        $calls = [];
        $this->createProvider(true, [], false, $calls)->getEssentialActions();

        $this->assertSame(['action' => 'edit', 'entityId' => 1], $calls[0]);
    }

    public function testPagesActionLinksToTheIndexWhenTheHomepageDoesNotExistYet(): void
    {
        $calls = [];
        $this->createProvider(false, [], false, $calls)->getEssentialActions();

        $this->assertSame(['action' => 'index'], $calls[0]);
    }

    public function testMenusActionRequiresBothNavbarAndFooterLocations(): void
    {
        $this->assertFalse($this->createProvider(false, [Menu::LOCATION_NAVBAR], false)->getEssentialActions()[1]['isDone']);
        $this->assertFalse($this->createProvider(false, [Menu::LOCATION_FOOTER], false)->getEssentialActions()[1]['isDone']);
        $this->assertTrue($this->createProvider(false, [Menu::LOCATION_NAVBAR, Menu::LOCATION_FOOTER], false)->getEssentialActions()[1]['isDone']);
    }

    public function testFontsActionIsDoneOnlyWhenAtLeastOneFontExists(): void
    {
        $this->assertTrue($this->createProvider(false, [], true)->getEssentialActions()[2]['isDone']);
        $this->assertFalse($this->createProvider(false, [], false)->getEssentialActions()[2]['isDone']);
    }
}
