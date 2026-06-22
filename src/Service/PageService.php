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

/**
 * Services related to PageServiceInterface
 * @author Laurent Marquet <laurent.marquet@laposte.net>
 * @copyright 2026 975L <contact@975l.com>
 */
class PageService implements PageServiceInterface
{
    public function __construct(
        private readonly PageRepository $pageRepository,
    ) {
    }

    public function findAll(): array
    {
        return $this->pageRepository->findAllOrdered();
    }

    public function findOneBySlug(string $page): ?Page
    {
        return $this->pageRepository->findOneBySlug(str_replace('.html.twig', '', $page));
    }
}
