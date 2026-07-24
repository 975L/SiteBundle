<?php
/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\SiteBundle\Service;

use c975L\SiteBundle\Entity\Font;

// Guesses a Font's name/weight/style from its uploaded filename, following the naming convention used by Google
// Fonts and most font foundries: "FamilyName-WeightStyle.ext" (eg. "OpenSans-Bold.ttf", "Roboto-ExtraBoldItalic.woff2",
// "Inter-VariableFont_wght.ttf") - a best-effort guess meant to save re-typing three fields per file in
// FontBulkImportController, not a hard requirement: name/weight/style stay editable afterward like a single upload
class FontFilenameParser
{
    // Substring-matched (not exact) against the filename's last "-"/"_"-separated segment, longest keyword first so
    // eg. "extrabold" wins over "bold" - values are the same OpenType/CSS weight scale as FontCrudController::WEIGHT_NAMES
    private const WEIGHT_KEYWORDS = [
        'extralight' => 200,
        'ultralight' => 200,
        'semibold' => 600,
        'demibold' => 600,
        'extrabold' => 800,
        'ultrabold' => 800,
        'variable' => Font::WEIGHT_VARIABLE,
        'regular' => 400,
        'normal' => 400,
        'medium' => 500,
        'light' => 300,
        'thin' => 100,
        'black' => 900,
        'heavy' => 900,
        'bold' => 700,
    ];

    /**
     * @return array{name: string, weight: int, style: string}
     */
    public function parse(string $filename): array
    {
        $base = pathinfo($filename, PATHINFO_FILENAME);
        // Strips a variable font's axis tags (eg. "Inter-VariableFont_wght,opsz" -> "Inter-VariableFont")
        $base = preg_replace('/_[a-z]{3,4}(,[a-z]{3,4})*$/i', '', $base) ?? $base;

        $parts = preg_split('/[-_]+/', $base, -1, PREG_SPLIT_NO_EMPTY) ?: [$base];

        $weight = 400;
        $style = 'normal';
        $nameParts = $parts;

        if (\count($parts) > 1) {
            $suffix = strtolower((string) end($parts));
            $isItalic = str_contains($suffix, 'italic');
            $weightSuffix = str_replace('italic', '', $suffix);
            $matchedWeight = '' !== $weightSuffix ? $this->matchWeight($weightSuffix) : null;

            if ($isItalic || null !== $matchedWeight) {
                array_pop($nameParts);
                $style = $isItalic ? 'italic' : 'normal';
                $weight = $matchedWeight ?? $weight;
            }
        }

        return [
            'name' => $this->humanize(implode(' ', $nameParts)),
            'weight' => $weight,
            'style' => $style,
        ];
    }

    private function matchWeight(string $suffix): ?int
    {
        foreach (self::WEIGHT_KEYWORDS as $keyword => $weight) {
            if (str_contains($suffix, $keyword)) {
                return $weight;
            }
        }

        return null;
    }

    // Splits a camelCase family name (eg. "OpenSans", as bundled without spaces in most font archives) into words
    private function humanize(string $name): string
    {
        $name = preg_replace('/(?<=[a-z0-9])(?=[A-Z])/', ' ', $name) ?? $name;
        $name = preg_replace('/\s+/', ' ', $name) ?? $name;

        return trim($name);
    }
}
