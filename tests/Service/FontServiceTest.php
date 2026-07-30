<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\SiteBundle\Tests\Service;

use c975L\SiteBundle\Repository\FontRepository;
use c975L\SiteBundle\Service\FontService;
use PHPUnit\Framework\TestCase;

class FontServiceTest extends TestCase
{
    private function createService(array $uploadedNames = []): FontService
    {
        $fontRepository = $this->createStub(FontRepository::class);
        $fontRepository->method('findDistinctNames')->willReturn($uploadedNames);

        return new FontService($fontRepository);
    }

    public function testGetFontsReturnsUploadedFontNames(): void
    {
        $this->assertSame(['Roboto'], $this->createService(['Roboto'])->getFonts());
    }

    // findDistinctNames() is DISTINCT but unordered - the config select needs a stable, alphabetical list
    public function testGetFontsSortsNamesAlphabetically(): void
    {
        $this->assertSame(['Georgia', 'Inter', 'Roboto'], $this->createService(['Roboto', 'Georgia', 'Inter'])->getFonts());
    }

    public function testGetFontsReturnsEmptyArrayWhenNoFontIsUploaded(): void
    {
        $this->assertSame([], $this->createService()->getFonts());
    }
}
