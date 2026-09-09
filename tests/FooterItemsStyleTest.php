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

        // The third token goes with the pair: a group's flex basis sits on the main axis, so the width a theme gives it in a row footer becomes a height once the footer stacks
        $this->assertStringContainsString(
            'footer .menu-items--block{--footer-items-direction:column;--footer-items-justify:flex-start;--footer-group-flex:0 0 auto',
            $css,
            sprintf('"%s" no longer stacks a footer menu picked as such in the backoffice.', $file)
        );

        // The third style retunes the pair too: its grid takes the whole band whatever the justification, but the fallback copyright line sits beside it as a flex item of its own
        $this->assertStringContainsString(
            'footer .menu-items--columns{--footer-items-direction:row;--footer-items-justify:center',
            $css,
            sprintf('"%s" no longer turns the wrapper of a columns footer into a row, and its fr tracks have no width to share.', $file)
        );

        // All three classes would be dead weight without the rule reading them
        $this->assertStringContainsString('flex-direction:var(--footer-items-direction)', $css);
        $this->assertStringContainsString('justify-content:var(--footer-items-justify)', $css);
    }

    // The third style writes a grid on top of that retuning, its first column stacking the blocks placed before the first group - so what has to hold is the grid itself, and the span leaving that column to them
    #[\PHPUnit\Framework\Attributes\DataProvider('stylesheetProvider')]
    public function testTheColumnsStyleLaysTheFooterOutAsAGrid(string $file): void
    {
        $css = $this->stylesheet($file);

        // Mobile first: the stack is the base and the grid only comes with the breakpoint, so the media query is part of what is asserted - without it the same string would match a grid written for phones too
        $this->assertStringContainsString(
            '(min-width:768px){footer .menu-items--columns .blocks{display:grid',
            $css,
            sprintf('"%s" no longer lays out a footer menu picked as columns from the breakpoint up.', $file)
        );

        // The base below it, where four tracks would leave a column per word - the band it takes is the same at both widths, a flex item sized on its own content being as narrow stacked as it is beside fr tracks
        $this->assertStringContainsString(
            'footer .menu-items--columns .blocks{flex:1 1 100%;flex-direction:column;--footer-group-flex:0 0 auto',
            $css,
            sprintf('"%s" no longer stacks a columns footer below the breakpoint.', $file)
        );

        // Filled top to bottom, which is what stacks the name and the social links in one column instead of running them along the first row
        $this->assertStringContainsString('grid-auto-flow:column', $css);

        // Both track lists read through a token, so a footer counting another number of groups is retuned from the site's theme rather than from here
        $this->assertStringContainsString('grid-template-columns:var(--footer-grid-columns,1.6fr repeat(3,1fr))', $css);
        $this->assertStringContainsString('grid-template-rows:var(--footer-grid-rows,auto auto)', $css);

        // Without the span every group would take a row of the first column and push the next one down, which is the whole point of the style
        $this->assertStringContainsString('grid-row:1/-1', $css);

        // Centered, a column of links of uneven lengths reads as a ragged block rather than as one of the aligned columns the style is for
        $this->assertStringContainsString(
            'footer .menu-items--columns .blocks-group--column{align-items:flex-start',
            $css
        );
    }

    // The markup side: the class the rules above are written against, and the whitelist keeping anything else from ever naming one of them
    public function testTheTemplateOnlyWritesAClassForAKnownStyle(): void
    {
        $template = (string) file_get_contents(dirname(__DIR__) . '/templates/components/General/Footer.html.twig');

        $this->assertStringContainsString("menu_style('footer') in ['inline', 'block', 'columns']", $template);
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
