<?php
/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */
namespace c975L\SiteBundle\Management;

use c975L\ConfigBundle\Management\ImportProviderInterface;
use c975L\SiteBundle\Entity\Font;
use c975L\SiteBundle\Repository\FontRepository;
use Doctrine\ORM\EntityManagerInterface;
use Vich\UploaderBundle\FileAbstraction\ReplacingFile;

// Imports a "site_font" content export (see FontCrudController::exportSelection/ContentExporter) - matches by name+weight+style (Font has no single unique column, several rows share a name across weight/style cuts), always overwrites the file like PageImportProvider does for Blocks/Medias
class FontImportProvider implements ImportProviderInterface
{
    public const KIND = 'site_font';

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly FontRepository $fontRepository,
    ) {
    }

    public function supportsImport(string $kind): bool
    {
        return self::KIND === $kind;
    }

    public function import(array $items, ?string $filesDir = null): array
    {
        $created = 0;
        $updated = 0;

        foreach ($items as $item) {
            $font = $this->fontRepository->findOneBy([
                'name' => $item['name'],
                'weight' => $item['weight'],
                'style' => $item['style'],
            ]);
            $isNew = null === $font;
            $font ??= new Font();

            $font
                ->setName($item['name'])
                ->setWeight($item['weight'])
                ->setStyle($item['style']);

            if (null !== $filesDir && isset($item['file'])) {
                $font->setFile(new ReplacingFile($filesDir . '/' . $item['file'], true, true, true));
            }

            $this->em->persist($font);
            $isNew ? $created++ : $updated++;
        }

        $this->em->flush();

        return ['created' => $created, 'updated' => $updated];
    }
}
