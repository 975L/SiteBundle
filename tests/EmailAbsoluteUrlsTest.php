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

// An email is read outside the site: a picture or a link left root-relative resolves against nothing in a mailbox, so the layout every email is wrapped in makes them absolute rather than each template doing it for itself
class EmailAbsoluteUrlsTest extends TestCase
{
    public function testTheLayoutIsMadeAbsoluteAgainstTheSiteUrl(): void
    {
        $this->assertStringContainsString(
            "{% apply inline_css|absolute_urls(config('site-url')) %}",
            $this->layout(),
            'The email layout no longer makes its paths absolute, so every picture and link of an order email is broken once sent.'
        );
    }

    private function layout(): string
    {
        return (string) file_get_contents(__DIR__ . '/../templates/emails/fullLayout.html.twig');
    }
}
