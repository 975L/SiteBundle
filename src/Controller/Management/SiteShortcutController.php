<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\SiteBundle\Controller\Management;

use c975L\ConfigBundle\Service\ConfigServiceInterface;
use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminRoute;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;

class SiteShortcutController extends AbstractController
{
    // EasyAdmin prefixes this with the Dashboard's own route name, giving management_XXX
    public const CREATE_PAGE_ROUTE = 'management_site_create_page';

    public function __construct(
        private readonly ConfigServiceInterface $configService,
        private readonly AdminUrlGenerator $adminUrlGenerator,
    ) {
    }

    // Redirects to the page creation form (PageCrudController's "new" action)
    #[AdminRoute(
        path: '/site/create-page',
        name: 'site_create_page',
        options: ['methods' => ['POST']]
    )]
    public function createPage(Request $request): RedirectResponse
    {
        $this->denyAccessUnlessGranted($this->configService->get('site-role-editor'));

        if ($this->isCsrfTokenValid(self::CREATE_PAGE_ROUTE, $request->request->get('_token'))) {
            return $this->redirect(
                $this->adminUrlGenerator
                    ->setController(PageCrudController::class)
                    ->setAction(Action::NEW)
                    ->generateUrl()
            );
        }

        return $this->redirectToRoute('management');
    }
}
