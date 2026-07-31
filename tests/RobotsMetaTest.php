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

// An error page rendered with the site's own layout used to inherit the "index, follow" default and invite search engines to index it - a 404 offering itself to the index is the opposite of what it should say. The expression is read out of layout.html.twig itself rather than restated here, so this checks the template that actually ships and not a copy of it
class RobotsMetaTest extends TestCase
{
    // The value part of the robots meta as the layout writes it, rendered on its own - the surrounding layout pulls in the whole application (assets, nonces, config), which this behaviour doesn't depend on
    private function renderRobots(array $context): string
    {
        $layout = (string) file_get_contents(dirname(__DIR__) . '/templates/layout.html.twig');

        $this->assertSame(
            1,
            preg_match('/<meta name="robots" content="(\{\{.*?\}\})">/', $layout, $matches),
            'The robots meta tag is no longer written as a single expression in layout.html.twig, this test can no longer read it.'
        );

        $twig = new Environment(new ArrayLoader(['robots' => $matches[1]]));

        return $twig->render('robots', $context);
    }

    public function testAPageIsIndexableByDefault(): void
    {
        $this->assertSame('index, follow', $this->renderRobots([]));
    }

    // "status_code" is only ever set by Symfony's error renderer
    public function testAnErrorPageIsNotIndexable(): void
    {
        foreach ([404, 410, 500, 403] as $statusCode) {
            $this->assertSame(
                'noindex, follow',
                $this->renderRobots(['status_code' => $statusCode]),
                sprintf('A %d page offers itself to the index.', $statusCode)
            );
        }
    }

    // "follow" is kept on error pages too, so the links they carry (the menu, the home page) still pass on
    public function testAnErrorPageStillLetsItsLinksBeFollowed(): void
    {
        $this->assertStringContainsString('follow', $this->renderRobots(['status_code' => 404]));
        $this->assertStringNotContainsString('nofollow', $this->renderRobots(['status_code' => 404]));
    }

    // A non-indexable Page sets it explicitly (see pages/page.html.twig and Page::isIndexable()), which the default must not override
    public function testAnExplicitValueWins(): void
    {
        $this->assertSame('noindex, follow', $this->renderRobots(['robots' => 'noindex, follow']));
    }
}
