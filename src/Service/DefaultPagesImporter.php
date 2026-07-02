<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\SiteBundle\Service;

use c975L\SiteBundle\Entity\Page;
use c975L\SiteBundle\Repository\PageRepository;
use c975L\UiBundle\Entity\Block;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;

class DefaultPagesImporter
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly PageRepository $pageRepository,
        private readonly Security $security,
    ) {
    }

    // Returns ['created' => int, 'skipped' => int]
    public function import(): array
    {
        $created = 0;
        $skipped = 0;
        $now = new \DateTime();
        $user = $this->security->getUser();

        foreach ($this->getDefinitions() as $def) {
            if ($this->pageRepository->findOneBy(['slug' => $def['slug']])) {
                $skipped++;
                continue;
            }

            $page = (new Page())
                ->setTitle($def['title'])
                ->setSlug($def['slug'])
                ->setDescription($def['description'])
                ->setIsPublished(false)
                ->setChangeFrequency($def['changeFrequency'])
                ->setPriority($def['priority'])
                ->setCreation($now)
                ->setModification($now);

            if (null !== $user) {
                $page->setUser($user);
            }

            if (isset($def['model'])) {
                $block = (new Block())
                    ->setKind('legal_model')
                    ->setPosition(1)
                    ->setData(['model' => $def['model'], 'latestUpdate' => $now->format('Y-m-d')]);
                $this->em->persist($block);
                $page->addBlock($block);
            }

            $this->em->persist($page);
            $created++;
        }

        if ($created > 0) {
            $this->em->flush();
        }

        return ['created' => $created, 'skipped' => $skipped];
    }

    private function getDefinitions(): array
    {
        return [
            [
                'title'           => 'Home',
                'slug'            => 'home',
                'description'     => 'Accueil',
                'changeFrequency' => 'daily',
                'priority'        => 10,
            ],
            [
                'title'           => 'Mentions légales',
                'slug'            => 'mentions-legales',
                'description'     => 'Mentions légales',
                'model'           => 'france/legal-notice',
                'changeFrequency' => 'yearly',
                'priority'        => 1,
            ],
            [
                'title'           => 'Règles de confidentialité',
                'slug'            => 'regles-de-confidentialite',
                'description'     => 'Règles de confidentialité',
                'model'           => 'france/privacy-policy',
                'changeFrequency' => 'yearly',
                'priority'        => 1,
            ],
            [
                'title'           => 'Conditions générales d\'utilisation',
                'slug'            => 'conditions-generales-d-utilisation',
                'description'     => 'Conditions générales d\'utilisation (CGU)',
                'model'           => 'france/terms-of-use',
                'changeFrequency' => 'yearly',
                'priority'        => 1,
            ],
            [
                'title'           => 'Conditions générales de vente',
                'slug'            => 'conditions-generales-de-vente',
                'description'     => 'Conditions générales de vente (CGV)',
                'model'           => 'france/terms-of-sales',
                'changeFrequency' => 'yearly',
                'priority'        => 1,
            ],
            [
                'title'           => 'Utilisation des cookies',
                'slug'            => 'cookies',
                'description'     => 'Utilisation des cookies',
                'model'           => 'france/cookies',
                'changeFrequency' => 'yearly',
                'priority'        => 1,
            ],
            [
                'title'           => 'Copyright',
                'slug'            => 'copyright',
                'description'     => 'Copyright',
                'model'           => 'france/copyright',
                'changeFrequency' => 'yearly',
                'priority'        => 1,
            ],
        ];
    }
}
