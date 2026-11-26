<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\SiteBundle\Tests\Service;

use c975L\SiteBundle\Management\MenuVariantProviderInterface;
use c975L\SiteBundle\Service\MenuLocations;
use PHPUnit\Framework\TestCase;
use Symfony\Contracts\Translation\TranslatorInterface;

class MenuLocationsTest extends TestCase
{
    // Checks a site declaring no variant keeps the bundle's own five locations, in their order
    public function testWithoutVariantsTheBundlesOwnLocationsOnly(): void
    {
        $this->assertSame(['navbar', 'navbar-brand', 'footer', 'email-header', 'email-footer'], array_keys($this->locations()->all()));
        $this->assertFalse($this->locations()->has('navbar.members'));
    }

    // Checks each variant adds a navbar and a footer, named after the menu they stand in for
    public function testAVariantAddsANavbarAndAFooter(): void
    {
        $locations = $this->locations(['members' => 'item-1']);

        $this->assertTrue($locations->has('navbar.members'));
        $this->assertSame('label.footer · item-1', $locations->label('footer.members'));
        $this->assertSame('label.menu', $locations->label('sidebar'));
    }

    // Checks the location of a variant's menu, null for a variant nobody declared and for a location variants have none of
    public function testVariantLocation(): void
    {
        $locations = $this->locations(['members' => 'item-1']);

        $this->assertSame('navbar.members', $locations->variantLocation('navbar', 'members'));
        $this->assertNull($locations->variantLocation('navbar', 'catalog'));
        $this->assertNull($locations->variantLocation('navbar', null));
        $this->assertNull($locations->variantLocation('email-footer', 'members'));
    }

    // Checks the location a variant's menu stands in for
    public function testBase(): void
    {
        $this->assertSame('navbar', MenuLocations::base('navbar.members'));
        $this->assertSame('navbar-brand', MenuLocations::base('navbar-brand'));
    }

    // Checks a key that would not fit a location or a cache key is refused at once
    public function testAKeyThatDoesNotFitIsRefused(): void
    {
        $this->expectException(\LogicException::class);

        $this->locations(['Kids:1' => 'item-1'])->all();
    }

    // The locations, an app declaring the given variants, the labels left as their keys
    /** @param array<string, string> $variants */
    private function locations(array $variants = []): MenuLocations
    {
        $translator = $this->createStub(TranslatorInterface::class);
        $translator->method('trans')->willReturnArgument(0);
        $provider = $this->createStub(MenuVariantProviderInterface::class);
        $provider->method('getMenuVariants')->willReturn($variants);

        return new MenuLocations($translator, [$provider]);
    }
}
