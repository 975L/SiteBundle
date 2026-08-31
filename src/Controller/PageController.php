<?php

/*
 * (c) 2025: 975L <contact@975l.com>
 * (c) 2025: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\SiteBundle\Controller;

use c975L\ConfigBundle\Service\ConfigServiceInterface;
use c975L\ConfigBundle\Service\SiteLocales;
use c975L\SiteBundle\Entity\Page;
use c975L\SiteBundle\Service\PagePublicUrlResolver;
use c975L\SiteBundle\Service\PageServiceInterface;
use c975L\SiteBundle\Twig\CollectionItemContext;
use c975L\UiBundle\Entity\Block;
use c975L\UiBundle\Registry\CollectionSourceRegistry;
use c975L\UiBundle\Service\BlockRenderContext;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\GoneHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Translation\LocaleSwitcher;
use Twig\Environment;

/**
 * Main Site Controller class.
 *
 * @author Laurent Marquet <laurent.marquet@laposte.net>
 * @copyright 2026 975L <contact@975l.com>
 */
class PageController extends AbstractController
{
    public function __construct(
        private readonly PageServiceInterface $pageService,
        private readonly ConfigServiceInterface $configService,
        private readonly CollectionSourceRegistry $collectionSourceRegistry,
        private readonly Environment $twig,
        private readonly CollectionItemContext $collectionItemContext,
        private readonly BlockRenderContext $blockRenderContext,
        private readonly RequestStack $requestStack,
        private readonly LocaleSwitcher $localeSwitcher,
        private readonly SiteLocales $siteLocales,
        private readonly PagePublicUrlResolver $pagePublicUrlResolver,
    ) {
    }

    /**
     * The same route, in the language the request is being answered in.
     *
     * Without it a redirect - the home page, a trailing slash - would drop a visitor reading in one language back
     * into the one the site was written in, and hand a crawler a redirect across languages.
     *
     * @param array<string, string> $parameters
     *
     * @return array{0: string, 1: array<string, string>}
     */
    private function sameLanguage(string $route, array $parameters = []): array
    {
        $locale = $this->requestStack->getCurrentRequest()?->attributes->get('_locale');

        return \is_string($locale) && '' !== $locale
            ? [$route . '_localized', $parameters + ['_locale' => $locale]]
            : [$route, $parameters];
    }

    /**
     * The writing language's own urls, asked for in another language: the visitor is sent to that language's url instead.
     *
     * "/" and "/pages/{page}" are what the sitemap and the hreflang groups declare as the writing language's versions
     * (see PagePublicUrlResolver::resolvePath()), so they may only ever answer in it. A visitor whose own language the
     * site has been translated into is redirected to that language's url rather than served another language here, and
     * anyone else is served the writing language for good - the locale is switched back to it, LocaleListener having
     * already handed the translator whatever the browser asked for.
     *
     * Null when there is nothing to redirect to, which is every request of a site declaring a single language, and every
     * localised url, one having already said in its path which language it answers in.
     *
     * @param array<string, string> $parameters
     */
    private function writingLanguage(Request $request, string $route, array $parameters = []): ?Response
    {
        if (null !== $request->attributes->get('_locale')) {
            return null;
        }

        $locale = $request->getLocale();

        // Only a language the visitor actually asked for: the one their browser announced, asked through getPreferredLanguage() exactly as LocaleListener did, so "en-GB" is read as "en" here too rather than matching nothing; or the one they picked from a language menu, which the listener keeps under Symfony's own "_locale" session key. Announcing neither, the method hands back the first declared language - the writing one - and a crawler that asked for nothing has no business being moved off the url it requested
        $asked = $locale === $request->getPreferredLanguage($this->siteLocales->all())
            || ($request->hasPreviousSession() && $locale === $request->getSession()->get('_locale'));

        if ($asked && $locale !== $this->siteLocales->getDefaultLocale() && \in_array($locale, $this->siteLocales->all(), true)) {
            // The query string goes along: a campaign's "utm_source" or a block's own filter would otherwise be dropped by the redirect. The route's own parameters come first, and the language right after them, so a "?page=" or a "?_locale=" of the visitor's own making is absorbed by the key collision rather than sending them somewhere else
            return $this->redirectToRoute($route . '_localized', $parameters + ['_locale' => $locale] + $request->query->all());
        }

        $this->localeSwitcher->setLocale($this->siteLocales->getDefaultLocale());
        $request->setLocale($this->siteLocales->getDefaultLocale());

        return null;
    }

    // What a non-prefixed url answers depends on the language the browser asks for - that language's url for a visitor the site has been translated for, the page itself for everyone else - which a shared cache has to be told, or the first visitor's answer would be handed to every one after them. The redirect carries it as much as the page does: a cached 302 would send every visitor into one visitor's language
    private function varyOnLanguage(Request $request, Response $response): Response
    {
        if (null === $request->attributes->get('_locale') && $this->siteLocales->isMultilingual()) {
            $response->setVary('Accept-Language', false);
        }

        return $response;
    }

    // REDIRECT HOME
    #[Route(
        path: '/pages',
        name: 'redirect_home_pages'
    )]
    public function redirectPages()
    {
        return $this->redirectToRoute('page_home');
    }

    // REDIRECT HOME POST, PUT, PATCH REQUESTS
    #[Route(
        path: '/',
        name: 'redirect_home_wrong_methods',
        methods: ['POST', 'PUT', 'PATCH']
    )]
    public function redirectIndexWrongMethods()
    {
        // 303 tells the client to replay the request as GET, unlike 301/302 which are meant to preserve the method
        return $this->redirectToRoute('page_home', [], 303);
    }

    // HOME
    // The same home page, in another language: the writing language keeps "/" byte for byte, the others go through "/{_locale}/". The pattern holds the languages the site declares beside the one it is written in, and matches nothing while there are none (see c975LSiteBundle::loadExtension()), so a single-language site only ever answers on the second
    #[Route(
        path: '/{_locale}/',
        name: 'page_home_localized',
        requirements: ['_locale' => '%c975l_site.locales_pattern%'],
        methods: ['GET']
    )]
    #[Route(
        path: '/',
        name: 'page_home',
        methods: ['GET']
    )]
    public function home(Request $request)
    {
        $homePage = $this->pageService->findOneBySlug('home');
        if ($homePage) {
            // No "page" route parameter on "/" (unlike page_display's "/pages/{page}") - set it manually so a "collection" block rendered on the home page can still resolve its own items' detail links (see UiBundle's CollectionExtension::buildDetailUrl())
            $request->attributes->set('page', 'home');

            $otherLanguage = $this->writingLanguage($request, 'page_home');
            if (null !== $otherLanguage) {
                return $this->varyOnLanguage($request, $otherLanguage);
            }

            return $this->varyOnLanguage($request, $this->render(
                '@c975LSite/pages/page.html.twig',
                ['page' => $homePage]
            ));
        }

        throw $this->createNotFoundException();
    }

    // REDIRECT PAGES POST, PUT, PATCH REQUESTS
    #[Route(
        path: '/pages/{page}',
        name: 'redirect_pages_wrong_methods',
        requirements: [
            'page' => '^(?!pdf)([a-zA-Z0-9\-\/]+)',
        ],
        methods: ['POST', 'PUT', 'PATCH']
    )]
    public function redirectPagesWrongMethods()
    {
        // 303 tells the client to replay the request as GET, unlike 301/302 which are meant to preserve the method
        return $this->redirectToRoute('page_home', [], 303);
    }

    // DISPLAY
    #[Route(
        path: '/{_locale}/pages/{page}',
        name: 'page_display_localized',
        requirements: [
            '_locale' => '%c975l_site.locales_pattern%',
            'page' => '^(?!pdf)([a-zA-Z0-9\-\/]+)',
        ],
        methods: ['GET']
    )]
    #[Route(
        path: '/pages/{page}',
        name: 'page_display',
        requirements: [
            'page' => '^(?!pdf)([a-zA-Z0-9\-\/]+)',
        ],
        methods: ['GET']
    )]
    public function display($page, Request $request)
    {
        $slug = rtrim($page, '/');

        // The home page only has one canonical URL: the site root - the one of the language being read
        if ('home' === $slug) {
            [$route, $parameters] = $this->sameLanguage('page_home');

            return $this->redirectToRoute($route, $parameters, 301);
        }

        // "/pages/slug/" used to answer 200 with the exact same content as "/pages/slug", leaving the crawler two urls for one page - a 301 to the slashless form (the one the sitemap declares) settles it, where the canonical link alone is only a hint. Checked after the 'home' case above, so "/pages/home/" reaches the site root in a single hop rather than through a redirect chain
        if ($page !== $slug) {
            [$route, $parameters] = $this->sameLanguage('page_display', ['page' => $slug]);

            return $this->redirectToRoute($route, $parameters, 301);
        }

        $pageObject = $this->pageService->findForDisplay($slug);
        $detailHtml = null;
        $detailTitle = null;

        // No exact Page for this slug: the last segment may be a "collection" block's item slug, carried by the Page one level up (see resolveCollectionDetail())
        if (null === $pageObject && str_contains($slug, '/')) {
            [$pageObject, $detailHtml, $detailTitle] = $this->resolveCollectionDetail($slug);
        }

        if (null === $pageObject) {
            throw $this->createNotFoundException();
        }

        return $this->renderPage($request, $pageObject, $slug, $detailHtml, $detailTitle);
    }

    // The page a slug resolved to, once it is one a visitor may read: a deleted page is gone for good, an unpublished one was never there, and a request written in another language is answered in that one
    private function renderPage(Request $request, Page $pageObject, string $slug, ?string $detailHtml, ?string $detailTitle): Response
    {
        if ($pageObject->isDeleted()) {
            throw new GoneHttpException();
        }
        if (!$pageObject->isPublished()) {
            throw $this->createNotFoundException();
        }

        $otherLanguage = $this->writingLanguage($request, 'page_display', ['page' => $slug]);
        if (null !== $otherLanguage) {
            return $this->varyOnLanguage($request, $otherLanguage);
        }

        return $this->varyOnLanguage($request, $this->render(
            '@c975LSite/pages/page.html.twig',
            [
                'page' => $pageObject,
                'detailHtml' => $detailHtml,
                'detailTitle' => $detailTitle,
                // A collection item is served by its parent Page, whose own group names the parent's urls: this url's group is built from the url itself instead, or the page would declare somebody else's languages and never itself
                'detailAlternates' => null === $detailHtml ? [] : $this->pagePublicUrlResolver->resolveAlternatesForSlug($slug),
            ]
        ));
    }

    // Tries the slug's last segment as a "collection" block's item slug, resolved against the block's own source, then rendered via a separate Page (the block's "detailPage") whose own blocks render normally, with "collectionItem" (see CollectionItemContext) set for the duration of this render - no Page/Block row persisted per item (see README, "Item detail pages"); tries each "collection" block on the page independently, so only the one whose source resolves this item slug wins; @return array{0: ?Page, 1: ?string, 2: ?string}
    private function resolveCollectionDetail(string $slug): array
    {
        $lastSlash = strrpos($slug, '/');
        $parentPage = $this->pageService->findForDisplay(substr($slug, 0, $lastSlash));
        if (null === $parentPage) {
            return [null, null, null];
        }

        $itemSlug = substr($slug, $lastSlash + 1);

        foreach ($parentPage->getBlocks() as $block) {
            $detail = $this->renderCollectionDetail($block, $itemSlug);
            if (null !== $detail) {
                return [$parentPage, $detail[0], $detail[1]];
            }
        }

        return [null, null, null];
    }

    // One block's own attempt at the item slug - null as soon as anything doesn't line up (a block of another kind, an incomplete "collection" block, an item its source doesn't know, a detail page since unpublished), so the caller simply moves on to the next block; @return array{0: string, 1: ?string}|null - the rendered detail page and the item's own title
    private function renderCollectionDetail(Block $block, string $itemSlug): ?array
    {
        if ('collection' !== $block->getKind()) {
            return null;
        }

        $data = $block->getData();
        $source = $data['source'] ?? null;
        $detailPageSlug = $data['detailPage'] ?? null;
        if (null === $source || null === $detailPageSlug) {
            return null;
        }

        $itemData = $this->collectionSourceRegistry->detail($source, $itemSlug);
        if (null === $itemData) {
            return null;
        }

        $detailPage = $this->pageService->findForDisplay($detailPageSlug);
        if (null === $detailPage) {
            return null;
        }

        $this->collectionItemContext->set($itemData);

        return [
            $this->twig->render('@c975LSite/pages/_blocks.html.twig', ['blocks' => $detailPage->getBlocks()]),
            $itemData['title'] ?? null,
        ];
    }

    // PREVIEW
    #[Route(
        path: '/pages/{page}/preview',
        name: 'page_preview',
        requirements: [
            'page' => '^(?!pdf)([a-zA-Z0-9\-\/]+)',
        ],
        methods: ['GET'],
        priority: 1
    )]
    public function preview($page, Request $request)
    {
        $this->denyAccessUnlessGranted($this->configService->get('site-role-editor'));

        // Before anything is rendered: a preview has to show what was just saved, and its own html is not the public one - a "collection" block here builds its items' links against this very preview route (see BlockRenderContext)
        $this->blockRenderContext->disableCache();

        $slug = rtrim($page, '/');
        $pageObject = $this->pageService->findForDisplay($slug);
        $detailHtml = null;
        $detailTitle = null;

        // Same fallback as display(): lets an editor preview an unpublished Page's own collection detail views before publishing
        if (null === $pageObject && str_contains($slug, '/')) {
            [$pageObject, $detailHtml, $detailTitle] = $this->resolveCollectionDetail($slug);
        }

        if (null === $pageObject || $pageObject->isDeleted()) {
            throw $this->createNotFoundException();
        }

        return $this->render(
            '@c975LSite/pages/page.html.twig',
            [
                'page' => $pageObject,
                'isPreview' => true,
                'detailHtml' => $detailHtml,
                'detailTitle' => $detailTitle,
            ]
        )->setPrivate();
    }
}
