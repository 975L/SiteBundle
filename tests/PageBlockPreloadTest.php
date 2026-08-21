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

// A page built of containers - flex_columns, cards, a group inside a group - costs a query per container and per slot on every render as long as its nested blocks are left lazy. The counterpart of FooterMenuGroupTest's own check on MenuRepository, read off the source since a repository needs a database to run
class PageBlockPreloadTest extends TestCase
{
    /**
     * @return array<string, array{string}>
     */
    public static function finderProvider(): array
    {
        return [
            'findOneByIdWithBlocks' => ['findOneByIdWithBlocks'],
            'findOneBySlug' => ['findOneBySlug'],
            'findOneBySlugForDisplay' => ['findOneBySlugForDisplay'],
        ];
    }

    // Every finder handing a whole page to a render goes through withSlots(), which is where the tree is walked
    #[DataProvider('finderProvider')]
    public function testEveryFinderPreloadsTheNestedBlocks(string $method): void
    {
        $repository = $this->repository();

        $this->assertMatchesRegularExpression(
            sprintf('/public function %s\([^)]*\): \?Page\s*\{\s*return \$this->withSlots\(/', preg_quote($method, '/')),
            $repository,
            sprintf('PageRepository::%s() no longer preloads the page\'s nested blocks, so a container costs a query per slot on every render.', $method)
        );
    }

    // The walking itself is UiBundle's, one query per level rather than a join - a join only ever answered for the first level of the slots
    public function testTheTreeIsWalkedByTheBlockRepository(): void
    {
        $this->assertStringContainsString(
            '$this->blockRepository->preloadSlots($page->getBlocks())',
            $this->repository(),
            'PageRepository walks the block tree itself instead of leaving it to BlockRepository::preloadSlots().'
        );
    }

    private function repository(): string
    {
        $path = dirname(__DIR__) . '/src/Repository/PageRepository.php';
        $this->assertFileExists($path);

        return (string) file_get_contents($path);
    }
}
