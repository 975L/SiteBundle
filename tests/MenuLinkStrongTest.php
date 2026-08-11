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

// The lighter emphasis beside "primary": the label alone is bolded, so the item keeps its place in the row instead of taking a button's room - see MenuLinkTypeTest for the checkbox itself
class MenuLinkStrongTest extends TestCase
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

    public function testTheTemplateAddsTheClassWhenTheBoxIsTicked(): void
    {
        $this->assertStringContainsString(
            "strong|default(false) ? 'menu-item--strong' : null",
            $this->template(),
            'MenuLink.html.twig no longer writes the "menu-item--strong" class, so the checkbox does nothing.'
        );
    }

    // The two are independent and stack, a "primary" item checked "strong" reading as a bolder button
    public function testItIsWrittenBesideThePrimaryClassRatherThanInsteadOfIt(): void
    {
        $template = $this->template();

        $primary = strpos($template, 'menu-item--primary');
        $strong = strpos($template, 'menu-item--strong');

        $this->assertNotFalse($primary);
        $this->assertNotFalse($strong);
        $this->assertLessThan($strong, $primary, 'The two emphases are no longer both listed in the class list, so ticking one drops the other.');
    }

    // Set on the label rather than on the item, .menu-label being what carries the typography
    #[DataProvider('stylesheetProvider')]
    public function testTheStylesheetBoldsTheLabel(string $file): void
    {
        $this->assertMatchesRegularExpression(
            '/\.menu-item--strong \.menu-label\s*\{[^}]*font-weight:\s*bold[;}]/',
            $this->stylesheet($file),
            sprintf('"%s" no longer bolds a "strong" menu link.', $file)
        );
    }

    private function template(): string
    {
        $path = \dirname(__DIR__) . '/templates/blocks/MenuLink.html.twig';
        $this->assertFileExists($path);

        return (string) file_get_contents($path);
    }

    private function stylesheet(string $file): string
    {
        $path = \dirname(__DIR__) . '/public/css/' . $file;
        $this->assertFileExists($path, sprintf('"%s" is missing, recompile sass/styles.scss.', $file));

        return (string) file_get_contents($path);
    }
}
