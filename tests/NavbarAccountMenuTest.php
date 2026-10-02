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

// The signed-in visitor's links: a built-in "My space" for a site whose navbar has none, the back-office link added to the one a site built itself
class NavbarAccountMenuTest extends TestCase
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

    // Rendered on every request outside the cached menu, so it reads the security state itself and writes nothing for a guest
    public function testTheMenuIsWrittenForASignedInVisitorOnly(): void
    {
        $this->assertStringStartsWith('{% if app.user %}', $this->stripLeadingComment($this->template('components/General/AccountMenu.html.twig')));
    }

    // The back-office link follows the voter, which lets in the contributor, editor and admin bars and ROLE_SUPER_ADMIN alike
    public function testTheBackOfficeLinkFollowsTheBackOfficeVoter(): void
    {
        $source = $this->template('components/General/AccountMenu.html.twig');

        $this->assertStringContainsString("is_granted('C975L_ACCESS_BACK_OFFICE') ? {url: path('management')", $source);
        $this->assertStringContainsString("path('config_account')", $source);
    }

    // Signing out is no page for a crawler to follow
    public function testTheSignOutLinkCarriesNofollow(): void
    {
        $this->assertStringContainsString("{url: logout_path(), label: 'label.logout'|trans({}, 'site'), rel: 'nofollow'}", $this->template('components/General/AccountMenu.html.twig'));
    }

    // Both bars carry it, the menu bar as a dropdown, and a site that built its own members' dropdown keeps that one alone
    public function testTheNavbarSkipsTheBuiltInMenuWhenTheSiteHasItsOwn(): void
    {
        $source = $this->template('components/General/Navbar.html.twig');

        $this->assertStringContainsString("block.kind == 'menu_dropdown' and not block.hidden and (block.data.visibility ?? 'all') == 'members'", $source);
        $this->assertStringContainsString("{% if not hasMembersDropdown %}\n                <twig:c975LSite:General:AccountMenu :dropdown=\"true\" />", $source);
        $this->assertSame(2, substr_count($source, '<twig:c975LSite:General:AccountMenu'));
    }

    // A members' dropdown is cached once for everyone: the back-office link is written for all of them and the body class decides
    public function testAMembersDropdownCarriesTheBackOfficeLink(): void
    {
        $source = $this->template('blocks/MenuDropdown.html.twig');

        $this->assertStringContainsString("{% if visibility|default('all') == 'members' %}\n                <div class=\"menu-item menu-item--back-office\">", $source);
        $this->assertStringNotContainsString('is_granted', $source);
    }

    // The layout answers who may enter the back office, appended like the member/guest class
    public function testTheLayoutMarksWhoeverMayEnterTheBackOffice(): void
    {
        $this->assertStringContainsString("{% if is_granted('C975L_ACCESS_BACK_OFFICE') %}\n    {% set bodyClasses = bodyClasses ~ 'has-back-office ' %}", $this->template('layout.html.twig'));
    }

    // Hidden unless the body says otherwise, so a member with no back-office role never sees it
    #[DataProvider('stylesheetProvider')]
    public function testTheBackOfficeLinkIsHiddenWithoutTheBodyClass(string $stylesheet): void
    {
        $css = (string) file_get_contents(dirname(__DIR__) . '/public/css/' . $stylesheet);

        $this->assertMatchesRegularExpression('/body:not\(\.has-back-office\)\s*\.menu-item--back-office\s*\{\s*display:\s*none/', $css);
    }

    private function template(string $name): string
    {
        $path = dirname(__DIR__) . '/templates/' . $name;
        $this->assertFileExists($path);

        return (string) file_get_contents($path);
    }

    private function stripLeadingComment(string $source): string
    {
        return ltrim((string) preg_replace('/^\{#.*?#\}/s', '', $source));
    }
}
