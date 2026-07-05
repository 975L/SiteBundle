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
use c975L\SiteBundle\Command\SitemapCreateCommand;
use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminRoute;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Contracts\Translation\TranslatorInterface;

class SiteShortcutController extends AbstractController
{
    // EasyAdmin prefixes this with the Dashboard's own route name, giving management_site_sitemap_create
    public const SITEMAP_CREATE_ROUTE = 'management_site_sitemap_create';

    public function __construct(
        private readonly SitemapCreateCommand $sitemapCreateCommand,
        private readonly ConfigServiceInterface $configService,
        private readonly TranslatorInterface $translator,
    ) {
    }

    #[AdminRoute(path: '/site/sitemap-create', name: 'site_sitemap_create', options: ['methods' => ['POST']])]
    public function createSitemap(Request $request): RedirectResponse
    {
        $this->denyAccessUnlessGranted($this->configService->get('site-role-needed'));

        if ($this->isCsrfTokenValid(self::SITEMAP_CREATE_ROUTE, $request->request->get('_token'))) {
            $this->sitemapCreateCommand->createSitemap();
            $this->addFlash('success', $this->translator->trans('flash.sitemap_created', [], 'site'));
        }

        return $this->redirectToRoute('management');
    }
}
