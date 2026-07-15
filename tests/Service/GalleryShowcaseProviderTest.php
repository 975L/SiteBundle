<?php
/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\SiteBundle\Tests\Service;

use c975L\SiteBundle\Service\GalleryShowcaseProvider;
use c975L\UiBundle\Entity\Media;
use c975L\UiBundle\Service\BlockFixtureMediaAttacher;
use PHPUnit\Framework\TestCase;
use Symfony\Contracts\Translation\TranslatorInterface;
use Twig\Environment;
use Twig\Loader\ArrayLoader;

class GalleryShowcaseProviderTest extends TestCase
{
    private function createProvider(): GalleryShowcaseProvider
    {
        // TemplateWrapper is final and can't be doubled - use a real Environment (ArrayLoader stubs
        // the one named template the provider renders) so createTemplate() also works for real
        $twig = new Environment(new ArrayLoader([
            '@c975LUi/components/Slider/Slider.html.twig' => '<!-- slider -->',
        ]));

        $translator = $this->createStub(TranslatorInterface::class);
        $translator->method('trans')->willReturnCallback(static fn (string $id) => $id);

        $mediaAttacher = $this->createStub(BlockFixtureMediaAttacher::class);
        $mediaAttacher->method('nextPlaceholderImage')->willReturnCallback(static fn () => new Media());

        return new GalleryShowcaseProvider($twig, $translator, $mediaAttacher);
    }

    public function testGetShowcasesReturnsArticlesSliderAndMenuLinkSections(): void
    {
        $showcases = $this->createProvider()->getShowcases();

        $this->assertSame(
            ['label.gallery_showcase_articles_slider', 'label.gallery_showcase_menu_link'],
            array_keys($showcases)
        );
    }

    // Both are single-variant showcases (no meaningful style choice to compare, unlike alert/button)
    public function testBothShowcasesHaveASingleUnlabelledVariant(): void
    {
        $showcases = $this->createProvider()->getShowcases();

        $this->assertSame([''], array_keys($showcases['label.gallery_showcase_articles_slider']['variants']));
        $this->assertSame([''], array_keys($showcases['label.gallery_showcase_menu_link']['variants']));
    }

    // Both stand in for their own block kind - the gallery suppresses that kind's own regular preview
    // card once "kind" is set here, so neither shows up twice
    public function testBothShowcasesStandInForTheirOwnBlockKind(): void
    {
        $showcases = $this->createProvider()->getShowcases();

        $this->assertSame('articles_slider', $showcases['label.gallery_showcase_articles_slider']['kind']);
        $this->assertSame('menu_link', $showcases['label.gallery_showcase_menu_link']['kind']);
    }
}
