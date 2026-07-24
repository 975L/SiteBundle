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
use c975L\SiteBundle\Entity\Redirect;
use c975L\SiteBundle\Repository\RedirectRepository;
use Doctrine\ORM\EntityManagerInterface;

// Imports a "site_redirect" content export (see RedirectExportProvider) - matches by fromPath, Redirect's own unique constraint
class RedirectImportProvider implements ImportProviderInterface
{
    public const KIND = 'site_redirect';

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly RedirectRepository $redirectRepository,
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
            $redirect = $this->redirectRepository->findOneByFromPath($item['fromPath']);
            $isNew = null === $redirect;
            $redirect ??= new Redirect();

            $redirect
                ->setFromPath($item['fromPath'])
                ->setToUrl($item['toUrl'])
                ->setPermanent($item['permanent'] ?? true);

            $this->em->persist($redirect);
            $isNew ? $created++ : $updated++;
        }

        $this->em->flush();

        return ['created' => $created, 'updated' => $updated];
    }
}
