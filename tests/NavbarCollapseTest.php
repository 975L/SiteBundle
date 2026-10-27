<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\SiteBundle\Tests;

use PHPUnit\Framework\TestCase;

// menu-collapse.js folds a bar its links no longer fit on into the burger. Left unregistered or undeclared, it fails without a single error: the bar simply never folds, and the dropdowns never become the burger's headings
class NavbarCollapseTest extends TestCase
{
    private function file(string $name): string
    {
        return (string) file_get_contents(dirname(__DIR__) . '/' . $name);
    }

    public function testTheControllerIsRegistered(): void
    {
        $controllers = $this->file('assets/controllers.js');

        $this->assertStringContainsString("import MenuCollapseController from './js/menu-collapse.js';", $controllers);
        $this->assertStringContainsString("app.register('menu-collapse', MenuCollapseController);", $controllers);
    }

    // On the nav itself, the element the folding class goes on, with the items it measures as its target
    public function testTheNavbarDeclaresTheControllerAndItsTarget(): void
    {
        $navbar = $this->file('templates/components/General/Navbar.html.twig');

        $this->assertMatchesRegularExpression('/<nav class="[^"]*\bmenu\b[^>]*data-controller="[^"]*\bmenu-collapse\b/', $navbar);
        $this->assertStringContainsString('data-menu-collapse-target="items"', $navbar);
    }
}
