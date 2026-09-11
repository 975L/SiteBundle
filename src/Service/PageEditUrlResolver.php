<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\SiteBundle\Service;

use c975L\ConfigBundle\Service\SiteLocales;
use c975L\SiteBundle\Controller\Management\PageCrudController;
use c975L\SiteBundle\Entity\Page;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGeneratorInterface;

// Builds a Page's EasyAdmin edit URL, shared by every health check listing pages
class PageEditUrlResolver
{
    public function __construct(
        private readonly AdminUrlGeneratorInterface $adminUrlGenerator,
        private readonly SiteLocales $siteLocales,
    ) {
    }

    // unsetAll() first: the generator keeps its route parameters, leaking the previous entityId otherwise. $locale opens the screen that language is written on, where a health check row about the English page sends its reader - the writing language keeps the url it always had (see PageCrudController::contentLocale)
    public function resolve(Page $page, ?string $locale = null): string
    {
        $urlGenerator = $this->adminUrlGenerator->unsetAll()
            ->setController(PageCrudController::class)
            ->setAction(Action::EDIT)
            ->setEntityId($page->getId());

        if (null !== $locale && $locale !== $this->siteLocales->getDefaultLocale()) {
            $urlGenerator->set(PageCrudController::CONTENT_LOCALE_PARAM, $locale);
        }

        return $urlGenerator->generateUrl();
    }
}
