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
use Twig\Environment;
use Twig\Loader\ArrayLoader;
use Twig\TwigFunction;

// The "©" of a copyright menu link is the footer's edit shortcut, which assets/js/edit-shortcut.js ignores inside a link - see EditShortcutBehaviourTest
class MenuLinkCopyrightTest extends TestCase
{
    public function testTheCopyrightSignIsWrittenOffTheLink(): void
    {
        $template = (string) file_get_contents(\dirname(__DIR__) . '/templates/blocks/MenuLink.html.twig');

        $this->assertStringContainsString("resolvedLabel starts with '© '", $template);
        $this->assertStringContainsString("isCopyright ? 'menu-item--copyright' : null", $template);
        $this->assertMatchesRegularExpression(
            '#<span class="menu-label">©</span>\s*<a href="\{\{ url \}\}" class="menu-link">\s*<span class="menu-label">\{\{ resolvedLabel\|slice\(2\) \}\}</span>#',
            $template,
            'The "©" of the copyright notice is back inside the link, so three clicks on it open the copyright page instead of the login form.'
        );
    }

    // The three ways a link carries no label of its own
    /** @return array<string, array{array<string, mixed>}> */
    public static function noOwnLabelProvider(): array
    {
        return [
            'label absent' => [[]],
            'label null' => [['label' => null]],
            'label empty' => [['label' => '']],
        ];
    }

    // A link to the Copyright page given no label of its own in the menu shows the computed "© year" notice
    #[DataProvider('noOwnLabelProvider')]
    public function testACopyrightLinkWithoutItsOwnLabelShowsTheNotice(array $data): void
    {
        $html = $this->render($data);

        $this->assertStringContainsString('menu-item--copyright', $html);
        $this->assertStringContainsString('<span class="menu-label">2020 - 2026</span>', $html);
    }

    // The same link given a label of its own in the menu shows that label, the data deciding rather than a setting
    public function testACopyrightLinkWithItsOwnLabelShowsThatLabel(): void
    {
        $html = $this->render(['label' => 'Copyright']);

        $this->assertStringNotContainsString('menu-item--copyright', $html);
        $this->assertStringNotContainsString('©', $html);
        $this->assertStringContainsString('<span class="menu-label">Copyright</span>', $html);
    }

    // Renders MenuLink.html.twig for a link to the Copyright page, its derived label being the computed notice
    private function render(array $data): string
    {
        $twig = new Environment(new ArrayLoader(['link' => (string) file_get_contents(\dirname(__DIR__) . '/templates/blocks/MenuLink.html.twig')]));
        $twig->addFunction(new TwigFunction('menu_link_url', static fn (): string => '/pages/copyright'));
        $twig->addFunction(new TwigFunction('menu_link_members_only', static fn (): bool => false));
        $twig->addFunction(new TwigFunction('menu_link_label', static fn (): string => '© 2020 - 2026'));

        return $twig->render('link', ['target' => 'page:42'] + $data);
    }
}
