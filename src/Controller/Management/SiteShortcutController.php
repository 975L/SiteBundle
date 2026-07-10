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
use c975L\SiteBundle\Command\ExportTablesCommand;
use App\Command\SitemapCreateCommand;
use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminRoute;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Contracts\Translation\TranslatorInterface;

class SiteShortcutController extends AbstractController
{
    // EasyAdmin prefixes this with the Dashboard's own route name, giving management_XXX
    public const SITEMAP_CREATE_ROUTE = 'management_site_sitemap_create';
    public const EXPORT_TABLES_ROUTE = 'management_site_export_tables';
    public const REGISTRATION_ENABLED_TOGGLE_ROUTE = 'management_site_user_registration_enabled_toggle';
    public const CREATE_PAGE_ROUTE = 'management_site_create_page';

    public function __construct(
        private readonly SitemapCreateCommand $sitemapCreateCommand,
        private readonly ExportTablesCommand $exportTablesCommand,
        private readonly ConfigServiceInterface $configService,
        private readonly TranslatorInterface $translator,
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
        $this->denyAccessUnlessGranted('ROLE_EDITOR');

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

    // Flips the 'user-registration-enabled' config value;
    #[AdminRoute(
        path: '/site/user-registration-enabled-toggle',
        name: 'site_user_registration_enabled_toggle',
        options: ['methods' => ['POST']]
    )]
    public function registrationEnabledToggle(Request $request): RedirectResponse
    {
        $this->denyAccessUnlessGranted($this->configService->get('site-role-needed'));

        $config = $this->configRepository->findOneBySlug('user-registration-enabled');
        if (null !== $config && $this->isCsrfTokenValid(self::REGISTRATION_ENABLED_TOGGLE_ROUTE, $request->request->get('_token'))) {
            $enabled = !$this->configService->getBool($config->getValue());
            $config->setValue($enabled);
            $config->setModification(new \DateTime());
            $this->manager->flush();
            $this->configService->invalidateCache();

            $this->addFlash('success', $this->translator->trans(
                $enabled ? 'flash.user_registration_enabled' : 'flash.user_registration_disabled',
                [],
                'site',
            ));
        }

        return $this->redirectToRoute('management');
    }

    // Exports all tables to SQL files in the var/export directory, with a flash message indicating success or failure
    #[AdminRoute(
        path: '/site/export-tables',
        name: 'site_export_tables',
        options: ['methods' => ['POST']]
    )]
    public function exportTables(Request $request): RedirectResponse
    {
        $this->denyAccessUnlessGranted('ROLE_SUPER_ADMIN');

        if ($this->isCsrfTokenValid(self::EXPORT_TABLES_ROUTE, $request->request->get('_token'))) {
            $result = $this->exportTablesCommand->exportTables();

            if ($result['error'] !== null) {
                $this->addFlash('danger', $result['error']);
            } elseif (empty($result['tables'])) {
                $this->addFlash('warning', $result['message']);
            } else {
                $this->addFlash('success', $result['message']);
            }
        }

        return $this->redirectToRoute('management');
    }

    // Calls the creation of sitemaps
    #[AdminRoute(
        path: '/site/sitemap-create',
        name: 'site_sitemap_create',
        options: ['methods' => ['POST']]
    )]
    public function createSitemap(Request $request): RedirectResponse
    {
        $this->denyAccessUnlessGranted($this->configService->get('site-role-needed'));

        if ($this->isCsrfTokenValid(self::SITEMAP_CREATE_ROUTE, $request->request->get('_token'))) {
            $this->sitemapCreateCommand->createSitemap();
            $this->addFlash('success', $this->translator->trans('flash.sitemaps_created', [], 'site'));
        }

        return $this->redirectToRoute('management');
    }
}
