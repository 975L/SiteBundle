<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\SiteBundle\Service;

use c975L\ConfigBundle\Service\SiteLocales;
use c975L\SiteBundle\Entity\Page;
use c975L\SiteBundle\Repository\PageRepository;
use c975L\SiteBundle\Twig\MenuExtension;
use c975L\UiBundle\Contract\InternalLinkLocalizerInterface;
use Symfony\Component\HttpFoundation\RequestStack;

// Rewrites this site's own page links into the language the page around them is being read in - the very rule MenuExtension applies to a menu item, applied to everything else a visitor can click: a portfolio card, a call to action, a word linked inside a rich text. Without it a visitor reading "/en/" is sent back into the writing language at the first click, since PageController answers "/pages/{slug}" in that language alone
class PageLinkLocalizer implements InternalLinkLocalizerInterface
{
    // What this rewrites, and nothing else: a link of this site to a page of this site, alone in its value or inside an href. An external url, a mailto, a bare anchor and every other path are given back untouched
    private const string PAGE_PATH = '#^/pages/(?<slug>[a-zA-Z0-9_-]+)(?<rest>[/?\#].*)?$#';

    // A target picked in a block's link field ("page:12#offer-34", "route:shop_index"), the very value a menu link stores: turned into its url in every language, the writing one included, so a page renamed since keeps being reached
    private const string TARGET = '#^(?:page|route):#';

    // Slug => the Page it names, false where none does, for the length of the request. A rich text is re-rendered on every request when its block declares itself uncacheable, and every href in it is looked up here: a page carrying ten links to the same handful of pages paid ten queries for them.
    /** @var array<string, Page|false> */
    private array $pages = [];

    public function __construct(
        private readonly PagePublicUrlResolver $pagePublicUrlResolver,
        private readonly SiteLocales $siteLocales,
        private readonly PageTranslator $pageTranslator,
        private readonly PageRepository $pageRepository,
        private readonly RequestStack $requestStack,
        private readonly ?MenuExtension $menuExtension = null,
    ) {
    }

    public function localize(string $value): string
    {
        $locale = $this->readingLocale();
        if (null === $locale && !str_contains($value, 'page:') && !str_contains($value, 'route:')) {
            return $value;
        }

        // A whole rich text: only what an href holds is a link, the same words elsewhere in the prose being prose
        if (str_contains($value, 'href="')) {
            return (string) preg_replace_callback(
                '#href="([^"]*)"#',
                fn (array $matches): string => sprintf('href="%s"', 1 === preg_match(self::TARGET, $matches[1])
                    ? htmlspecialchars($this->targetUrl($matches[1]), \ENT_QUOTES)
                    : $this->localizePath($matches[1], $locale)),
                $value
            );
        }

        return 1 === preg_match(self::TARGET, $value) ? $this->targetUrl($value) : $this->localizePath($value, $locale);
    }

    // Decoded by the menus' own reading, which already writes it in the language being read: an unpublished or deleted page gives an empty url, as it does to a menu item
    private function targetUrl(string $target): string
    {
        return $this->menuExtension?->getMenuLinkUrl(html_entity_decode($target, \ENT_QUOTES)) ?? $target;
    }

    // The language being read, when it is one the site declares besides the one it was written in. The route attribute rather than getLocale(), which PageController switches back for the duration of the render - the same reading MenuExtension does
    private function readingLocale(): ?string
    {
        $locale = $this->requestStack->getCurrentRequest()?->attributes->get('_locale');

        return \is_string($locale) && '' !== $locale && $locale !== $this->siteLocales->getDefaultLocale() ? $locale : null;
    }

    // Only a page really written in that language: a localised url answers for nothing else (PageController::requireTranslated() 404s it), so an untranslated page keeps the writing language's link rather than one the visitor lands on a 404 from
    private function localizePath(string $path, ?string $locale): string
    {
        if (null === $locale || 1 !== preg_match(self::PAGE_PATH, $path, $matches)) {
            return $path;
        }

        $slug = $matches['slug'];
        // findOneBy() on a slug goes back to base every time, the identity map only answering for an identifier
        $page = $this->pages[$slug] ??= $this->pageRepository->findOneBy(['slug' => $slug]) ?? false;

        if (!$page instanceof Page || !\in_array($locale, $this->pageTranslator->translatedLocales($page), true)) {
            return $path;
        }

        // The resolver rather than the router: it is the single definition of a page's public path, and it alone knows the home page answers at the site root - "/en/pages/home" only ever 301s there
        return $this->pagePublicUrlResolver->resolvePath($page, $locale) . ($matches['rest'] ?? '');
    }
}
