<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\SiteBundle\Tests\Assets;

use PHPUnit\Framework\Attributes\Group;

// assets/js/basic.js, whose whole job on a page is to give rel="external" links the target the W3C validator refuses to be written by hand
// It walks every anchor in the document rather than the ones under its own element, which is a thing to be sure of rather than to take on trust: an editor writes rel="external" in a Trix field, and a link in a menu or a footer is not to be opened in a tab nobody asked for. What it does about a missing console is deliberately left out - that shim answers a browser none of the supported ones is, and standing in for it would leave a no-op console behind for everything that runs after
#[Group('browser')]
class BasicBehaviourTest extends JsCase
{
    public function testALinkDeclaredExternalIsOpenedInATabOfItsOwn(): void
    {
        $this->assertSame('_blank', $this->page('return root.querySelector("#outside").target;'), 'A link an editor declared external opens over the site it was read from.');
    }

    // The attribute is what an editor writes; the target is what the validator refuses. Everything else is left exactly as it was rendered
    public function testALinkThatDeclaresNothingIsLeftAlone(): void
    {
        $left = $this->page('return { plain: root.querySelector("#inside").target, other: root.querySelector("#nofollow").target };');

        $this->assertSame('', $left['plain'], 'An ordinary link was opened in a tab of its own.');
        $this->assertSame('', $left['other'], 'A link carrying some other rel was opened in a tab of its own.');
    }

    // An anchor with no href at all is a jump target, not a link, and giving it a target says something about nothing
    public function testAnAnchorThatLeadsNowhereIsNotGivenATarget(): void
    {
        $this->assertSame('', $this->page('return root.querySelector("#anchor").target;'), 'An anchor with no href was treated as a link.');
    }

    // The page is not this controller's element: an editor writes the attribute in a body field, and a menu is rendered outside it
    public function testTheWholePageIsWalkedRatherThanTheElementItIsMountedOn(): void
    {
        $this->assertSame('_blank', $this->page('return root.querySelector("#footer").target;'), 'A link outside the mounted element is left as it was, so where the attribute works depends on where the controller sits.');
    }

    private function page(string $probe): mixed
    {
        return $this->observe(
            '<div data-controller="basic">
                <a id="inside" href="/mentions">Mentions</a>
                <a id="outside" href="https://example.org" rel="external">Ailleurs</a>
                <a id="nofollow" href="https://example.org" rel="nofollow">Ailleurs encore</a>
                <a id="anchor" rel="external">Un ancrage</a>
            </div>
            <footer><a id="footer" href="https://example.org" rel="external">Dans le pied de page</a></footer>',
            ['basic' => 'basic'],
            $probe
        );
    }
}
