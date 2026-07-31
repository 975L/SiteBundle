<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\SiteBundle\Twig;

use c975L\SiteBundle\Service\LegalModelCatalog;
use c975L\SiteBundle\Service\LegalModelPlaceholders;
use c975L\SiteBundle\Service\LegalModelRenderer;
use c975L\UiBundle\Entity\Block;
use Symfony\Component\HttpFoundation\RequestStack;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class LegalModelExtension extends AbstractExtension
{
    public function __construct(
        private readonly LegalModelRenderer $renderer,
        private readonly LegalModelPlaceholders $placeholders,
        private readonly RequestStack $requestStack,
    ) {
    }

    public function getFunctions(): array
    {
        return [
            // Both return finished HTML: legal_var() a config value the models print, legal_model() a whole
            // rendered document whose client-authored parts were escaped when they were stored
            new TwigFunction('legal_var', $this->legalVar(...), ['is_safe' => ['html']]),
            new TwigFunction('legal_model', $this->legalModel(...), ['is_safe' => ['html']]),
        ];
    }

    // The site's own data inside a model - resolved on the spot, or written as a %marker% while the
    // customization screen is collecting the model's units (see LegalModelPlaceholders)
    public function legalVar(string $slug): string
    {
        return $this->placeholders->value($slug);
    }

    // Renders a "legal_model" block: its model, in the request's locale, with its own customization applied
    public function legalModel(Block $block): string
    {
        $data = $block->getData();

        return $this->renderer->render(
            (string) ($data['model'] ?? ''),
            $data['latestUpdate'] ?? null,
            (array) ($data['customization'] ?? []),
            $this->requestStack->getCurrentRequest()?->getLocale() ?? LegalModelCatalog::FALLBACK_LOCALE,
        );
    }
}
