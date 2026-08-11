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

// A page laid on a ground of its own (c975l/gallery-bundle's viewer, and any screen after it) fills this block rather than wrapping its content in a div, which would leave the page around it in the site's color
// Nothing fails when the block goes: the pages filling it are simply painted like every other one, which is exactly the kind of silence this catches
class BodyClassBlockTest extends TestCase
{
    public function testTheBodyOffersTheBlock(): void
    {
        $this->assertMatchesRegularExpression(
            '/<body id="top" class="\{\{[^}]+\}\}\{% block bodyClass %\}\{% endblock %\}"/',
            $this->layout(),
            'The layout\'s body no longer offers the "bodyClass" block, so a page has no way to be laid on a ground of its own.'
        );
    }

    // The navbar's own class and the page's are written side by side, so the two never end up glued into one unknown class name
    public function testTheNavbarClassKeepsItsSeparator(): void
    {
        $this->assertStringContainsString(
            "'navbar-fixed ' : ''",
            $this->layout(),
            'The trailing space went missing, so a fixed navbar and a page class are written as one word and neither applies.'
        );
    }

    private function layout(): string
    {
        $path = dirname(__DIR__) . '/templates/layout.html.twig';
        $this->assertFileExists($path);

        return (string) file_get_contents($path);
    }
}
