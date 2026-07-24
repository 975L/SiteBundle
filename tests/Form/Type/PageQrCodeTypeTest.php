<?php
/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\SiteBundle\Tests\Form\Type;

use c975L\SiteBundle\Form\Type\PageQrCodeType;
use PHPUnit\Framework\TestCase;
use Symfony\Component\OptionsResolver\OptionsResolver;

class PageQrCodeTypeTest extends TestCase
{
    public function testGetBlockPrefixMatchesTheFormThemeBlockName(): void
    {
        $this->assertSame('c975l_page_qrcode', (new PageQrCodeType())->getBlockPrefix());
    }

    public function testConfigureOptionsMarksTheFieldUnmappedAndNotRequired(): void
    {
        $resolver = new OptionsResolver();
        (new PageQrCodeType())->configureOptions($resolver);

        $options = $resolver->resolve();

        $this->assertFalse($options['mapped']);
        $this->assertFalse($options['required']);
    }
}
