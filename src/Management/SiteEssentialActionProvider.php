<?php
/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */
namespace c975L\SiteBundle\Management;

use c975L\ConfigBundle\Management\EssentialActionProviderInterface;
use c975L\SiteBundle\Controller\Management\FontCrudController;
use c975L\SiteBundle\Controller\Management\MenuCrudController;
use c975L\SiteBundle\Controller\Management\PageCrudController;
use c975L\SiteBundle\Entity\Menu;
use c975L\SiteBundle\Repository\FontRepository;
use c975L\SiteBundle\Repository\MenuRepository;
use c975L\SiteBundle\Repository\PageRepository;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGeneratorInterface;

// This bundle's own essential actions, merged by ConfigBundle's EssentialActionBuilder alongside
// ConfigEssentialActionProvider's - continues that same order sequence (10-40), picking up at 50
class SiteEssentialActionProvider implements EssentialActionProviderInterface
{
    public function __construct(
        private readonly PageRepository $pageRepository,
        private readonly MenuRepository $menuRepository,
        private readonly FontRepository $fontRepository,
        private readonly AdminUrlGeneratorInterface $adminUrlGenerator,
    ) {
    }

    public function getEssentialActions(): array
    {
        $homePage = $this->pageRepository->findOneBySlug('home');
        $homePageId = $homePage?->getId();

        return [
            [
                'slug' => 'pages',
                'label' => 'label.essential_action_pages',
                'description' => 'description.essential_action_pages',
                'translation_domain' => 'site',
                // Straight to the home page's own edit screen when it already exists, otherwise the index has nothing more specific to send the admin to
                'url' => null !== $homePageId ? $this->editUrl(PageCrudController::class, $homePageId) : $this->indexUrl(PageCrudController::class),
                // The homepage ("home" slug) is the one page every site needs to function at all - not "at least one page exists", which a freshly installed site already has from its own fixtures
                'isDone' => null !== $homePage,
                'order' => 50,
            ],
            [
                'slug' => 'menus',
                'label' => 'label.essential_action_menus',
                'description' => 'description.essential_action_menus',
                'translation_domain' => 'site',
                'url' => $this->indexUrl(MenuCrudController::class),
                'isDone' => null !== $this->menuRepository->findOneByLocation(Menu::LOCATION_NAVBAR)
                    && null !== $this->menuRepository->findOneByLocation(Menu::LOCATION_FOOTER),
                'order' => 60,
            ],
            [
                'slug' => 'fonts',
                'label' => 'label.essential_action_fonts',
                'description' => 'description.essential_action_fonts',
                'translation_domain' => 'site',
                'url' => $this->indexUrl(FontCrudController::class),
                'isDone' => [] !== $this->fontRepository->findAllOrdered(),
                'order' => 70,
            ],
        ];
    }

    private function indexUrl(string $controllerFqcn): string
    {
        return $this->adminUrlGenerator
            ->unsetAll()
            ->setController($controllerFqcn)
            ->setAction(Action::INDEX)
            ->generateUrl();
    }

    private function editUrl(string $controllerFqcn, int $entityId): string
    {
        return $this->adminUrlGenerator
            ->unsetAll()
            ->setController($controllerFqcn)
            ->setAction(Action::EDIT)
            ->setEntityId($entityId)
            ->generateUrl();
    }
}
