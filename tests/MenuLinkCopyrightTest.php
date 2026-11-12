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

// The "©" of a copyright menu link is the footer's edit shortcut, the "trigger" target of assets/js/edit-shortcut.js - see EditShortcutBehaviourTest
class MenuLinkCopyrightTest extends TestCase
{
    public function testTheCopyrightSignIsWrittenOffTheLink(): void
    {
        $template = (string) file_get_contents(\dirname(__DIR__) . '/templates/blocks/MenuLink.html.twig');

        $this->assertStringContainsString("resolvedLabel starts with '© '", $template);
        $this->assertStringContainsString("isCopyright ? 'menu-item--copyright' : null", $template);
        $this->assertMatchesRegularExpression(
            '#<span class="menu-label" data-edit-shortcut-target="trigger">©</span>\s*<a href="\{\{ url \}\}" class="menu-link">\s*<span class="menu-label">\{\{ resolvedLabel\|slice\(2\) \}\}</span>#',
            $template,
            'The "©" of the copyright notice is back inside the link or lost its "trigger" target, so three clicks on it no longer lead to the login form.'
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

    // The footer prints the notice itself whenever the copyright link renders nothing: hidden, or its page unpublished - otherwise the site loses both its mention and its edit shortcut
    #[DataProvider('silentCopyrightLinkProvider')]
    public function testTheFooterPrintsTheNoticeWhenTheCopyrightLinkRendersNothing(bool $hidden, string $url, string $expected): void
    {
        $template = (string) file_get_contents(\dirname(__DIR__) . '/templates/components/General/Footer.html.twig');
        $this->assertSame(1, preg_match('/\{% set footerItems .*?\|length > 0 %\}/s', $template, $match));

        $twig = new Environment(new ArrayLoader(['footer' => $match[0] . "{{ hasCopyrightLink ? 'link' : 'notice' }}"]));
        $twig->addFunction(new TwigFunction('menu_link_is_copyright', static fn (): bool => true));
        $twig->addFunction(new TwigFunction('menu_link_url', static fn (): string => $url));
        $block = ['hidden' => $hidden, 'data' => ['target' => 'page:42'], 'slots' => []];

        $this->assertSame($expected, $twig->render('footer', ['footerBlocks' => [$block]]));
    }

    /** @return array<string, array{bool, string, string}> */
    public static function silentCopyrightLinkProvider(): array
    {
        return [
            'shown' => [false, '/pages/copyright', 'link'],
            'hidden' => [true, '/pages/copyright', 'notice'],
            'page unpublished' => [false, '', 'notice'],
        ];
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
