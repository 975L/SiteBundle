<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\SiteBundle\Tests;

use Doctrine\Common\Collections\ArrayCollection;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Twig\Environment;
use Twig\Loader\ArrayLoader;
use Twig\TwigFunction;

// A link for guests or members only is written for everyone, the menu's html being cached once and shared, and hidden by the class the layout sets on <body> - see MenuLinkTypeTest for the field itself
class MenuLinkVisibilityTest extends TestCase
{
    /**
     * @return array<string, array{string}>
     */
    public static function stylesheetProvider(): array
    {
        return [
            'styles.css' => ['styles.css'],
            'styles.min.css' => ['styles.min.css'],
        ];
    }

    /**
     * @return array<string, array{?string, string}>
     */
    public static function visibilityProvider(): array
    {
        return [
            'unset' => [null, '<div class="menu-item">'],
            'all' => ['all', '<div class="menu-item">'],
            'guests' => ['guests', '<div class="menu-item menu-item--guests">'],
            'members' => ['members', '<div class="menu-item menu-item--members">'],
            'unknown value' => ['<script>', '<div class="menu-item">'],
        ];
    }

    // The marker class, and nothing else, depends on the value: a stored value outside the three never reaches the attribute
    #[DataProvider('visibilityProvider')]
    public function testTheTemplateWritesTheMarkerClass(?string $visibility, string $expected): void
    {
        $twig = new Environment(new ArrayLoader(['link' => $this->file('templates/blocks/MenuLink.html.twig')]));
        $twig->addFunction(new TwigFunction('menu_link_url', static fn (): string => '/login'));
        $twig->addFunction(new TwigFunction('menu_link_label', static fn (): string => 'Connexion'));

        $html = $twig->render('link', array_filter(['target' => 'route:app_login', 'visibility' => $visibility]));

        $this->assertStringContainsString($expected, $html);
    }

    // The layout says which half the visitor is in, appended with its separator like the navbar's own class
    public function testTheLayoutSetsTheBodyClassFromTheSecurityState(): void
    {
        $this->assertStringContainsString(
            "{% set bodyClasses = bodyClasses|default('') ~ (app.user ? 'is-member ' : 'is-guest ') %}",
            $this->file('templates/layout.html.twig'),
            'The layout no longer writes "is-member"/"is-guest" on <body>, so a guests or members only link shows to everyone.'
        );
    }

    // "display: none" hides it from screen readers too, and "body" keeps the rule above the mobile dropdown's ".menu .menu-item"
    #[DataProvider('stylesheetProvider')]
    public function testTheStylesheetHidesEachLinkFromTheOtherHalf(string $file): void
    {
        $css = $this->file('public/css/' . $file);

        $this->assertMatchesRegularExpression(
            '/body\.is-guest \.menu-item--members,\s*body\.is-member \.menu-item--guests\s*\{\s*display:\s*none[;}]/',
            $css,
            sprintf('"%s" no longer hides a guests/members only menu link, recompile sass/styles.scss.', $file)
        );
    }

    /**
     * @return array<string, array{string}>
     */
    public static function emailTemplateProvider(): array
    {
        return [
            'header' => ['templates/emails/header.html.twig'],
            'footer' => ['templates/emails/footer.html.twig'],
        ];
    }

    // An email has no visitor and no body class to hide a link with, so a guests/members link is left out rather than sent to everyone
    #[DataProvider('emailTemplateProvider')]
    public function testAnEmailLeavesOutARestrictedLink(string $template): void
    {
        $blocks = new ArrayCollection([
            ['data' => ['label' => 'Home']],
            ['data' => ['label' => 'Everyone', 'visibility' => 'all']],
            ['data' => ['label' => 'Sign in', 'visibility' => 'guests']],
            ['data' => ['label' => 'Account', 'visibility' => 'members']],
        ]);
        $twig = new Environment(new ArrayLoader(['email' => $this->file($template)]));
        $twig->addFunction(new TwigFunction('menu_blocks', static fn (): ArrayCollection => $blocks));
        $twig->addFunction(new TwigFunction('render_block', static fn (array $block): string => '[' . $block['data']['label'] . ']'));

        $html = $twig->render('email');

        $this->assertStringContainsString('[Home]', $html);
        $this->assertStringContainsString('[Everyone]', $html);
        $this->assertStringNotContainsString('[Sign in]', $html);
        $this->assertStringNotContainsString('[Account]', $html);
    }

    // A footer whose links are all restricted renders nothing, not an empty list
    public function testAnEmailFooterOfRestrictedLinksOnlyIsNotRendered(): void
    {
        $twig = new Environment(new ArrayLoader(['email' => $this->file('templates/emails/footer.html.twig')]));
        $twig->addFunction(new TwigFunction('menu_blocks', static fn (): ArrayCollection => new ArrayCollection([['data' => ['visibility' => 'members']]])));
        $twig->addFunction(new TwigFunction('render_block', static fn (): string => ''));

        $this->assertStringNotContainsString('email-footer', $twig->render('email'));
    }

    private function file(string $path): string
    {
        $path = \dirname(__DIR__) . '/' . $path;
        $this->assertFileExists($path);

        return (string) file_get_contents($path);
    }
}
