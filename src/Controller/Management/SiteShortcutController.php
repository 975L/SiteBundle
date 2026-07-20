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
use c975L\UiBundle\Repository\FormRepository;
use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminRoute;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
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
        private readonly FormRepository $formRepository,
        private readonly EntityManagerInterface $manager,
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

    // Flips the "register" c975L\UiBundle\Entity\Form's $enabled flag - same lever RegisterFormAction's Form is checked against by FormController before building/submitting it
    #[AdminRoute(
        path: '/site/user-registration-enabled-toggle',
        name: 'site_user_registration_enabled_toggle',
        options: ['methods' => ['POST']]
    )]
    public function registrationEnabledToggle(Request $request): RedirectResponse
    {
        $this->denyAccessUnlessGranted($this->configService->get('site-role-admin'));

        $form = $this->formRepository->findOneBy(['name' => 'register']);
        if (null !== $form && $this->isCsrfTokenValid(self::REGISTRATION_ENABLED_TOGGLE_ROUTE, $request->request->get('_token'))) {
            $enabled = !$form->isEnabled();
            $form->setEnabled($enabled);
            $this->manager->flush();

            $this->addFlash('success', $this->translator->trans(
                $enabled ? 'flash.user_registration_enabled' : 'flash.user_registration_disabled',
                [],
                'site',
            ));
        }

        return $this->redirectToRoute('management');
    }

    // Downloads the export of all "site_*" tables directly, same non-file-persisting approach as
    // ConfigShortcutController::exportSql; writeFile is set to false so nothing lingers in var/export
    #[AdminRoute(
        path: '/site/export-tables',
        name: 'site_export_tables',
        options: ['methods' => ['POST']]
    )]
    public function exportTables(Request $request): Response
    {
        $this->denyAccessUnlessGranted('ROLE_SUPER_ADMIN');

        if (!$this->isCsrfTokenValid(self::EXPORT_TABLES_ROUTE, $request->request->get('_token'))) {
            return $this->redirectToRoute('management');
        }

        $result = $this->exportTablesCommand->exportTables(writeFile: false);

        if ($result['error'] !== null) {
            $this->addFlash('danger', $result['error']);
            return $this->redirectToRoute('management');
        }

        if (empty($result['tables'])) {
            $this->addFlash('warning', $result['message']);
            return $this->redirectToRoute('management');
        }

        return new Response($result['content'], Response::HTTP_OK, [
            'Content-Type' => 'application/octet-stream',
            'Content-Disposition' => 'attachment; filename="site_' . date('Ymd_His') . '.sql"',
        ]);
    }

    // Calls the creation of sitemaps
    #[AdminRoute(
        path: '/site/sitemap-create',
        name: 'site_sitemap_create',
        options: ['methods' => ['POST']]
    )]
    public function createSitemap(Request $request): RedirectResponse
    {
        $this->denyAccessUnlessGranted('ROLE_SUPER_ADMIN');

        if ($this->isCsrfTokenValid(self::SITEMAP_CREATE_ROUTE, $request->request->get('_token'))) {
            $this->sitemapCreateCommand->createSitemap();
            $this->addFlash('success', $this->translator->trans('flash.sitemaps_created', [], 'site'));
        }

        return $this->redirectToRoute('management');
    }
}
