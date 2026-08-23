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

// A navbar taken out of the flow needs the body to make room for it, which this layout says by handing "bodyClasses" over to the one it extends (see @c975LUi/layout.html.twig, which writes it before its own "bodyClass" block)
class NavbarBodyClassTest extends TestCase
{
    // The navbar's own class and the page's are written side by side, so the two never end up glued into one unknown class name
    public function testTheNavbarClassKeepsItsSeparator(): void
    {
        $this->assertStringContainsString(
            "~ 'navbar-fixed ' %}",
            $this->layout(),
            'The trailing space went missing, so a fixed navbar and a page class are written as one word and neither applies.'
        );
    }

    // Appended, never assigned: an app layout extending this one runs its own "set" first, and an assignment here would drop the classes it declared
    public function testTheNavbarClassIsAppendedToWhateverAnAppDeclared(): void
    {
        $this->assertStringContainsString(
            "{% set bodyClasses = bodyClasses|default('') ~ 'navbar-fixed ' %}",
            $this->layout(),
            'The layout assigns bodyClasses instead of appending to it, so an app layout setting its own body classes loses them.'
        );
    }

    // Same contract for the Stimulus controllers, where the loss is unconditional - this layout always writes the variable
    public function testTheStimulusControllerIsAppendedToWhateverAnAppDeclared(): void
    {
        $this->assertStringContainsString(
            "{% set bodyControllers = (bodyControllers|default('') ~ ' basic')|trim %}",
            $this->layout(),
            'The layout assigns bodyControllers instead of appending to it, so a controller declared by an app layout never reaches the body.'
        );
    }

    // Only a navbar out of the flow needs it: a static one leaves the body untouched
    public function testTheClassIsAddedForTheFixedPositionOnly(): void
    {
        $this->assertStringContainsString(
            "{% if config('site-navbar-position') == 'fixed' %}",
            $this->layout(),
            'The layout no longer reads the navbar position before making room for it.'
        );
    }

    private function layout(): string
    {
        $path = dirname(__DIR__) . '/templates/layout.html.twig';
        $this->assertFileExists($path);

        return (string) file_get_contents($path);
    }
}
