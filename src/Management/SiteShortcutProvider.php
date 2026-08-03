<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\SiteBundle\Management;

use c975L\ConfigBundle\Management\ShortcutProviderInterface;
use c975L\ConfigBundle\Service\ConfigServiceInterface;
use c975L\SiteBundle\Controller\Management\SiteShortcutController;
use Symfony\Contracts\Translation\TranslatorInterface;

class SiteShortcutProvider implements ShortcutProviderInterface
{
    public function __construct(
        private readonly TranslatorInterface $translator,
        private readonly ConfigServiceInterface $configService,
    ) {
    }

    public function getShortcuts(): array
    {
        return [
            [
                'label' => $this->translator->trans('label.create_page', [], 'site'),
                'icon' => 'fas fa-file',
                'route' => SiteShortcutController::CREATE_PAGE_ROUTE,
                'active' => false,
                'role' => $this->configService->get('site-role-editor'),
                'category' => ShortcutProviderInterface::CATEGORY_SITE,
            ],
        ];
    }
}
