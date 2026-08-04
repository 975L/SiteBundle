<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\SiteBundle\Tests;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

// An email's footer links read like the "email-text-sent-by" line they sit next to - one centered inline row, grey, small and undecorated - not like the page's colored footer band, whose --footer-text is white on the white background an email keeps
class EmailFooterTest extends TestCase
{
    /**
     * @return array<string, array{string}>
     */
    public static function stylesheetProvider(): array
    {
        return [
            'emails.css' => ['emails.css'],
            'emails.min.css' => ['emails.min.css'],
        ];
    }

    // Each block is wrapped in its own div by render_block(), so the list items alone wouldn't keep them on one line
    #[DataProvider('stylesheetProvider')]
    public function testTheItemsStayOnOneRow(string $file): void
    {
        $this->assertMatchesRegularExpression(
            '/\.email-footer \.menu-item\s*\{[^}]*display:\s*inline[;}]/',
            $this->stylesheet($file),
            sprintf('"%s" no longer keeps the email footer items inline, so each link stacks on a line of its own.', $file)
        );
    }

    #[DataProvider('stylesheetProvider')]
    public function testTheLinksAreMutedSmallAndUndecorated(string $file): void
    {
        $css = $this->stylesheet($file);

        foreach (['color:\s*var\(--label-color\)', 'font-size:\s*90%', 'text-decoration:\s*none'] as $declaration) {
            $this->assertMatchesRegularExpression(
                sprintf('/\.email-footer a[^{]*\{[^}]*%s[;}]/', $declaration),
                $css,
                sprintf('"%s" lost "%s" on the email footer links, which then no longer match the "sent by" line.', $file, $declaration)
            );
        }
    }

    // The markup itself: a centered inline list, the shape the rules above are written against
    public function testTheTemplateRendersOneCenteredInlineList(): void
    {
        $template = (string) file_get_contents(__DIR__ . '/../templates/emails/footer.html.twig');

        $this->assertStringContainsString('class="email-footer text-center"', $template);
        $this->assertStringContainsString('<ul class="inline">', $template);
        $this->assertStringContainsString('<li>{{ render_block(block) }}</li>', $template);
    }

    private function stylesheet(string $file): string
    {
        $path = __DIR__ . '/../public/css/' . $file;
        $this->assertFileExists($path, sprintf('"%s" is missing, recompile sass/emails.scss.', $file));

        return (string) file_get_contents($path);
    }
}
