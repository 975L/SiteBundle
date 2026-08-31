<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\SiteBundle\Tests\Twig;

use c975L\SiteBundle\Twig\MenuExtension;
use c975L\SiteBundle\Twig\PageExtension;
use c975L\SiteBundle\Twig\PageHealthCheckExtension;
use c975L\SiteBundle\Twig\TwigContentExtension;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Twig\Attribute\AsTwigFunction;

// The function names live in #[AsTwigFunction] attributes alone, which no other test reads: an attribute dropped or misspelled by a refactor would keep the suite green and only fail on the first page rendered
class TwigFunctionRegistrationTest extends TestCase
{
    /**
     * @return array<string, array{class-string, array<string, string>}>
     */
    public static function extensionProvider(): array
    {
        return [
            'MenuExtension' => [MenuExtension::class, [
                'menu_blocks' => 'getMenuBlocks',
                'menu_link_is_copyright' => 'isMenuLinkCopyright',
                'menu_link_label' => 'getMenuLinkLabel',
                'menu_link_url' => 'getMenuLinkUrl',
                'menu_style' => 'getMenuStyle',
            ]],
            'PageExtension' => [PageExtension::class, [
                'site_legal_pages' => 'getLegalPages',
                'site_page' => 'getPage',
                'site_page_for_form_block' => 'getPageForFormBlock',
            ]],
            'PageHealthCheckExtension' => [PageHealthCheckExtension::class, [
                'page_health_check' => 'getPanel',
            ]],
            'TwigContentExtension' => [TwigContentExtension::class, [
                'site_twig_content_allowed' => 'isAllowed',
            ]],
        ];
    }

    /**
     * @param class-string          $class
     * @param array<string, string> $expected
     */
    #[DataProvider('extensionProvider')]
    public function testTheExtensionRegistersItsFunctions(string $class, array $expected): void
    {
        $registered = [];
        foreach (new \ReflectionClass($class)->getMethods() as $method) {
            foreach ($method->getAttributes(AsTwigFunction::class) as $attribute) {
                $registered[$attribute->newInstance()->name] = $method->getName();
            }
        }
        ksort($registered);

        $this->assertSame($expected, $registered);
    }
}
