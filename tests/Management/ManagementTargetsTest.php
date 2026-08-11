<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\SiteBundle\Tests\Management;

use c975L\ConfigBundle\Service\ConfigServiceInterface;
use c975L\ConfigBundle\Test\ManagementTargetsTestCase;
use c975L\SiteBundle\Management\MenuProvider;
use c975L\SiteBundle\Management\SiteEssentialActionProvider;
use c975L\SiteBundle\Management\SiteGuidedProjectProvider;
use c975L\SiteBundle\Management\SiteShortcutProvider;
use c975L\SiteBundle\Repository\MenuRepository;
use c975L\SiteBundle\Repository\PageRepository;
use c975L\UiBundle\Repository\FontRepository;
use Symfony\Contracts\Translation\TranslatorInterface;

// Every CRUD controller and route this bundle's management providers name, checked against what its controllers actually declare - see ConfigBundle's ManagementTargetsTestCase
class ManagementTargetsTest extends ManagementTargetsTestCase
{
    protected function managementProviders(): iterable
    {
        return [
            new MenuProvider(),
            new SiteShortcutProvider($this->createTranslator(), $this->createConfigService()),
            new SiteEssentialActionProvider(
                $this->createStub(PageRepository::class),
                $this->createStub(MenuRepository::class),
                $this->createStub(FontRepository::class),
                $this->adminUrlGenerator(),
            ),
            new SiteGuidedProjectProvider($this->adminUrlGenerator(), $this->createConfigService()),
        ];
    }

    // This bundle's own controllers on top of ConfigBundle's, whose screens its links point to as well
    #[\Override]
    protected function controllerDirectories(): array
    {
        return [...parent::controllerDirectories(), __DIR__ . '/../../src/Controller', __DIR__ . '/../../src/Controller/Management'];
    }

    private function createConfigService(): ConfigServiceInterface
    {
        $configService = $this->createStub(ConfigServiceInterface::class);
        $configService->method('get')->willReturn('ROLE_ADMIN');

        return $configService;
    }

    private function createTranslator(): TranslatorInterface
    {
        $translator = $this->createStub(TranslatorInterface::class);
        $translator->method('trans')->willReturnArgument(0);

        return $translator;
    }
}
