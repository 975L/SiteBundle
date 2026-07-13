<?php
/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\SiteBundle\Tests\Service;

use c975L\SiteBundle\Service\BlockFixtureProvider;
use PHPUnit\Framework\TestCase;

class BlockFixtureProviderTest extends TestCase
{
    // "articles_slider" and "menu_link" are deliberately not covered - see the class comment
    public function testGetFixturesCoversLegalModelAndTwigContentOnly(): void
    {
        $fixtures = (new BlockFixtureProvider())->getFixtures();

        $this->assertSame(['legal_model', 'twig_content'], array_keys($fixtures));
        $this->assertSame([''], array_keys($fixtures['legal_model']));
        $this->assertSame([''], array_keys($fixtures['twig_content']));
    }

    // "model" must match one of LegalModelType's actual choices (a real template path under models/)
    public function testLegalModelFixtureUsesARealChoiceValue(): void
    {
        $fixtures = (new BlockFixtureProvider())->getFixtures();

        $this->assertSame('france/legal-notice', $fixtures['legal_model']['']['model']);
    }
}
