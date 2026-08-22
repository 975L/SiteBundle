<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\SiteBundle\Tests;

use PHPUnit\Framework\TestCase;
use Twig\Environment;
use Twig\Loader\ArrayLoader;

// Only the four tinted variants carry a background, so a flash labelled anything else would print black ink on the dark page's own background - the layout maps the labels other bundles emit onto the nearest tinted one
class FlashVariantTest extends TestCase
{
    // "error" is what ConfigShortcutController emits beside its own "danger", so both have to reach the same tint
    public function testAnErrorLabelIsPrintedAsTheDangerVariant(): void
    {
        $this->assertStringContainsString('alert-danger', $this->renderFlashesBlock(['error' => ['Something went wrong']]));
    }

    public function testANoticeLabelIsPrintedAsTheInfoVariant(): void
    {
        $this->assertStringContainsString('alert-info', $this->renderFlashesBlock(['notice' => ['Just so you know']]));
    }

    // The four tinted ones travel untouched, the map only naming what has to move
    public function testATintedLabelIsPrintedAsItself(): void
    {
        foreach (['success', 'info', 'warning', 'danger'] as $label) {
            $this->assertStringContainsString('alert-' . $label, $this->renderFlashesBlock([$label => ['A message']]));
        }
    }

    // Anything else - a label a bundle invented, one this map does not name - lands on the neutral tint rather than on no background at all
    public function testAnUnknownLabelFallsBackOnTheInfoVariant(): void
    {
        $rendered = $this->renderFlashesBlock(['primary' => ['A message']]);

        $this->assertStringContainsString('alert-info', $rendered);
        $this->assertStringNotContainsString('alert-primary', $rendered);
    }

    /**
     * The layout's own flashes block, rendered on its own - the surrounding layout pulls in the whole application (assets, nonces, config), which this behaviour doesn't depend on.
     *
     * @param array<string, list<string>> $flashes
     */
    private function renderFlashesBlock(array $flashes): string
    {
        $layout = (string) file_get_contents(dirname(__DIR__) . '/templates/layout.html.twig');

        $this->assertSame(
            1,
            preg_match('/\{% block flashes %\}(.*?)\{% endblock %\}/s', $layout, $matches),
            'The flashes block is no longer written as its own block in layout.html.twig, this test can no longer read it.'
        );

        // Only the source of the flashes is swapped, the mapping itself being what is under test
        $source = str_replace('app.flashes', 'flashes', $matches[1]);
        $twig = new Environment(new ArrayLoader(['flashes' => $source]));

        return $twig->render('flashes', ['flashes' => $flashes]);
    }
}
