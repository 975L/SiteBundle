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

// The size body copy is read at is a token, and it only means anything as long as the headings stay out of it: in "em" they would ride it, and a design opening up its paragraphs would drag every title along with them
class BodyFontSizeTest extends TestCase
{
    private const array HEADINGS = ['h1', 'h2', 'h3', 'h4', 'h5', 'h6'];

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
    public function testBodyReadsItsFontSizeToken(string $file): void
    {
        $css = $this->stylesheet($file);

        $this->assertStringContainsString(
            'font-size:var(--font-size-body)',
            $css,
            sprintf('"%s" does not size the body off its token, so a design has nothing to turn.', $file)
        );

        // 1rem is the browser's own default, i.e. what the site was read at before the token existed
        $this->assertStringContainsString(
            '--font-size-body:1rem',
            $css,
            sprintf('"%s" does not declare the token\'s default value.', $file)
        );
    }

    // In rem the headings are laid out against the root, which the token does not move - in em they would be laid out against the body copy, and every one of them would grow with it
    #[\PHPUnit\Framework\Attributes\DataProvider('stylesheetProvider')]
    public function testNoHeadingIsSizedInEm(string $file): void
    {
        $css = $this->stylesheet($file);

        foreach (self::HEADINGS as $heading) {
            if (1 !== preg_match('/(?:^|[{}])' . $heading . '\{([^}]*)\}/', $css, $matches)) {
                continue;
            }

            $this->assertDoesNotMatchRegularExpression(
                '/font-size:[\d.]+em/',
                $matches[1],
                sprintf('"%s" sizes <%s> in em, so it rides --font-size-body instead of the root.', $file, $heading)
            );
        }
    }

    // The scaffolded site.css restates every chrome token at its default value, this one included
    public function testScaffoldedThemeRestatesTheToken(): void
    {
        $path = \dirname(__DIR__) . '/scaffold/assets/styles/themes/site.css';
        $this->assertFileExists($path);

        $this->assertStringContainsString('--font-size-body: 1rem;', (string) file_get_contents($path));
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
