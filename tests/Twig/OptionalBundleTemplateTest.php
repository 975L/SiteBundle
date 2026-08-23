<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\SiteBundle\Tests\Twig;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

// Twig resolves a function call at compile time and an include at render time: a template calling a suggested bundle's function answers 500 on every page of a site not installing it, whatever runtime guard wraps it
class OptionalBundleTemplateTest extends TestCase
{
    // Twig functions registered by SocialBundle alone, which composer.json only suggests
    private const array SUGGESTED_FUNCTIONS = [
        'social_link_block',
        'social_link_icon',
        'share_buttons',
        'share_buttons_default',
    ];

    /**
     * @return array<string, array{string}>
     */
    public static function functionProvider(): array
    {
        $cases = [];
        foreach (self::SUGGESTED_FUNCTIONS as $function) {
            $cases[$function] = [$function];
        }

        return $cases;
    }

    // No template of this bundle may name one of them
    #[DataProvider('functionProvider')]
    public function testNoTemplateCallsASuggestedBundleFunction(string $function): void
    {
        $offenders = [];
        foreach ($this->templates() as $path) {
            // Twig comments are stripped first, a comment naming the function being exactly how this rule is explained where it applies
            $template = (string) preg_replace('/\{#.*?#\}/s', '', (string) file_get_contents($path));
            if (preg_match('/\b' . preg_quote($function, '/') . '\s*\(/', $template)) {
                $offenders[] = basename($path);
            }
        }

        $this->assertSame(
            [],
            $offenders,
            sprintf(
                '"%s" is registered by SocialBundle alone, which composer.json only suggests: %s would fail to compile on a site not installing it. Include the bundle\'s own template with "ignore_missing" instead.',
                $function,
                implode(', ', $offenders)
            )
        );
    }

    // Requiring it back would make the rule above pointless, and silently re-tie every consuming site to it
    public function testSocialBundleIsSuggestedRatherThanRequired(): void
    {
        $composer = json_decode((string) file_get_contents(dirname(__DIR__, 2) . '/composer.json'), true, flags: \JSON_THROW_ON_ERROR);

        $this->assertArrayNotHasKey('c975l/social-bundle', $composer['require']);
        $this->assertArrayHasKey('c975l/social-bundle', $composer['suggest']);
    }

    /**
     * @return string[]
     */
    private function templates(): array
    {
        $directory = dirname(__DIR__, 2) . '/templates';
        $this->assertDirectoryExists($directory);

        $paths = [];
        $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS));
        foreach ($files as $file) {
            if ($file->isFile() && str_ends_with($file->getFilename(), '.twig')) {
                $paths[] = $file->getPathname();
            }
        }

        return $paths;
    }
}
