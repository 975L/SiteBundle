<?php
/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */
namespace c975L\SiteBundle\Management;

use c975L\ConfigBundle\Management\ExportProviderInterface;
use c975L\SiteBundle\Entity\CollectionItem;
use c975L\SiteBundle\Management\Trait\ArchiveFileTrait;
use c975L\SiteBundle\Repository\CollectionItemRepository;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

// Serializes CollectionItems (title/description/url/position + the real file bundled in the archive, if any) into the shape ContentExporter/CollectionItemImportProvider expect - for the "export sync all" dashboard shortcut (see ConfigBundle's SyncAllExporter). The "user" who uploaded an item is deliberately left out: App\Entity\User ids never match between dev and prod
class CollectionItemExportProvider implements ExportProviderInterface
{
    use ArchiveFileTrait;

    public function __construct(
        private readonly CollectionItemRepository $collectionItemRepository,
        #[Autowire('%kernel.project_dir%')]
        private readonly string $projectDir,
    ) {
    }

    public function getKind(): string
    {
        return CollectionItemImportProvider::KIND;
    }

    public function exportAll(): array
    {
        return $this->serialize($this->collectionItemRepository->findBy([], ['collectionGroup' => 'ASC', 'position' => 'ASC']));
    }

    // @param iterable<CollectionItem> $collectionItems
    public function serialize(iterable $collectionItems): array
    {
        $files = [];
        $items = [];
        foreach ($collectionItems as $collectionItem) {
            $items[] = $this->exportItemData($collectionItem, $files);
        }

        return ['items' => $items, 'files' => $files];
    }

    // Registers the item's physical file for the zip archive (&$files: archive-relative path => disk path) when it has one - same convention as FontExportProvider::exportFontData(). Unlike Font/Gallery, a missing/absent file isn't fatal here: CollectionItem's image is optional, so the item itself is still exported without a 'file' entry
    private function exportItemData(CollectionItem $collectionItem, array &$files): array
    {
        $data = [
            'collectionGroup' => $collectionItem->getCollectionGroup()?->getName(),
            'title' => $collectionItem->getTitle(),
            'slug' => $collectionItem->getSlug(),
            'description' => $collectionItem->getDescription(),
            'url' => $collectionItem->getUrl(),
            'position' => $collectionItem->getPosition(),
        ];

        $filename = $collectionItem->getFilename();
        if (null === $filename) {
            return $data;
        }

        $registered = $this->registerArchiveFile($this->projectDir, $filename, $files);
        if (null === $registered) {
            return $data;
        }

        $data['originalFilename'] = $registered['originalFilename'];
        $data['file'] = $registered['archivePath'];

        return $data;
    }
}
