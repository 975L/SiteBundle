<?php
/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\SiteBundle\Service;

use c975L\SiteBundle\Controller\Management\PageCrudController;
use c975L\SiteBundle\Entity\Page;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGeneratorInterface;

// Builds a Page's own EasyAdmin edit URL (SiteBundle's PageCrudController) - shared by every HealthCheckProviderInterface
// implementation checking pages (PageSpeed, W3C, content quality, mixed content), so their dashboard rows can
// link to the CRUD screen for the page behind the row, alongside the tested public url (see PagePublicUrlResolver)
class PageEditUrlResolver
{
    public function __construct(private readonly AdminUrlGeneratorInterface $adminUrlGenerator)
    {
    }

    // unsetAll() first: AdminUrlGenerator::generateUrl() never resets its own internal route parameters, so
    // reusing one builder instance across calls would leak the previous page's entityId into the next url
    public function resolve(Page $page): string
    {
        return $this->adminUrlGenerator->unsetAll()
            ->setController(PageCrudController::class)
            ->setAction(Action::EDIT)
            ->setEntityId($page->getId())
            ->generateUrl();
    }
}
