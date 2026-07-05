<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\SiteBundle\Management;

use c975L\ConfigBundle\Entity\Config;
use c975L\ConfigBundle\Management\AlertProviderInterface;
use c975L\SiteBundle\Controller\Management\SiteGraphicCrudController;
use c975L\UiBundle\Entity\Media;
use c975L\UiBundle\Repository\MediaRepository;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGeneratorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

// Alerts for the site-wide graphics (favicon, apple-touch-icon, og-image, logo) not yet uploaded
class SiteGraphicAlertProvider implements AlertProviderInterface
{
    private const ROLE_LABELS = [
        Media::ROLE_FAVICON => 'label.favicon',
        Media::ROLE_APPLE_TOUCH_ICON => 'label.apple_touch_icon',
        Media::ROLE_OG_IMAGE => 'label.og_image',
        Media::ROLE_LOGO => 'label.logo',
    ];

    public function __construct(
        private readonly MediaRepository $mediaRepository,
        private readonly AdminUrlGeneratorInterface $adminUrlGenerator,
        private readonly TranslatorInterface $translator,
    ) {
    }

    public function getAlerts(): array
    {
        $alerts = [];

        foreach (self::ROLE_LABELS as $role => $labelKey) {
            if (null !== $this->mediaRepository->findOneByRole($role)) {
                continue;
            }

            $alerts[] = [
                'label' => $this->translator->trans($labelKey, [], 'site'),
                'description' => $this->translator->trans('label.site_graphic_missing', [], 'site'),
                'severity' => Config::SEVERITY_WARNING,
                'url' => $this->adminUrlGenerator
                    ->unsetAll()
                    ->setController(SiteGraphicCrudController::class)
                    ->setAction(Action::NEW)
                    ->generateUrl(),
            ];
        }

        return $alerts;
    }
}
