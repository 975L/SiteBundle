<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\SiteBundle\Service;

use c975L\SiteBundle\Repository\PageRepository;
use c975L\UiBundle\Contract\FormPageUrlProviderInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

// Answers UiBundle's "form_url" with the real Page carrying the matching "form" Block - an admin-editable, per-locale slug rather than the bare "ui_form_submit" route. Auto-registered by FormPageUrlProviderPass (scans every service implementing the interface), nothing to wire in services.yaml
class SiteFormPageUrlProvider implements FormPageUrlProviderInterface
{
    public function __construct(
        private readonly PageRepository $pageRepository,
        private readonly UrlGeneratorInterface $urlGenerator,
    ) {
    }

    public function getFormPageUrl(string $formName): ?string
    {
        $page = $this->pageRepository->findOneByFormBlockName($formName);

        return null !== $page
            ? $this->urlGenerator->generate('page_display', ['page' => $page->getSlug()])
            : null;
    }
}
