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
use PHPUnit\Framework\TestCase;
use Symfony\Contracts\Translation\TranslatorInterface;
use Twig\Environment;
use Twig\TemplateWrapper;

class GalleryShowcaseProviderTest extends TestCase
{
    private function createProvider(): GalleryShowcaseProvider
    {
        $twig = $this->createMock(Environment::class);
        $twig->method('render')->willReturnCallback(
            static fn (string $template, array $context) => "<!-- {$template} -->"
        );
        $wrapper = $this->createStub(TemplateWrapper::class);
        $wrapper->method('render')->willReturnCallback(static fn (array $context) => "<a>{$context['label']}</a>");
        $twig->method('createTemplate')->willReturn($wrapper);

        $translator = $this->createStub(TranslatorInterface::class);
        $translator->method('trans')->willReturnCallback(static fn (string $id) => $id);

        return new GalleryShowcaseProvider($twig, $translator);
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
