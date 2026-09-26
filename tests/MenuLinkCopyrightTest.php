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

// The "©" of a copyright menu link is the footer's edit shortcut, which assets/js/edit-shortcut.js ignores inside a link - see EditShortcutBehaviourTest
class MenuLinkCopyrightTest extends TestCase
{
    public function testTheCopyrightSignIsWrittenOffTheLink(): void
    {
        $template = (string) file_get_contents(\dirname(__DIR__) . '/templates/blocks/MenuLink.html.twig');

        $this->assertStringContainsString("resolvedLabel starts with '© '", $template);
        $this->assertStringContainsString("isCopyright ? 'menu-item--copyright' : null", $template);
        $this->assertMatchesRegularExpression(
            '#<span class="menu-label">©</span>\s*<a href="\{\{ url \}\}" class="menu-link">\s*<span class="menu-label">\{\{ resolvedLabel\|slice\(2\) \}\}</span>#',
            $template,
            'The "©" of the copyright notice is back inside the link, so three clicks on it open the copyright page instead of the login form.'
        );
    }
}
