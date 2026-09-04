<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\SiteBundle\Twig;

use c975L\SiteBundle\Service\SiteSnippetBuilder;
use Symfony\Component\HttpFoundation\RequestStack;
use Twig\Attribute\AsTwigFunction;

class SiteJsonLdExtension
{
    public function __construct(
        private readonly SiteSnippetBuilder $snippetBuilder,
        private readonly RequestStack $requestStack,
    ) {
    }

    // Returns the <script type="application/ld+json"> payload for the home page, empty when the site has not been named or addressed yet
    #[AsTwigFunction('site_json_ld', isSafe: ['html'])]
    public function jsonLd(?string $logoUrl = null, ?string $description = null): string
    {
        // The route attribute rather than getLocale(), which PageController switches back to the writing language for the duration of the render - the graph's "inLanguage" has to name the language its description is read in
        $locale = $this->requestStack->getCurrentRequest()?->attributes->get('_locale');

        return $this->snippetBuilder->buildJson($logoUrl, $description, \is_string($locale) ? $locale : null);
    }
}
