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
use c975L\ConfigBundle\Service\ConfigServiceInterface;
use c975L\SiteBundle\Controller\Management\PageCrudController;
use c975L\SiteBundle\Repository\PageRepository;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGeneratorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

// What a site is missing to be lawfully online, and what it holds but doesn't serve - neither shows anywhere else
// The legal models themselves are core-bundle's (its "Legal models" screen writes the text), but only this bundle knows whether a Page carries one and whether that Page is published, so the alert belongs here
class SiteAlertProvider implements AlertProviderInterface
{
    // The two a French site cannot go online without. Keyed by the identifier a "legal_model" block stores, which is what DefaultPagesImporter seeds and what the model catalog names
    private const array LEGAL_MODELS = [
        'france/legal-notice' => 'label.legal_notice',
        'france/cookies' => 'label.cookies',
    ];

    private const string HOME_SLUG = 'home';

    public function __construct(
        private readonly PageRepository $pageRepository,
        private readonly AdminUrlGeneratorInterface $adminUrlGenerator,
        private readonly TranslatorInterface $translator,
        private readonly ConfigServiceInterface $configService,
    ) {
    }

    public function getAlerts(): array
    {
        return [...$this->legalPageAlerts(), ...$this->homePageAlerts()];
    }

    // One query per model rather than one for both: findByLegalModels() returns the pages it found, skipping the models it didn't, so a single call cannot say which one is missing
    private function legalPageAlerts(): array
    {
        $alerts = [];

        foreach (self::LEGAL_MODELS as $model => $labelKey) {
            if ([] !== $this->pageRepository->findByLegalModels([$model])) {
                continue;
            }

            // The page may exist but be unpublished or trashed: sending the admin to a blank creation form there has them write a duplicate instead of republishing the one they have
            $existing = $this->pageRepository->findByLegalModelsAnyStatus([$model])[0] ?? null;

            $urlBuilder = $this->adminUrlGenerator
                ->unsetAll()
                ->setController(PageCrudController::class)
            ;
            $url = null === $existing
                ? $urlBuilder->setAction(Action::NEW)->generateUrl()
                : $urlBuilder->setAction(Action::EDIT)->setEntityId($existing->getId())->generateUrl();

            $alerts[] = $this->alert(
                $this->translator->trans($labelKey, [], 'site'),
                $this->translator->trans(null === $existing ? 'label.legal_page_missing' : 'label.legal_page_unpublished', [], 'site'),
                $url,
            );
        }

        return $alerts;
    }

    // Only when the page exists and isn't served: a site with no "home" Page at all answers "/" from a route of its own, which is a supported way to run and not something to nag about (see PageController::home())
    private function homePageAlerts(): array
    {
        $home = $this->pageRepository->findHomePublicationStatus(self::HOME_SLUG);
        if (null === $home || ($home['isPublished'] && !$home['isDeleted'])) {
            return [];
        }

        return [$this->alert(
            $this->translator->trans('label.home_page', [], 'site'),
            $this->translator->trans('label.home_page_unpublished', [], 'site'),
            // Straight to that very page's form, where the switch is
            $this->adminUrlGenerator
                ->unsetAll()
                ->setController(PageCrudController::class)
                ->setAction(Action::EDIT)
                ->setEntityId($home['id'])
                ->generateUrl(),
        )];
    }

    // Same role as the "create a page" shortcut: an admin who cannot edit a page can do nothing about any of this, and an alert they cannot act on is noise on their dashboard
    private function alert(string $label, string $description, string $url): array
    {
        return [
            'label' => $label,
            'description' => $description,
            'severity' => Config::SEVERITY_WARNING,
            'url' => $url,
            'role' => $this->configService->get('site-role-editor'),
        ];
    }
}
