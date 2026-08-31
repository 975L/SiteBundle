<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\SiteBundle\Listener;

use c975L\SiteBundle\Entity\Page;
use c975L\SiteBundle\Service\PageTranslator;
use c975L\UiBundle\Repository\TranslationRepository;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\ORM\Event\PostRemoveEventArgs;
use Doctrine\ORM\Events;

// Takes a page's translations away with the page, the way UiBundle's TranslationPurgeListener does for a block.
//
// Translations name their owner rather than pointing at it (see Translation), so no foreign key takes them along:
// a page deleted for good - PageCrudController::deletePermanently(), a fixture, a command - would leave its
// translated title in the table, and a new page landing on the same id would inherit it. Ids survive an
// export/import round trip (see PageCrudController::exportSql()), so that id is one a site really does hand out again.
//
// A site declaring one language never gets here with anything to delete, having stored none.
#[AsDoctrineListener(event: Events::postRemove)]
class PageTranslationPurgeListener
{
    public function __construct(private readonly TranslationRepository $repository)
    {
    }

    public function postRemove(PostRemoveEventArgs $args): void
    {
        $entity = $args->getObject();

        if (!$entity instanceof Page || null === $entity->getId()) {
            return;
        }

        // A DQL delete rather than a remove(): a flush is already running, and nothing here needs hydrating
        $this->repository->deleteByOwner(PageTranslator::OWNER, $entity->getId());
    }
}
