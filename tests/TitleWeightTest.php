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

// Everything set in the title family reads --font-title-weight rather than a weight of its own: a face uploaded in one weight only would otherwise be thickened by the browser instead of by a bolder cut of itself
class TitleWeightTest extends TestCase
{
    private const array TITLE_FAMILY_RULES = ['h1,h2,h3,h4,h5,h6', '.lead', '.nav-simple-name'];

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

    // "normal" is what a face uploaded in a single weight is drawn at, i.e. the weight it was actually designed in
    #[\PHPUnit\Framework\Attributes\DataProvider('stylesheetProvider')]
    public function testTheTokenKeepsItsDefault(string $file): void
    {
        $this->assertStringContainsString(
            '--font-title-weight:normal',
            $this->stylesheet($file),
            sprintf('"%s" does not declare the token\'s default value.', $file)
        );
    }

    // The headings, the lead line and the fallback navbar's name are the three rules set in that family, and they move together
    #[\PHPUnit\Framework\Attributes\DataProvider('stylesheetProvider')]
    public function testEveryTitleFamilyRuleReadsTheToken(string $file): void
    {
        $css = $this->stylesheet($file);

        foreach (self::TITLE_FAMILY_RULES as $rule) {
            $this->assertMatchesRegularExpression(
                '/(?:^|[{},])' . preg_quote($rule, '/') . '\{[^}]*font-weight:var\(--font-title-weight\)/',
                $css,
                sprintf('"%s" sets a weight of its own on "%s", which the title family may not ship a cut for.', $file, $rule)
            );
        }
    }

    // The menu brand's name is set in the body family, which ships a real 700 - it keeps its own weight rather than following the titles
    #[\PHPUnit\Framework\Attributes\DataProvider('stylesheetProvider')]
    public function testTheMenuBrandNameKeepsItsOwnWeight(string $file): void
    {
        $this->assertMatchesRegularExpression(
            '/(?:^|[{},])\.menu-site-name\{[^}]*font-weight:bold/',
            $this->stylesheet($file),
            sprintf('"%s" no longer sets the menu brand\'s name in bold.', $file)
        );
    }

    // Strips comments and collapses whitespace, so the same assertions hold on the minified sheet
    private function stylesheet(string $file): string
    {
        $path = \dirname(__DIR__) . '/public/css/' . $file;
        $this->assertFileExists($path, sprintf('"%s" is missing, the sass has not been compiled.', $file));

        $css = (string) preg_replace('#/\*.*?\*/#s', '', (string) file_get_contents($path));

        return (string) preg_replace('/\s*([{};:,])\s*/', '$1', $css);
    }
}
