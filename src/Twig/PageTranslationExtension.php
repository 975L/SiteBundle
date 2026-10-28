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
use c975L\UiBundle\Entity\Media;
use c975L\UiBundle\Service\MediaTranslator;
use Twig\Attribute\AsTwigFunction;

// The page's own two texts, said in the language being served. Two functions rather than a translated entity: an entity whose title was replaced in memory is one Doctrine would happily write back on the next flush.
class PageTranslationExtension
{
    public function __construct(
        private readonly PageTranslator $pageTranslator,
        private readonly PagePublicUrlResolver $pagePublicUrlResolver,
        // Optional and last, the way PageCrudController takes it: a construction that leaves it out shares the image as it was written
        private readonly ?MediaTranslator $mediaTranslator = null,
    ) {
    }

    // The page's share image as the language being served shows it: its own file and alternative text when that language was given some, the image's otherwise. MediaTranslator's overlay is never persisted, unlike a title replaced in memory
    #[AsTwigFunction('page_og_image')]
    public function getOgImage(Page $page): ?Media
    {
        $ogImage = $page->getOgImage();
        if (null !== $ogImage) {
            $this->mediaTranslator?->apply([$ogImage]);
        }

        return $ogImage;
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

    // The languages this page can be read in, each with the bare url carrying "?_locale=xx" and never that language's own "/en/...": ConfigBundle's LocaleListener returns early on a localised route, so a link straight to it would serve that one page in that language and hand the writing language back on the next click, where the query is kept in session and PageController::writingLanguage() redirects to the localised url itself. Empty on a page written in a single language, whose other urls would answer 404 (see PageController::requireTranslated())
    /** @return array<string, string> locale => the url that switches to it */
    #[AsTwigFunction('page_languages')]
    public function getLanguages(Page $page): array
    {
        $locales = $this->pageTranslator->translatedLocales($page);

        if (\count($locales) < 2) {
            return [];
        }

        $path = $this->pagePublicUrlResolver->resolvePath($page);

        return array_combine($locales, array_map(static fn (string $locale): string => $path . '?_locale=' . $locale, $locales));
    }
}
