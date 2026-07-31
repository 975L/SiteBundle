<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\SiteBundle\Management;

use c975L\ConfigBundle\Entity\HealthCheckResult;
use c975L\ConfigBundle\Management\HealthCheckProviderInterface;
use c975L\SiteBundle\Controller\Management\LegalModelController;
use c975L\SiteBundle\Repository\PageRepository;
use c975L\SiteBundle\Service\LegalModelCatalog;
use c975L\SiteBundle\Service\LegalModelCustomizer;
use c975L\SiteBundle\Service\PagePublicUrlResolver;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

// Tells the site's owner when the bundle has reworded a passage they had already rewritten themselves, for
// ConfigBundle's "Health check" dashboard page.
//
// Deliberately informational: the row is reported as STATUS_OK, which never feeds the dashboard alerts nor
// the digest email. A legal text the client took over is theirs, and our having improved our own canvas is
// news, not a fault - they read the new wording and decide. Nothing is ever merged for them.
//
// Only pages that actually drifted produce a row: a site whose legal pages are untouched (the common case,
// and the one where updates flow in on their own) reports nothing at all.
class LegalModelDriftHealthCheckProvider implements HealthCheckProviderInterface
{
    public function __construct(
        private readonly PageRepository $pageRepository,
        private readonly LegalModelCustomizer $customizer,
        private readonly LegalModelCatalog $catalog,
        private readonly PagePublicUrlResolver $pagePublicUrlResolver,
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly TranslatorInterface $translator,
    ) {
    }

    public function getKind(): string
    {
        return 'legal_model';
    }

    public function runChecks(): array
    {
        $results = [];

        foreach ($this->pageRepository->findWithLegalModelBlocks() as $pair) {
            $data = $pair['block']->getData();
            $model = (string) ($data['model'] ?? '');

            if (!$this->catalog->has($model)) {
                continue;
            }

            $drifted = $this->customizer->drifted($model, (array) ($data['customization'] ?? []));
            if ([] === $drifted) {
                continue;
            }

            $url = $this->pagePublicUrlResolver->resolve($pair['page']);
            if (null === $url) {
                continue;
            }

            // The drifted sections are named in the summary rather than in "details": one line says all there
            // is to say here, and the row stays readable straight from the Health check table
            $results[] = [
                'url' => $url,
                'label' => $pair['page']->getTitle(),
                'status' => HealthCheckResult::STATUS_OK,
                'summary' => $this->translator->trans(
                    'label.health_check_legal_model_drift',
                    [
                        '%count%' => \count($drifted),
                        '%model%' => $this->translator->trans($this->catalog->label($model), [], 'site'),
                        '%sections%' => implode(' · ', array_map(
                            static fn (array $unit): string => '' !== $unit['title'] ? $unit['title'] : $unit['id'],
                            $drifted,
                        )),
                    ],
                    'site',
                ),
                'editUrl' => $this->urlGenerator->generate(
                    LegalModelController::CUSTOMIZE_ROUTE,
                    ['block' => $pair['block']->getId()],
                ),
            ];
        }

        return $results;
    }
}
