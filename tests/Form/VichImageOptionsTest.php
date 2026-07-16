<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\SiteBundle\Tests\Form;

use c975L\SiteBundle\Form\VichImageOptions;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Validator\Constraints\File as FileConstraint;

class VichImageOptionsTest extends TestCase
{
    public function testDefaultUsesTenMegabytesAndNotRequiredWhenNoArgsGiven(): void
    {
        $options = VichImageOptions::default();

        $this->assertFalse($options['required']);
        $this->assertTrue($options['allow_delete']);
        $this->assertTrue($options['download_uri']);
        $this->assertTrue($options['asset_helper']);
        $this->assertCount(1, $options['constraints']);
        $this->assertInstanceOf(FileConstraint::class, $options['constraints'][0]);
        $this->assertSame(10_000_000, $options['constraints'][0]->maxSize);
    }

    public function testDefaultAppliesTheGivenMaxSizeAndRequiredFlag(): void
    {
        $options = VichImageOptions::default('2M', true);

        $this->assertTrue($options['required']);
        $this->assertSame(2_000_000, $options['constraints'][0]->maxSize);
    }
}
