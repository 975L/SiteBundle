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

/**
 * Interface to be called for DI for PageServiceInterface related services
 * @author Laurent Marquet <laurent.marquet@laposte.net>
 * @copyright 2026 975L <contact@975l.com>
 */
interface PageServiceInterface
{
    // Find all pages
    public function findAll(): array;

    // Gets the page (published only)
    public function findOneBySlug(string $slug): ?Page;

    // Gets the page regardless of status (for display: handles redirects and 410)
    public function findForDisplay(string $slug): ?Page;
}
