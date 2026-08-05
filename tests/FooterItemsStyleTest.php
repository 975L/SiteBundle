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

// The layout a footer menu is given from the backoffice (see Menu::STYLE_*, applied by Footer.html.twig) only holds as long as the compiled stylesheets carry the class it names
class FooterItemsStyleTest extends TestCase
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

    #[\PHPUnit\Framework\Attributes\DataProvider('stylesheetProvider')]
    public function testEachStyleRetunesTheFooterItemsTokens(string $file): void
    {
        $css = $this->stylesheet($file);

        // Retuning the two tokens rather than writing flex-direction itself is what keeps one class enough: the "footer .menu-items" rule reads them, and the ".blocks" child inherits them. Declared on the element itself, they also beat whatever the theme left on :root, which is only ever inherited
        $this->assertStringContainsString(
            'footer .menu-items--inline{--footer-items-direction:row;--footer-items-justify:center',
            $css,
            sprintf('"%s" no longer inlines a footer menu picked as such in the backoffice.', $file)
        );

        $this->assertStringContainsString(
            'footer .menu-items--block{--footer-items-direction:column;--footer-items-justify:flex-start',
            $css,
            sprintf('"%s" no longer stacks a footer menu picked as such in the backoffice.', $file)
        );

        // Both classes would be dead weight without the rule reading them
        $this->assertStringContainsString('flex-direction:var(--footer-items-direction)', $css);
        $this->assertStringContainsString('justify-content:var(--footer-items-justify)', $css);
    }

    // The markup side: the class the rules above are written against, and the whitelist keeping anything else from ever naming one of them
    public function testTheTemplateOnlyWritesAClassForAKnownStyle(): void
    {
        $template = (string) file_get_contents(dirname(__DIR__) . '/templates/components/General/Footer.html.twig');

        $this->assertStringContainsString("menu_style('footer') in ['inline', 'block']", $template);
        $this->assertStringContainsString("class=\"menu-items{{ footerStyle ? ' menu-items--' ~ footerStyle : '' }}\"", $template);
    }

    // Strips comments and collapses whitespace, so the same assertions hold on the minified sheet
    private function stylesheet(string $file): string
    {
        $path = dirname(__DIR__) . '/public/css/' . $file;
        $this->assertFileExists($path, sprintf('"%s" is missing, the sass has not been compiled.', $file));

        $css = (string) preg_replace('#/\*.*?\*/#s', '', (string) file_get_contents($path));

        return (string) preg_replace('/\s*([{};:,])\s*/', '$1', $css);
    }
}
