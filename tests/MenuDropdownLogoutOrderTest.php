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
use Twig\Environment;
use Twig\Loader\ArrayLoader;
use Twig\TwigFilter;
use Twig\TwigFunction;

// A site's own members' dropdown ends with the back-office link then the sign-out, whatever the position of the latter in the back office - the order of the built-in AccountMenu.html.twig
class MenuDropdownLogoutOrderTest extends TestCase
{
    // The sign-out, put first in the back office, is drawn after the back-office link, the other links keeping their own order
    public function testTheSignOutComesLastAfterTheBackOfficeLink(): void
    {
        $html = $this->render('members', ['route:app_logout', 'route:user_shortcuts', 'route:config_account']);

        $this->assertSame(['route:user_shortcuts', 'route:config_account', 'management', 'route:app_logout'], $this->entries($html));
    }

    // A dropdown left to everyone has no back-office link, the sign-out still ending it
    public function testTheSignOutComesLastInAnyDropdown(): void
    {
        $html = $this->render('all', ['route:app_logout', 'page:1']);

        $this->assertSame(['page:1', 'route:app_logout'], $this->entries($html));
    }

    // Renders the dropdown template with stubbed Twig helpers, one slot per target
    /** @param list<string> $targets */
    private function render(string $visibility, array $targets): string
    {
        $twig = new Environment(new ArrayLoader(['dropdown' => (string) file_get_contents(\dirname(__DIR__) . '/templates/blocks/MenuDropdown.html.twig')]));
        $twig->addFunction(new TwigFunction('render_block', static fn (array $slot): string => '[' . $slot['data']['target'] . ']', ['is_safe' => ['html']]));
        $twig->addFunction(new TwigFunction('path', static fn (string $route): string => '/' . $route));
        $twig->addFilter(new TwigFilter('trans', static fn (string $key): string => $key));

        $slots = array_map(static fn (string $target): array => ['kind' => 'menu_link', 'data' => ['target' => $target]], $targets);

        return $twig->render('dropdown', ['label' => 'Mon espace', 'visibility' => $visibility, 'block' => ['slots' => $slots]]);
    }

    // The rendered slots and the back-office link, in the order they are drawn
    /** @return list<string> */
    private function entries(string $html): array
    {
        preg_match_all('#\[([^\]]+)\]|href="/(management)"#', $html, $matches);

        return array_values(array_map(static fn (string $slot, string $link): string => '' !== $slot ? $slot : $link, $matches[1], $matches[2]));
    }
}
