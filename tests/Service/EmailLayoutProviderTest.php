<?php
/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\SiteBundle\Tests\Service;

use c975L\SiteBundle\Service\EmailLayoutProvider;
use PHPUnit\Framework\TestCase;
use Twig\Environment;

class EmailLayoutProviderTest extends TestCase
{
    // The body is passed through unmodified as "bodyHtml" to the bundle's own branded email layout
    public function testWrapRendersEmailTemplateLayoutWithBodyHtml(): void
    {
        // c975l/ui-bundle's tagged releases (currently v1.9.1) don't have EmailLayoutProviderInterface yet -
        // it only exists in the sibling UiBundle repo's uncommitted work. Remove this skip once a release
        // containing it is required here.
        if (!interface_exists(\c975L\UiBundle\Contract\EmailLayoutProviderInterface::class)) {
            $this->markTestSkipped('c975l/ui-bundle has no EmailLayoutProviderInterface yet');
        }

        $twig = $this->createMock(Environment::class);
        $twig->expects($this->once())
            ->method('render')
            ->with('@c975LSite/emails/emailTemplateLayout.html.twig', ['bodyHtml' => '<p>Hello</p>'])
            ->willReturn('<html><body><p>Hello</p></body></html>');

        $this->assertSame(
            '<html><body><p>Hello</p></body></html>',
            (new EmailLayoutProvider($twig))->wrap('<p>Hello</p>')
        );
    }
}
