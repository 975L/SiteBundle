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
use Symfony\Component\Finder\Finder;

// What an editor or an admin writes never reaches a page or an e-mail as it was typed
class RawOutputTest extends TestCase
{
    // What may stay raw: two fragments Twig has already rendered and escaped - the JSON-LD is a {% set %} capture, safe without it
    private const array RAW_ALLOWED = ['detailHtml|raw', 'bodyHtml|raw'];

    public function testNoTemplatePrintsAnythingElseRaw(): void
    {
        $raw = [];
        foreach (new Finder()->files()->in([\dirname(__DIR__) . '/templates', \dirname(__DIR__) . '/scaffold'])->name('*.twig') as $file) {
            $source = (string) preg_replace('/\{#.*?#\}/s', '', $file->getContents());
            preg_match_all('/[\w.\']+(?:\([^)]*\))?(?:\|\w+(?:\([^)]*\))?)*\|raw\b/', $source, $matches);
            // Written in a form the pattern cannot name, "(a ~ b)|raw" for one: refused as well rather than passing unread
            $this->assertSame(preg_match_all('/\|\s*raw\b/', $source), \count($matches[0]), $file->getRelativePathname());
            foreach (array_diff($matches[0], self::RAW_ALLOWED) as $expression) {
                $raw[] = $file->getRelativePathname() . ': ' . $expression;
            }
        }

        $this->assertSame([], $raw);
    }
}
