<?php
/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\SiteBundle\Listener;

use c975L\SiteBundle\Entity\Article;
use c975L\SiteBundle\Listener\Traits\UserTrait;
use DateTime;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsEntityListener;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Event\PreFlushEventArgs;
use Doctrine\ORM\Event\PrePersistEventArgs;
use Doctrine\ORM\Events;
use Symfony\Bundle\SecurityBundle\Security;

#[AsEntityListener(event: Events::preFlush, method: 'preFlush', entity: Article::class)]
#[AsEntityListener(event: Events::prePersist, method: 'prePersist', entity: Article::class)]
class ArticleListener
{
    use UserTrait;

    public function __construct(
        private readonly Security $security,
        private readonly EntityManagerInterface $entityManager
    ) {
    }

    public function preFlush(Article $entity, PreFlushEventArgs $event): void
    {
        $entity->setModification(new DateTime());
        $this->setUser($entity);
    }

    public function prePersist(Article $entity, PrePersistEventArgs $event): void
    {
        $entity->setCreation(new DateTime());
    }
}
