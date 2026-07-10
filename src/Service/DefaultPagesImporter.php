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

    // $onPage, if given, is called for each page not yet in database as fn(array $def): array{import: bool, isPublished: bool}
    // and lets a command decide interactively whether to import it and with which isPublished value.
    // Without it (default), every page is imported using the isPublished value from getDefinitions().
    // Returns ['created' => int, 'skipped' => int]
    public function import(?callable $onPage = null): array
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
                // Skips definitions tied to a bundle (i.e. Shop's "terms of sales") that isn't installed
                if (isset($def['requiresClass']) && !class_exists($def['requiresClass'])) {
                    continue;
                }

                if ($this->pageRepository->findOneBy(['slug' => $def['slug']])) {
                    $skipped++;
                    continue;
                }

                if (null !== $onPage) {
                    $decision = $onPage($def);
                    if (!$decision['import']) {
                        continue;
                    }
                    $def['isPublished'] = $decision['isPublished'];
                }

                $this->em->persist($this->buildPage($def, $now, $user));
                $created++;
            }
        }

        if ($created > 0) {
            $this->em->flush();
        }

        return ['created' => $created, 'skipped' => $skipped];
    }

    private function buildPage(array $def, \DateTime $now, mixed $user): Page
    {
        $page = (new Page())
            ->setTitle($def['title'])
            ->setSlug($def['slug'])
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

        return $page;
    }

    // Returns the default-locale legal pages' slugs, keyed by model and in the fixed display
    // order below - used by SiteCreateCommand to offer them as footer menu items. A definition
    // whose bundle isn't installed (e.g. terms-of-sales without ShopBundle) is skipped.
    public function getLegalPageSlugsByModel(): array
    {
        $order = ['france/legal-notice', 'france/privacy-policy', 'france/terms-of-use', 'france/terms-of-sales', 'france/cookies', 'france/copyright'];

        $slugsByModel = [];
        foreach ($this->getDefinitions()[$this->defaultLocale] ?? [] as $def) {
            if (isset($def['model']) && (!isset($def['requiresClass']) || class_exists($def['requiresClass']))) {
                $slugsByModel[$def['model']] = $def['slug'];
            }
        }

        $ordered = [];
        foreach ($order as $model) {
            if (isset($slugsByModel[$model])) {
                $ordered[$model] = $slugsByModel[$model];
            }
        }

        return $ordered;
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
                    'changeFrequency' => 'daily',
                    'priority'        => 10,
                    'isPublished'        => true,
                ],
                [
                    'title'           => 'Mentions légales',
                    'slug'            => 'mentions-legales',
                    'model'           => 'france/legal-notice',
                    'changeFrequency' => 'yearly',
                    'priority'        => 1,
                    'isPublished'        => true,
                ],
                [
                    'title'           => 'Règles de confidentialité',
                    'slug'            => 'regles-de-confidentialite',
                    'model'           => 'france/privacy-policy',
                    'changeFrequency' => 'yearly',
                    'priority'        => 1,
                    'isPublished'        => true,
                ],
                [
                    'title'           => 'Conditions générales d\'utilisation',
                    'slug'            => 'conditions-generales-d-utilisation',
                    'model'           => 'france/terms-of-use',
                    'changeFrequency' => 'yearly',
                    'priority'        => 1,
                    'isPublished'        => true,
                ],
                [
                    'title'           => 'Conditions générales de vente',
                    'slug'            => 'conditions-generales-de-vente',
                    'model'           => 'france/terms-of-sales',
                    'changeFrequency' => 'yearly',
                    'priority'        => 1,
                    'isPublished'        => false,
                    'requiresClass'   => 'c975L\\ShopBundle\\c975LShopBundle',
                ],
                [
                    'title'           => 'Utilisation des cookies',
                    'slug'            => 'cookies',
                    'model'           => 'france/cookies',
                    'changeFrequency' => 'yearly',
                    'priority'        => 1,
                    'isPublished'        => true,
                ],
                [
                    'title'           => 'Copyright',
                    'slug'            => 'copyright',
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
                    'changeFrequency' => 'daily',
                    'priority'        => 10,
                    'isPublished'        => true,
                ],
                [
                    'title'           => 'Legal notice',
                    'slug'            => 'legal-notice',
                    'model'           => 'france/legal-notice',
                    'changeFrequency' => 'yearly',
                    'priority'        => 1,
                    'isPublished'        => true,
                ],
                [
                    'title'           => 'Privacy policy',
                    'slug'            => 'privacy-policy',
                    'model'           => 'france/privacy-policy',
                    'changeFrequency' => 'yearly',
                    'priority'        => 1,
                    'isPublished'        => true,
                ],
                [
                    'title'           => 'Terms of use',
                    'slug'            => 'terms-of-use',
                    'model'           => 'france/terms-of-use',
                    'changeFrequency' => 'yearly',
                    'priority'        => 1,
                    'isPublished'        => true,
                ],
                [
                    'title'           => 'Terms of sales',
                    'slug'            => 'terms-of-sales',
                    'model'           => 'france/terms-of-sales',
                    'changeFrequency' => 'yearly',
                    'priority'        => 1,
                    'isPublished'        => false,
                ],
                [
                    'title'           => 'Cookies usage',
                    'slug'            => 'cookies-usage',
                    'model'           => 'france/cookies',
                    'changeFrequency' => 'yearly',
                    'priority'        => 1,
                    'isPublished'        => true,
                ],
                [
                    'title'           => 'Copyright',
                    'slug'            => 'copyright-notice',
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
                    'changeFrequency' => 'daily',
                    'priority'        => 10,
                    'isPublished'        => true,
                ],
                [
                    'title'           => 'Aviso legal',
                    'slug'            => 'aviso-legal',
                    'model'           => 'france/legal-notice',
                    'changeFrequency' => 'yearly',
                    'priority'        => 1,
                    'isPublished'        => true,
                ],
                [
                    'title'           => 'Política de privacidad',
                    'slug'            => 'politica-de-privacidad',
                    'model'           => 'france/privacy-policy',
                    'changeFrequency' => 'yearly',
                    'priority'        => 1,
                    'isPublished'        => true,
                ],
                [
                    'title'           => 'Condiciones de uso',
                    'slug'            => 'condiciones-de-uso',
                    'model'           => 'france/terms-of-use',
                    'changeFrequency' => 'yearly',
                    'priority'        => 1,
                    'isPublished'        => true,
                ],
                [
                    'title'           => 'Condiciones de venta',
                    'slug'            => 'condiciones-de-venta',
                    'model'           => 'france/terms-of-sales',
                    'changeFrequency' => 'yearly',
                    'priority'        => 1,
                    'isPublished'        => false,
                ],
                [
                    'title'           => 'Uso de cookies',
                    'slug'            => 'uso-de-cookies',
                    'model'           => 'france/cookies',
                    'changeFrequency' => 'yearly',
                    'priority'        => 1,
                    'isPublished'        => true,
                ],
                [
                    'title'           => 'Copyright',
                    'slug'            => 'aviso-de-copyright',
                    'model'           => 'france/copyright',
                    'changeFrequency' => 'yearly',
                    'priority'        => 1,
                    'isPublished'        => true,
                ],
            ],
        ];
    }
}
