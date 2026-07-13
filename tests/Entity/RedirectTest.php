<?php
/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\SiteBundle\Tests\Entity;

use c975L\SiteBundle\Entity\Redirect;
use PHPUnit\Framework\TestCase;

class RedirectTest extends TestCase
{
    // A path already starting with a slash is left untouched
    public function testSetFromPathKeepsLeadingSlashAsIs(): void
    {
        $redirect = (new Redirect())->setFromPath('/old-page');

        $this->assertSame('/old-page', $redirect->getFromPath());
    }

    // A path given without its leading slash gets one prepended
    public function testSetFromPathAddsMissingLeadingSlash(): void
    {
        $redirect = (new Redirect())->setFromPath('old-page');

        $this->assertSame('/old-page', $redirect->getFromPath());
    }

    // Several leading slashes are collapsed down to a single one
    public function testSetFromPathCollapsesRepeatedLeadingSlashes(): void
    {
        $redirect = (new Redirect())->setFromPath('///old-page');

        $this->assertSame('/old-page', $redirect->getFromPath());
    }
}
