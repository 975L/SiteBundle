<?php
/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\SiteBundle\Tests\Entity;

use c975L\SiteBundle\Entity\Font;
use PHPUnit\Framework\TestCase;

class FontTest extends TestCase
{
    public function testIsVariableIsTrueOnlyForTheVariableWeightSentinel(): void
    {
        $this->assertTrue((new Font())->setWeight(Font::WEIGHT_VARIABLE)->isVariable());
        $this->assertFalse((new Font())->setWeight(400)->isVariable());
    }

    public function testSetWeightDefaultsToRegularWhenGivenNull(): void
    {
        $font = (new Font())->setWeight(700)->setWeight(null);

        $this->assertSame(400, $font->getWeight());
    }

    public function testSetStyleDefaultsToNormalWhenGivenNull(): void
    {
        $font = (new Font())->setStyle('italic')->setStyle(null);

        $this->assertSame('normal', $font->getStyle());
    }

    public function testGetFormatMapsKnownExtensionsToTheirFontFaceToken(): void
    {
        $this->assertSame('truetype', (new Font())->setFilename('medias/fonts/font-1.ttf')->getFormat());
        $this->assertSame('woff', (new Font())->setFilename('medias/fonts/font-1.woff')->getFormat());
        $this->assertSame('woff2', (new Font())->setFilename('medias/fonts/font-1.WOFF2')->getFormat());
    }

    public function testGetFormatReturnsNullForAnUnknownOrMissingFilename(): void
    {
        $this->assertNull((new Font())->setFilename('medias/fonts/font-1.otf')->getFormat());
        $this->assertNull((new Font())->getFormat());
    }

    public function testGetVichMediaPathIncludesUniqidWhenIdIsNotYetAssigned(): void
    {
        $this->assertMatchesRegularExpression(
            '#^medias/fonts/font-[a-f0-9]+$#',
            (new Font())->getVichMediaPath()
        );
    }

    public function testGetVichMediaPathUsesTheRealIdOnceAssigned(): void
    {
        $font = new Font();
        (new \ReflectionProperty(Font::class, 'id'))->setValue($font, 42);

        $this->assertSame('medias/fonts/font-42', $font->getVichMediaPath());
    }

    public function testToStringShowsTheNumericWeightForAStaticFont(): void
    {
        $font = (new Font())->setName('Roboto')->setWeight(700)->setStyle('italic');

        $this->assertSame('Roboto 700 italic', (string) $font);
    }

    public function testToStringShowsVariableInsteadOfTheSentinelWeight(): void
    {
        $font = (new Font())->setName('Inter')->setWeight(Font::WEIGHT_VARIABLE)->setStyle('normal');

        $this->assertSame('Inter variable normal', (string) $font);
    }

    public function testToStringTrimsTheLeadingSpaceWhenNoNameIsSet(): void
    {
        $font = (new Font())->setWeight(400)->setStyle('normal');

        $this->assertSame('400 normal', (string) $font);
    }
}
