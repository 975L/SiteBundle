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

// Guards assets/js/translations.js, the single file the three per-locale modules were merged into - a locale dropped from it silently falls back to English in Handlers.translate(), and a key missing from one locale renders as the raw "form.registration.password.error" string
class TranslationsJsTest extends TestCase
{
    private const LOCALES = ['en', 'es', 'fr'];

    /**
     * @return array<string, array<string, string>>
     */
    private function loadTranslations(): array
    {
        $js = (string) file_get_contents(__DIR__ . '/../assets/js/translations.js');

        // The module is "export default { ... };" around plain JSON, so the object literal decodes as-is
        $start = strpos($js, '{');
        $end = strrpos($js, '}');
        $this->assertIsInt($start);
        $this->assertIsInt($end);

        return json_decode(substr($js, $start, $end - $start + 1), true, 512, \JSON_THROW_ON_ERROR);
    }

    public function testEveryShippedLocaleIsPresent(): void
    {
        $translations = $this->loadTranslations();

        foreach (self::LOCALES as $locale) {
            $this->assertArrayHasKey($locale, $translations);
        }
    }

    // A key present in one locale only is a key that renders raw for every other visitor
    public function testEveryLocaleCarriesTheSameKeys(): void
    {
        $translations = $this->loadTranslations();
        $reference = array_keys($translations['en']);

        foreach (self::LOCALES as $locale) {
            $this->assertSame($reference, array_keys($translations[$locale]), sprintf('The "%s" locale does not carry the same keys as "en"', $locale));
        }
    }

    public function testNoTranslationIsEmpty(): void
    {
        foreach ($this->loadTranslations() as $locale => $messages) {
            foreach ($messages as $key => $message) {
                $this->assertNotSame('', $message, sprintf('"%s" has an empty %s translation', $key, $locale));
            }
        }
    }
}
