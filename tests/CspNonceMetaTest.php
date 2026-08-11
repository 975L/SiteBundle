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

// Turbo stamps the value of <meta name="csp-nonce"> on both the inline scripts it re-executes and its own progress bar <style>, so the nonce printed there has to be one style-src carries as well as script-src
class CspNonceMetaTest extends TestCase
{
    public function testTheLayoutAsksForBothDirectives(): void
    {
        $layout = $this->layout();

        foreach (['script', 'style'] as $directive) {
            $this->assertStringContainsString(
                sprintf("csp_nonce('%s')", $directive),
                $layout,
                sprintf('The layout no longer asks for a "%s" nonce, so the value printed in the meta is not one that directive lists.', $directive)
            );
        }
    }

    // Both calls come before the meta: NelmioSecurityBundle mints one nonce per response and stamps it into whichever directive was asked, so asking twice puts the same string in both
    public function testBothAreAskedBeforeTheMetaIsPrinted(): void
    {
        $layout = $this->layout();

        $meta = strpos($layout, '<meta name="csp-nonce"');
        $this->assertNotFalse($meta, 'The layout no longer prints the csp-nonce meta, which Turbo reads to nonce what it re-executes.');

        foreach (['script', 'style'] as $directive) {
            $call = strpos($layout, sprintf("set cspNonce = csp_nonce('%s')", $directive));

            $this->assertNotFalse($call, sprintf('The "%s" nonce is no longer assigned before the meta.', $directive));
            $this->assertLessThan($meta, $call, sprintf('The "%s" nonce is asked for after the meta was printed, so that directive never lists the value Turbo reads.', $directive));
        }
    }

    // The meta prints the assigned value rather than a fresh call, which would leave the second directive out again
    public function testTheMetaPrintsTheAssignedValue(): void
    {
        $this->assertStringContainsString(
            '<meta name="csp-nonce" content="{{ cspNonce }}">',
            $this->layout(),
            'The csp-nonce meta no longer prints the value both directives were asked for.'
        );
    }

    private function layout(): string
    {
        $path = \dirname(__DIR__) . '/templates/layout.html.twig';
        $this->assertFileExists($path);

        return (string) file_get_contents($path);
    }
}
