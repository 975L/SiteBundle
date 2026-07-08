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
use Symfony\Component\DependencyInjection\Attribute\Autowire;

class DefaultPagesImporter
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly PageRepository $pageRepository,
        private readonly Security $security,
        #[Autowire('%kernel.default_locale%')]
        private readonly string $defaultLocale,
        #[Autowire('%kernel.enabled_locales%')]
        private readonly array $enabledLocales,
    ) {
    }

    // Returns ['created' => int, 'skipped' => int]
    public function import(): array
    {
        $created = 0;
        $skipped = 0;
        $now = new \DateTime();
        $user = $this->security->getUser();
        $definitions = $this->getDefinitions();

        // Always imports the default locale, plus any locale declared in framework.enabled_locales;
        // the default locale comes first so the homepage keeps a deterministic title
        $locales = array_unique([$this->defaultLocale, ...$this->enabledLocales]);

        foreach ($locales as $locale) {
            if (!isset($definitions[$locale])) {
                continue;
            }

            foreach ($definitions[$locale] as $def) {
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
                    ->setIsPublished($def['isPublished'])
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
        }

        if ($created > 0) {
            $this->em->flush();
        }

        return ['created' => $created, 'skipped' => $skipped];
    }

    // Definitions are keyed by locale; the "home" slug is intentionally identical across
    // locales since PageController looks it up literally and only one can ever exist
    private function getDefinitions(): array
    {
        return [
            'fr' => [
                [
                    'title'           => 'Accueil',
                    'slug'            => 'home',
                    'description'     => 'Accueil',
                    'changeFrequency' => 'daily',
                    'priority'        => 10,
                    'isPublished'        => true,
                ],
                [
                    'title'           => 'Mentions légales',
                    'slug'            => 'mentions-legales',
                    'description'     => 'Mentions légales',
                    'model'           => 'france/legal-notice',
                    'changeFrequency' => 'yearly',
                    'priority'        => 1,
                    'isPublished'        => true,
                ],
                [
                    'title'           => 'Règles de confidentialité',
                    'slug'            => 'regles-de-confidentialite',
                    'description'     => 'Règles de confidentialité',
                    'model'           => 'france/privacy-policy',
                    'changeFrequency' => 'yearly',
                    'priority'        => 1,
                    'isPublished'        => true,
                ],
                [
                    'title'           => 'Conditions générales d\'utilisation',
                    'slug'            => 'conditions-generales-d-utilisation',
                    'description'     => 'Conditions générales d\'utilisation (CGU)',
                    'model'           => 'france/terms-of-use',
                    'changeFrequency' => 'yearly',
                    'priority'        => 1,
                    'isPublished'        => true,
                ],
                [
                    'title'           => 'Conditions générales de vente',
                    'slug'            => 'conditions-generales-de-vente',
                    'description'     => 'Conditions générales de vente (CGV)',
                    'model'           => 'france/terms-of-sales',
                    'changeFrequency' => 'yearly',
                    'priority'        => 1,
                    'isPublished'        => false,
                ],
                [
                    'title'           => 'Utilisation des cookies',
                    'slug'            => 'cookies',
                    'description'     => 'Utilisation des cookies',
                    'model'           => 'france/cookies',
                    'changeFrequency' => 'yearly',
                    'priority'        => 1,
                    'isPublished'        => true,
                ],
                [
                    'title'           => 'Copyright',
                    'slug'            => 'copyright',
                    'description'     => 'Copyright',
                    'model'           => 'france/copyright',
                    'changeFrequency' => 'yearly',
                    'priority'        => 1,
                    'isPublished'        => true,
                ],
            ],
            'en' => [
                [
                    'title'           => 'Home',
                    'slug'            => 'home',
                    'description'     => 'Home',
                    'changeFrequency' => 'daily',
                    'priority'        => 10,
                    'isPublished'        => true,
                ],
                [
                    'title'           => 'Legal notice',
                    'slug'            => 'legal-notice',
                    'description'     => 'Legal notice',
                    'model'           => 'france/legal-notice',
                    'changeFrequency' => 'yearly',
                    'priority'        => 1,
                    'isPublished'        => true,
                ],
                [
                    'title'           => 'Privacy policy',
                    'slug'            => 'privacy-policy',
                    'description'     => 'Privacy policy',
                    'model'           => 'france/privacy-policy',
                    'changeFrequency' => 'yearly',
                    'priority'        => 1,
                    'isPublished'        => true,
                ],
                [
                    'title'           => 'Terms of use',
                    'slug'            => 'terms-of-use',
                    'description'     => 'Terms of use',
                    'model'           => 'france/terms-of-use',
                    'changeFrequency' => 'yearly',
                    'priority'        => 1,
                    'isPublished'        => true,
                ],
                [
                    'title'           => 'Terms of sales',
                    'slug'            => 'terms-of-sales',
                    'description'     => 'Terms of sales',
                    'model'           => 'france/terms-of-sales',
                    'changeFrequency' => 'yearly',
                    'priority'        => 1,
                    'isPublished'        => false,
                ],
                [
                    'title'           => 'Cookies usage',
                    'slug'            => 'cookies-usage',
                    'description'     => 'Cookies usage',
                    'model'           => 'france/cookies',
                    'changeFrequency' => 'yearly',
                    'priority'        => 1,
                    'isPublished'        => true,
                ],
                [
                    'title'           => 'Copyright',
                    'slug'            => 'copyright-notice',
                    'description'     => 'Copyright',
                    'model'           => 'france/copyright',
                    'changeFrequency' => 'yearly',
                    'priority'        => 1,
                    'isPublished'        => true,
                ],
            ],
            'es' => [
                [
                    'title'           => 'Inicio',
                    'slug'            => 'home',
                    'description'     => 'Inicio',
                    'changeFrequency' => 'daily',
                    'priority'        => 10,
                    'isPublished'        => true,
                ],
                [
                    'title'           => 'Aviso legal',
                    'slug'            => 'aviso-legal',
                    'description'     => 'Aviso legal',
                    'model'           => 'france/legal-notice',
                    'changeFrequency' => 'yearly',
                    'priority'        => 1,
                    'isPublished'        => true,
                ],
                [
                    'title'           => 'Política de privacidad',
                    'slug'            => 'politica-de-privacidad',
                    'description'     => 'Política de privacidad',
                    'model'           => 'france/privacy-policy',
                    'changeFrequency' => 'yearly',
                    'priority'        => 1,
                    'isPublished'        => true,
                ],
                [
                    'title'           => 'Condiciones de uso',
                    'slug'            => 'condiciones-de-uso',
                    'description'     => 'Condiciones de uso',
                    'model'           => 'france/terms-of-use',
                    'changeFrequency' => 'yearly',
                    'priority'        => 1,
                    'isPublished'        => true,
                ],
                [
                    'title'           => 'Condiciones de venta',
                    'slug'            => 'condiciones-de-venta',
                    'description'     => 'Condiciones de venta',
                    'model'           => 'france/terms-of-sales',
                    'changeFrequency' => 'yearly',
                    'priority'        => 1,
                    'isPublished'        => false,
                ],
                [
                    'title'           => 'Uso de cookies',
                    'slug'            => 'uso-de-cookies',
                    'description'     => 'Uso de cookies',
                    'model'           => 'france/cookies',
                    'changeFrequency' => 'yearly',
                    'priority'        => 1,
                    'isPublished'        => true,
                ],
                [
                    'title'           => 'Copyright',
                    'slug'            => 'aviso-de-copyright',
                    'description'     => 'Copyright',
                    'model'           => 'france/copyright',
                    'changeFrequency' => 'yearly',
                    'priority'        => 1,
                    'isPublished'        => true,
                ],
            ],
        ];
    }
}
