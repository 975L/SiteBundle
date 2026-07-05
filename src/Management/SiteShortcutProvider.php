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
use c975L\SiteBundle\Controller\Management\SiteShortcutController;
use Symfony\Contracts\Translation\TranslatorInterface;

class SiteShortcutProvider implements ShortcutProviderInterface
{
    public function __construct(
        private readonly TranslatorInterface $translator,
    ) {
    }

    public function getShortcuts(): array
    {
        return [
            [
                'label' => $this->translator->trans('label.sitemap_create', [], 'site'),
                'icon' => 'fas fa-sitemap',
                'route' => SiteShortcutController::SITEMAP_CREATE_ROUTE,
                'active' => false,
            ],
            [
                'label' => $this->translator->trans('label.export_tables', [], 'site'),
                'icon' => 'fas fa-file-export',
                'route' => SiteShortcutController::EXPORT_TABLES_ROUTE,
                'active' => false,
            ],
        ];
    }
}
