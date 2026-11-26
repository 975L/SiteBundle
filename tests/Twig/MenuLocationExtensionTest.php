<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\SiteBundle\Tests\Twig;

use c975L\SiteBundle\Management\MenuVariantProviderInterface;
use c975L\SiteBundle\Service\MenuLocations;
use c975L\SiteBundle\Twig\MenuExtension;
use c975L\SiteBundle\Twig\MenuLocationExtension;
use c975L\UiBundle\Entity\Block;
use Doctrine\Common\Collections\ArrayCollection;
use PHPUnit\Framework\TestCase;
use Symfony\Contracts\Translation\TranslatorInterface;

class MenuLocationExtensionTest extends TestCase
{
    // Checks a page outside any variant, or in one no app declared, draws the site's own menu
    public function testNoVariantDrawsTheSitesMenu(): void
    {
        $extension = $this->extension(['navbar.members']);

        $this->assertSame('navbar', $extension->location('navbar'));
        $this->assertSame('navbar', $extension->location('navbar', ''));
        $this->assertSame('navbar', $extension->location('navbar', 'catalog'));
    }

    // Checks a variant draws its own menu once it holds a block, and the site's while it is empty
    public function testAVariantDrawsItsMenuOnceFilled(): void
    {
        $this->assertSame('navbar.members', $this->extension(['navbar.members'])->location('navbar', 'members'));
        $this->assertSame('footer', $this->extension(['navbar.members'])->location('footer', 'members'));
    }

    // The extension, an app declaring "members" alone, only the given locations holding a block
    /** @param list<string> $filled */
    private function extension(array $filled): MenuLocationExtension
    {
        $translator = $this->createStub(TranslatorInterface::class);
        $translator->method('trans')->willReturnArgument(0);
        $provider = $this->createStub(MenuVariantProviderInterface::class);
        $provider->method('getMenuVariants')->willReturn(['members' => 'item-1']);
        $menuExtension = $this->createStub(MenuExtension::class);
        $menuExtension->method('getMenuBlocks')->willReturnCallback(static fn (string $location): ArrayCollection => new ArrayCollection(\in_array($location, $filled, true) ? [new Block()] : []));

        return new MenuLocationExtension(new MenuLocations($translator, [$provider]), $menuExtension);
    }
}
