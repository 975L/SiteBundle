<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\SiteBundle\Controller\Management;

use c975L\SiteBundle\Entity\Article;
use c975L\SiteBundle\Entity\Page;
use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminDashboard;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Dashboard;
use EasyCorp\Bundle\EasyAdminBundle\Config\MenuItem;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractDashboardController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[AdminDashboard(routePath: '/management', routeName: 'site_management')]
#[IsGranted('ROLE_ADMIN')]
class SiteDashboardController extends AbstractDashboardController
{
    public function index(): Response
    {
        return $this->render('@c975LSite/management/index.html.twig');
    }

    public function configureDashboard(): Dashboard
    {
        return Dashboard::new()
            ->setTitle('<img src="/favicon.ico"> Site')
            ->setFaviconPath('/favicon.ico')
            ->setTranslationDomain('site');
        ;
    }

    public function configureMenuItems(): iterable
    {
        yield MenuItem::linkToDashboard('label.dashboard', 'fa fa-home')->setPermission('ROLE_ADMIN');

        yield MenuItem::section('label.management');
        yield MenuItem::linkTo(PageCrudController::class, 'label.pages', 'fas fa-file', Page::class)->setPermission('ROLE_ADMIN')->setAction(Crud::PAGE_INDEX);
        yield MenuItem::linkTo(ArticleCrudController::class, 'label.articles', 'fas fa-newspaper', Article::class)->setPermission('ROLE_ADMIN')->setAction(Crud::PAGE_INDEX);

        yield MenuItem::section('label.user');
        yield MenuItem::linkToLogout('label.signout', 'fa fa-exit');
    }
}
