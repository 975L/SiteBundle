<?php
/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\SiteBundle\Tests\Twig;

use c975L\SiteBundle\Twig\LinkifyExtension;
use PHPUnit\Framework\TestCase;

class LinkifyExtensionTest extends TestCase
{
    // Plain text with no URL is only HTML-escaped, nothing to linkify
    public function testLinkifyEscapesPlainTextWithoutUrl(): void
    {
        $this->assertSame(
            'Tom &amp; Jerry &lt;3',
            LinkifyExtension::linkify('Tom & Jerry <3')
        );
    }

    // A bare URL becomes a safe, new-tab link, both visible text and href identical to the source URL
    public function testLinkifyWrapsABareUrlInAnAnchor(): void
    {
        $this->assertSame(
            '<a href="https://example.com/path" target="_blank" rel="noopener noreferrer">https://example.com/path</a>',
            LinkifyExtension::linkify('https://example.com/path')
        );
    }

    // Surrounding text stays outside the anchor and is still HTML-escaped
    public function testLinkifyKeepsSurroundingTextEscapedOutsideTheAnchor(): void
    {
        $this->assertSame(
            'See <a href="https://example.com" target="_blank" rel="noopener noreferrer">https://example.com</a> &amp; enjoy',
            LinkifyExtension::linkify('See https://example.com & enjoy')
        );
    }

    // End-of-sentence punctuation right after a URL belongs to the surrounding text, not the link
    public function testLinkifyExcludesTrailingPunctuationFromTheUrl(): void
    {
        $this->assertSame(
            'Go to <a href="https://example.com" target="_blank" rel="noopener noreferrer">https://example.com</a>.',
            LinkifyExtension::linkify('Go to https://example.com.')
        );
    }

    // A quote right after a URL (e.g. quoting a link in prose) also stops the match
    public function testLinkifyStopsAtAQuoteRightAfterTheUrl(): void
    {
        $this->assertSame(
            'He said &quot;<a href="https://example.com" target="_blank" rel="noopener noreferrer">https://example.com</a>&quot; was down',
            LinkifyExtension::linkify('He said "https://example.com" was down')
        );
    }

    // Every URL in the string is linkified independently
    public function testLinkifyHandlesMultipleUrls(): void
    {
        $html = LinkifyExtension::linkify('https://one.example and https://two.example');

        $this->assertSame(
            '<a href="https://one.example" target="_blank" rel="noopener noreferrer">https://one.example</a>'
            . ' and <a href="https://two.example" target="_blank" rel="noopener noreferrer">https://two.example</a>',
            $html
        );
    }

    // A null input (e.g. an empty Twig variable) must not trigger a deprecation, and yields an empty string
    public function testLinkifyHandlesNullGracefully(): void
    {
        $this->assertSame('', LinkifyExtension::linkify(null));
    }
}
