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

// An email's footer links read like the "layout_sent_by" line they sit next to - one centered inline row, grey, small and undecorated - not like the page's colored footer band, whose --footer-text is white on the white background an email keeps
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

    // The "strong" checkbox of a menu_link is honoured by _menu.scss, which emails.scss doesn't compile - so the rule is restated here, or the box stays inert on a link sitting in the "email-footer" Menu
    #[DataProvider('stylesheetProvider')]
    public function testAStrongLinkIsBolded(string $file): void
    {
        $this->assertMatchesRegularExpression(
            '/\.email-footer \.menu-item--strong \.menu-label\s*\{[^}]*font-weight:\s*bold[;}]/',
            $this->stylesheet($file),
            sprintf('"%s" does not bold a "strong" menu link, whose checkbox then does nothing in an email footer.', $file)
        );
    }

    // The markup itself: a centered inline list, the shape the rules above are written against
    public function testTheTemplateRendersOneCenteredInlineList(): void
    {
        $template = (string) file_get_contents(__DIR__ . '/../templates/emails/footer.html.twig');

        $this->assertStringContainsString('class="email-footer text-center"', $template);
        $this->assertStringContainsString('<ul class="inline">', $template);
        $this->assertStringContainsString('<li>{{ render_block(block) }}</li>', $template);
    }

    // Who sends this e-mail is owed by every commercial document, and "site-owner" is the one place a site writes it
    public function testTheIdentificationIsReadFromTheSiteOwner(): void
    {
        $this->assertMatchesRegularExpression(
            "/\\{% if config\\('site-owner'\\) %\\}.*?config\\('site-owner'\\)\\|raw.*?\\{% endif %\\}/s",
            $this->layout(),
            'The e-mail footer no longer prints "site-owner", so every e-mail leaves identifying nobody.'
        );
    }

    // Article 13 asks the information to be reachable, not repeated in every e-mail - and a link to a page the admin never named would be a dead one
    public function testThePrivacyLinkIsGuardedByItsSetting(): void
    {
        $this->assertMatchesRegularExpression(
            "/\\{% if config\\('url-privacy-policy'\\) %\\}.*?label\\.privacy_policy.*?\\{% endif %\\}/s",
            $this->layout(),
            'The privacy policy link is no longer behind "url-privacy-policy", so a site that never filled it in shows a dead link.'
        );
    }

    // A transactional e-mail owes no way out; one that is prospection does (article L34-5 CPCE), and places its own in this hook
    public function testTheUnsubscribeHookIsThereAndEmptyByDefault(): void
    {
        $this->assertStringContainsString(
            '{% block emailUnsubscribe %}{% endblock %}',
            $this->layout(),
            'The e-mail layout lost the hook a prospection e-mail puts its way out in.'
        );
    }

    // The Menu an admin builds is a plus: it is rendered on top of the mandatory blocks, never instead of them
    public function testTheBackOfficeMenuComesBeforeWhatTheLawAsksFor(): void
    {
        $layout = $this->layout();

        $this->assertLessThan(
            strpos($layout, "config('site-owner')"),
            strpos($layout, "include '@c975LSite/emails/footer.html.twig'"),
            'The admin-built menu no longer sits above the identification, which must close the footer whatever the admin put there.'
        );
    }

    // The reader is told who writes to them before being pointed at what is done with their data, which is the order the README, UPGRADE and skill all state
    public function testTheIdentificationComesBeforeThePrivacyLink(): void
    {
        $layout = $this->layout();

        $this->assertLessThan(
            strpos($layout, "config('url-privacy-policy')"),
            strpos($layout, "config('site-owner')"),
            'The privacy link moved above the identification, which the documentation states comes first.'
        );
    }

    private function layout(): string
    {
        return (string) file_get_contents(__DIR__ . '/../templates/emails/fullLayout.html.twig');
    }

    private function stylesheet(string $file): string
    {
        $path = __DIR__ . '/../public/css/' . $file;
        $this->assertFileExists($path, sprintf('"%s" is missing, recompile sass/emails.scss.', $file));

        return (string) file_get_contents($path);
    }
}
