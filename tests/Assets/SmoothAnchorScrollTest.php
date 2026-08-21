<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\SiteBundle\Tests\Assets;

use PHPUnit\Framework\TestCase;

// Guards the anchor scrolling of assets/js/basic.js, which cancels the click it handles: what it takes for an anchor never reaches the server, and the repository has no browser to tell it wrong
class SmoothAnchorScrollTest extends TestCase
{
    private const string CONTROLLER = 'assets/js/basic.js';

    // A shop ordered by price, a listing on its second page: those links end on "#products" and share the path of the page they are on, and cancelling them left the listing exactly as it was
    public function testALinkChangingTheQueryIsNotTakenForAnAnchor(): void
    {
        $this->assertStringContainsString(
            'url.search !== window.location.search',
            $this->read(),
            'basic.js takes a link that only changes the query for a same-page anchor, and cancels the navigation it asks for.'
        );
    }

    // Behind a misconfigured proxy, an absolute link can carry another origin than the page it sits on: pushState refuses it, and the exception thrown after preventDefault() left the scroll button inert
    public function testALinkOfAnotherOriginIsNotTakenForAnAnchor(): void
    {
        $this->assertStringContainsString(
            'url.origin !== window.location.origin',
            $this->read(),
            'basic.js takes a link of another origin for a same-page anchor, and pushState throws on the click it has already cancelled.'
        );
    }

    // Every page carries a <base href>, against which a relative url resolves: pushing the hash alone left the address at the root of the site, path and query gone
    public function testTheWholeAddressIsPushed(): void
    {
        $controller = $this->read();

        $this->assertStringContainsString('history.pushState(null, "", url.href);', $controller);
        $this->assertStringNotContainsString('history.pushState(null, "", url.hash);', $controller);
    }

    private function read(): string
    {
        $path = \dirname(__DIR__, 2) . '/' . self::CONTROLLER;
        $this->assertFileExists($path);

        return (string) file_get_contents($path);
    }
}
