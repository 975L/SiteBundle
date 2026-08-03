<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\SiteBundle\Tests;

use c975L\UiBundle\c975LUiBundle;
use PHPUnit\Framework\TestCase;

// The scaffolded theme.css is a hand-maintained copy of the token defaults, so it drifts on its own
// A name kept after the bundle dropped it is deliberately not asserted, vendor/ holding any version
class ScaffoldThemeTest extends TestCase
{
    // Admin-editable from the backoffice, so listing them would hand the palette to this file's editor
    private const ADMIN_EDITABLE = [
        '--primary',
        '--secondary',
        '--background',
        '--text',
        '--font-family-title',
        '--font-family-body',
        '--font-family-accent',
    ];

    // Written by JS on the element itself, never read off :root, so a value here would apply to nothing
    private const RUNTIME_ONLY = [
        '--image-compare-position',
        '--slider-freeflow-vw',
    ];

    // Read, but never off :root either: the "--c975l-" pair is what the backoffice compiles into
    // site-theme.css, the "--bs-" ones belong to EasyAdmin, and the "--flex-columns-" ones are set on
    // the row and on each column's own span modifier - one value in :root would size every column alike.
    // Same for "--slider-freeflow-item" and "--contact-details-gutter", declared on ".slider-freeflow"
    // and ".contact-details" themselves, where the local declaration beats anything inherited from :root
    private const NOT_THEMABLE = [
        '--c975l-color-background',
        '--c975l-color-primary',
        '--c975l-color-primary-dark-mode',
        '--c975l-color-secondary',
        '--c975l-color-secondary-dark-mode',
        '--c975l-color-text',
        '--c975l-font-family-accent',
        '--c975l-font-family-body',
        '--c975l-font-family-title',
        '--bs-primary',
        '--bs-secondary-bg',
        '--bs-tertiary-bg',
        '--contact-details-gutter',
        '--flex-columns-gap',
        '--flex-columns-span',
        '--slider-freeflow-item',
    ];

    // Set inside each ".section--bg-*" rule, mixed out of that flat's own background - one value in :root
    // would collapse the three variants into a single look (the scaffold's own header says as much)
    private const PER_VARIANT = [
        '--section-background',
        '--section-text',
        '--section-text-soft',
        '--section-accent',
        '--section-border',
        '--section-overlay',
    ];

    // UiBundle's own directory, located through its bundle class rather than through "vendor/c975l/ui-bundle": it ships inside c975l/core-bundle, one directory deeper than that guess ever looked - same reasoning as ConfigBundle's BundleLocator, which reads it off the kernel at runtime
    public static function uiBundleDir(): string
    {
        return dirname((string) (new \ReflectionClass(c975LUiBundle::class))->getFileName(), 2);
    }

    // A. Every token the bundle declares is offered, so the file stays the single place to look
    public function testScaffoldOffersEveryDeclaredToken(): void
    {
        $missing = array_diff(
            array_keys($this->compiledRoot()),
            array_keys($this->scaffoldTokens()),
            self::ADMIN_EDITABLE,
            self::RUNTIME_ONLY
        );

        $this->assertSame([], array_values($missing), sprintf(
            'sass/_variables.scss declares %s that the scaffolded theme.css never mentions: a design has no way to discover them short of reading the compiled stylesheet.',
            implode(', ', $missing)
        ));
    }

    // B. What the scaffold shows as a default really is the one in force
    public function testScaffoldValuesMatchTheCompiledDefaults(): void
    {
        $compiled = $this->compiledRoot();
        $drifted = [];
        foreach ($this->scaffoldTokens() as $name => $value) {
            if (isset($compiled[$name]) && $compiled[$name] !== $value) {
                $drifted[] = sprintf('%s (bundle: "%s", scaffold: "%s")', $name, $compiled[$name], $value);
            }
        }

        $this->assertSame([], $drifted, sprintf(
            "The scaffolded theme.css no longer mirrors sass/_variables.scss:\n- %s",
            implode("\n- ", $drifted)
        ));
    }

    // C. Nothing is ever active, a value here outliving any later change to the bundle's default
    public function testScaffoldShipsEverythingCommentedOut(): void
    {
        foreach ($this->scaffoldPaths() as $path) {
            $bare = (string) preg_replace('#/\*.*?\*/#s', '', (string) file_get_contents($path));

            preg_match_all('/^\s*(--[a-z0-9-]+):/m', $bare, $matches);

            $this->assertSame([], $matches[1], sprintf(
                'The scaffolded %s declares %s outside a comment: a fresh site would freeze that value instead of following the bundle.',
                basename($path),
                implode(', ', $matches[1])
            ));
        }
    }

    // D. A token read with an inline fallback never reaches :root, so test A above is blind to it - and a
    // whole batch of form and alert tokens did stay out of the scaffold for a release because of that
    public function testScaffoldOffersEveryTokenReadWithAnInlineFallback(): void
    {
        $missing = array_diff(
            array_keys($this->tokensReadFromSass()),
            array_keys($this->scaffoldTokens()),
            self::ADMIN_EDITABLE,
            self::RUNTIME_ONLY,
            self::NOT_THEMABLE,
            self::PER_VARIANT
        );

        $this->assertSame([], array_values($missing), sprintf(
            'The sass reads %s with a fallback, so no :root ever carries it and the scaffolded theme.css never offers it: a design has no way to discover it.',
            implode(', ', $missing)
        ));
    }

    /**
     * Every "var(--x, …)" this bundle and UiBundle read, the fallback being where such a token's default lives.
     *
     * @return array<string, true>
     */
    private function tokensReadFromSass(): array
    {
        $roots = [dirname(__DIR__) . '/sass', self::uiBundleDir() . '/sass'];
        $tokens = [];

        foreach ($roots as $root) {
            // UiBundle is a hard requirement, so a missing root is an anomaly to report rather than a half-blind pass
            $this->assertDirectoryExists($root, sprintf('"%s" is missing, so the tokens it holds would go unchecked.', $root));

            /** @var iterable<\SplFileInfo> $files */
            $files = new \RegexIterator(
                new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root)),
                '/\.scss$/'
            );

            foreach ($files as $file) {
                // Comments are stripped first: prose explaining a rule quotes "var(--x)" too, and an
                // interpolated name written there ("var(--block-accent-#{$hue})") reads as a token nothing offers
                $code = (string) preg_replace(
                    ['#/\*.*?\*/#s', '#//[^\n]*#'],
                    '',
                    (string) file_get_contents($file->getPathname())
                );

                preg_match_all('/var\(\s*(--[a-z0-9-]+)/', $code, $matches);

                foreach ($matches[1] as $token) {
                    $tokens[$token] = true;
                }
            }
        }

        $this->assertNotEmpty($tokens, 'No sass was read, this test no longer checks anything.');

        return $tokens;
    }

    /**
     * Declarations of the compiled :root - the defaults actually served, rather than the sass that produced them.
     *
     * @return array<string, string>
     */
    private function compiledRoot(): array
    {
        $path = dirname(__DIR__) . '/public/css/styles.css';
        $this->assertFileExists($path, 'styles.css is missing, the sass has not been compiled.');

        $css = (string) file_get_contents($path);
        $start = strpos($css, ':root {');
        $this->assertNotFalse($start, 'styles.css carries no :root block.');

        $block = substr($css, $start, (int) strpos($css, "\n}", $start) - $start);

        return $this->declarations($block);
    }

    /**
     * One theme file per bundle, each listing what that bundle reads: this bundle's chrome in site.css, and
     * everything UiBundle reads in its own ui.css, which travels with it so a site running it without
     * SiteBundle still gets a catalogue. Both are asserted together - what matters to a design is that the
     * union covers the surface, not which file a given token landed in.
     *
     * @return list<string>
     */
    private function scaffoldPaths(): array
    {
        $ui = self::uiBundleDir() . '/scaffold/assets/styles/themes/ui.css';

        // Half of the catalogue lives there, and composer requires a UiBundle that ships it: a missing file
        // is an installed vendor predating the split, an anomaly to report rather than a half-blind pass -
        // skipping instead would leave every assertion below silent for as long as the vendor lags behind
        $this->assertFileExists($ui, sprintf('"%s" is missing: the installed c975l/core-bundle predates the theme split, so the two halves of the catalogue cannot be checked together - run "composer update c975l/core-bundle".', $ui));

        return [dirname(__DIR__) . '/scaffold/assets/styles/themes/site.css', $ui];
    }

    /**
     * Declarations of the scaffold, commented ones included - being all commented out is the point of the file.
     *
     * @return array<string, string>
     */
    private function scaffoldTokens(): array
    {
        $tokens = [];

        foreach ($this->scaffoldPaths() as $path) {
            // UiBundle is a hard requirement, so a missing scaffold is an anomaly to report rather than a half-blind pass
            $this->assertFileExists($path, sprintf('"%s" is missing, so the tokens it holds would go unchecked.', $path));

            // Strips the per-line comment markers, leaving the declarations themselves to be read as usual
            $css = (string) preg_replace('#^(\s*)/\*\s*(--[a-z0-9-]+:[^;]+;)\s*\*/#m', '$1$2', (string) file_get_contents($path));

            // Whatever prose is left is only matched if it holds a declaration, already unwrapped above
            $declarations = $this->declarations((string) preg_replace('#/\*.*?\*/#s', '', $css));

            // One token, one bundle: the same name offered twice would leave the second copy free to drift, the
            // merge below keeping the first file's value and test B never reading the other one
            $duplicates = array_keys(array_intersect_key($declarations, $tokens));
            $this->assertSame([], $duplicates, sprintf(
                '%s offers %s, which another theme file already carries: a token belongs to the one bundle that reads it, and the copy left behind would drift unchecked.',
                basename($path),
                implode(', ', $duplicates)
            ));

            $tokens += $declarations;
        }

        return $tokens;
    }

    /**
     * @return array<string, string>
     */
    private function declarations(string $css): array
    {
        preg_match_all('/^\s*(--[a-z0-9-]+):\s*(.+?);\s*$/m', $css, $matches, PREG_SET_ORDER);

        $declarations = [];
        foreach ($matches as $match) {
            $declarations[$match[1]] = trim($match[2]);
        }

        return $declarations;
    }
}
