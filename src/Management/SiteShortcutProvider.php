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
use c975L\UiBundle\Repository\FormRepository;
use Symfony\Contracts\Translation\TranslatorInterface;

class SiteShortcutProvider implements ShortcutProviderInterface
{
    public function __construct(
        private readonly TranslatorInterface $translator,
        private readonly ConfigServiceInterface $configService,
        private readonly FormRepository $formRepository,
    ) {
    }

    public function getShortcuts(): array
    {
        // False if the "register" Form doesn't exist yet either (nothing seeded, e.g. before the first c975l:site:pages:import-defaults) - same as it being explicitly disabled
        $userRegistrationEnabled = $this->formRepository->findOneBy(['name' => 'register'])?->isEnabled() ?? false;

        return [
            [
                'label' => $this->translator->trans('label.create_page', [], 'site'),
                'icon' => 'fas fa-file',
                'route' => SiteShortcutController::CREATE_PAGE_ROUTE,
                'active' => false,
                'role' => $this->configService->get('site-role-editor'),
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
                'role' => $this->configService->get('site-role-admin'),
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
