<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\SiteBundle\Tests\Service;

use c975L\SiteBundle\Entity\Font;
use c975L\SiteBundle\Service\FontFilenameParser;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class FontFilenameParserTest extends TestCase
{
    public static function provideFilenames(): iterable
    {
        yield 'plain family, no suffix' => ['Roboto.ttf', ['name' => 'Roboto', 'weight' => 400, 'style' => 'normal']];
        yield 'explicit regular' => ['Roboto-Regular.ttf', ['name' => 'Roboto', 'weight' => 400, 'style' => 'normal']];
        yield 'bold' => ['Roboto-Bold.woff2', ['name' => 'Roboto', 'weight' => 700, 'style' => 'normal']];
        yield 'italic only' => ['PlayfairDisplay-Italic.ttf', ['name' => 'Playfair Display', 'weight' => 400, 'style' => 'italic']];
        yield 'weight and italic combined' => ['OpenSans-ExtraBoldItalic.woff2', ['name' => 'Open Sans', 'weight' => 800, 'style' => 'italic']];
        yield 'semibold, underscore separator' => ['Merriweather_SemiBold.woff', ['name' => 'Merriweather', 'weight' => 600, 'style' => 'normal']];
        yield 'multi-word family kept as separate words' => ['PT-Sans-Bold.ttf', ['name' => 'PT Sans', 'weight' => 700, 'style' => 'normal']];
        yield 'variable font axis tag stripped' => ['Inter-VariableFont_wght.ttf', ['name' => 'Inter', 'weight' => Font::WEIGHT_VARIABLE, 'style' => 'normal']];
        yield 'variable font, multiple axis tags' => ['Inter-VariableFont_wght,opsz.ttf', ['name' => 'Inter', 'weight' => Font::WEIGHT_VARIABLE, 'style' => 'normal']];
        yield 'unrecognized suffix kept as part of the name' => ['Family-Book.ttf', ['name' => 'Family Book', 'weight' => 400, 'style' => 'normal']];
    }

    #[DataProvider('provideFilenames')]
    public function testParseGuessesNameWeightAndStyleFromFilename(string $filename, array $expected): void
    {
        $this->assertSame($expected, (new FontFilenameParser())->parse($filename));
    }
}
