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

// Reading app.flashes starts a session, so the layout only reads it for a visitor already carrying the session cookie or for a request that started the session itself, which keeps anonymous pages cacheable
class FlashesSessionGuardTest extends TestCase
{
    public function testTheLayoutGuardsOnBothTermsBeforeReadingTheFlashes(): void
    {
        $layout = $this->layout();

        foreach (['app.request.hasPreviousSession', 'app.session.started'] as $term) {
            $this->assertStringContainsString(
                $term,
                $layout,
                sprintf('The layout no longer checks "%s", so a session is opened again for every anonymous visitor and every crawler.', $term)
            );
        }
    }

    // The block sits inside the guard: moved out of it, the flashes would be read unconditionally again
    public function testTheFlashesBlockStaysInsideTheGuard(): void
    {
        $layout = $this->layout();

        $guard = strpos($layout, 'app.request.hasPreviousSession');
        $this->assertNotFalse($guard, 'The layout no longer carries the session guard.');

        $block = strpos($layout, '{% block flashes %}');
        $this->assertNotFalse($block, 'The layout no longer prints the flashes block.');
        $this->assertLessThan($block, $guard, 'The flashes block is printed before the session guard, so reading app.flashes opens a session for every visitor.');

        $read = strpos($layout, '{% for label, messages in app.flashes %}');
        $this->assertNotFalse($read, 'The layout no longer reads app.flashes.');
        $this->assertLessThan($read, $block, 'The flashes are read outside the guarded block.');
    }

    private function layout(): string
    {
        $path = \dirname(__DIR__) . '/templates/layout.html.twig';
        $this->assertFileExists($path);

        return (string) file_get_contents($path);
    }
}
