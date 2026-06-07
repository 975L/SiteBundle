<?php
/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\SiteBundle\Listener;

use c975L\SiteBundle\Entity\ArticleMedia;
use c975L\SiteBundle\Listener\Traits\UserTrait;
use DateTime;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsEntityListener;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Event\PreFlushEventArgs;
use Doctrine\ORM\Event\PrePersistEventArgs;
use Doctrine\ORM\Events;
use Symfony\Bundle\SecurityBundle\Security;

#[AsEntityListener(event: Events::preFlush, method: 'preFlush', entity: ArticleMedia::class)]
#[AsEntityListener(event: Events::prePersist, method: 'prePersist', entity: ArticleMedia::class)]
class ArticleMediaListener
{
    use UserTrait;

    public function __construct(
        private readonly Security $security,
        private readonly EntityManagerInterface $entityManager
    ) {
    }

    public function preFlush(ArticleMedia $entity, PreFlushEventArgs $event): void
    {
        $entity->setModification(new DateTime());
        $this->setUser($entity);
    }

    public function prePersist(ArticleMedia $entity, PrePersistEventArgs $event): void
    {
        if (null === $entity->getPosition()) {
            $maxPosition = 0;
            foreach ($entity->getArticle()->getMedias() as $media) {
                $maxPosition = max($maxPosition, $media->getPosition());
            }
            $entity->setPosition($maxPosition + 5);
        }
        $entity->setCreation(new DateTime());
    }
}
