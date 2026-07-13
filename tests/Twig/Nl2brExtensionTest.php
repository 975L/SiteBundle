<?php
/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\SiteBundle\Tests\Twig;

use c975L\SiteBundle\Twig\Nl2brExtension;
use PHPUnit\Framework\TestCase;

class Nl2brExtensionTest extends TestCase
{
    // Newlines are converted to XHTML <br /> tags, without a trailing backslash (is_xhtml=false)
    public function testNl2brConvertsNewlinesToBrTags(): void
    {
        $this->assertSame("Line 1<br>\nLine 2", Nl2brExtension::nl2br("Line 1\nLine 2"));
    }

    // A null input (e.g. an empty Twig variable) must not trigger a deprecation, and yields an empty string
    public function testNl2brHandlesNullGracefully(): void
    {
        $this->assertSame('', Nl2brExtension::nl2br(null));
    }
}
