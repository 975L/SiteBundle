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
        $userRegistrationEnabled = (bool) $this->configService->get('user-registration-enabled');

        return [
            [
                'label' => $this->translator->trans('label.create_page', [], 'site'),
                'icon' => 'fas fa-file',
                'route' => SiteShortcutController::CREATE_PAGE_ROUTE,
                'active' => false,
                'role' => 'ROLE_EDITOR',
            ],
            [
                'label' => $this->translator->trans(
                    $userRegistrationEnabled ? 'label.user_registration_disable' : 'label.user_registration_enable',
                    [],
                    'site',
                ),
                'icon' => 'fas fa-wrench',
                'route' => SiteShortcutController::REGISTRATION_ENABLED_TOGGLE_ROUTE,
                'active' => $userRegistrationEnabled,
                'role' => $this->configService->get('site-role-needed'),
            ],
            [
                'label' => $this->translator->trans('label.sitemaps_create', [], 'site'),
                'icon' => 'fas fa-sitemap',
                'route' => SiteShortcutController::SITEMAP_CREATE_ROUTE,
                'active' => false,
                'role' => 'ROLE_SUPER_ADMIN',
            ],
            [
                'label' => $this->translator->trans('label.export_tables', [], 'site'),
                'icon' => 'fas fa-database',
                'route' => SiteShortcutController::EXPORT_TABLES_ROUTE,
                'active' => false,
                'role' => 'ROLE_SUPER_ADMIN',
            ],
        ];
    }
}
