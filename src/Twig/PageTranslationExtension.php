<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\SiteBundle\Twig;

use c975L\SiteBundle\Entity\Page;
use c975L\SiteBundle\Service\PagePublicUrlResolver;
use c975L\SiteBundle\Service\PageTranslator;
use Twig\Attribute\AsTwigFunction;

// The page's own two texts, said in the language being served. Two functions rather than a translated entity: an entity whose title was replaced in memory is one Doctrine would happily write back on the next flush.
class PageTranslationExtension
{
    public function __construct(
        private readonly PageTranslator $pageTranslator,
        private readonly PagePublicUrlResolver $pagePublicUrlResolver,
    ) {
    }

    #[AsTwigFunction('page_title')]
    public function getTitle(Page $page): string
    {
        return $this->pageTranslator->getTitle($page);
    }

    #[AsTwigFunction('page_summary')]
    public function getSummary(Page $page): ?string
    {
        return $this->pageTranslator->getSummarySocialNetwork($page);
    }

    /**
     * The same page in every declared language, itself included - what the layout writes its "hreflang" links from.
     *
     * @return array<string, string> hreflang => absolute url
     */
    #[AsTwigFunction('page_alternates')]
    public function getAlternates(Page $page): array
    {
        return $this->pagePublicUrlResolver->resolveAlternates($page);
    }
}
