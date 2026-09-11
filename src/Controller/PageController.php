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
use c975L\ConfigBundle\Service\LocalizedRouteNegotiator;
use c975L\SiteBundle\Entity\Page;
use c975L\SiteBundle\Service\PageServiceInterface;
use c975L\SiteBundle\Service\PageTranslator;
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
        private readonly LocalizedRouteNegotiator $negotiator,
        private readonly PageTranslator $pageTranslator,
    ) {
    }

    // A localised url answers only for a page that language was really written in: the routes exist for every language the site declares, but a page nobody translated has nothing to serve there but the writing language's own text under another "lang" attribute. Nothing links to those urls - they are declared neither in the head nor in the sitemap (see PagePublicUrlResolver::resolveAlternates()), and a menu writes the writing language's url for a page nobody translated (see MenuExtension::pageUrl()) - so a 404 is what they always should have answered, and a redirect would only buy a crawler a hop
    private function requireTranslated(Request $request, Page $page): void
    {
        if (!$this->negotiator->isTranslated($request, $this->pageTranslator->translatedLocales($page))) {
            throw $this->createNotFoundException();
        }
    }

    // The same route, in the language the request is being answered in. Without it a redirect - the home page, a trailing slash - would drop a visitor reading in one language back into the one the site was written in, and hand a crawler a redirect across languages.
    /**
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

    // The writing language's own urls, asked for in another language: the visitor is sent to that language's url instead. "/" and "/pages/{page}" are what the sitemap and the hreflang groups declare as the writing language's versions (see PagePublicUrlResolver::resolvePath()), so they may only ever answer in it. A visitor whose own language the site has been translated into is redirected to that language's url rather than served another language here, and anyone else is served the writing language for good - the locale is switched back to it, LocaleListener having already handed the translator whatever the browser asked for. Null when there is nothing to redirect to, which is every request of a site declaring a single language, every localised url, one having already said in its path which language it answers in, and every page this language was never written in - sending a visitor to an untranslated "/en/" would only hand them the French page under lang="en" (see PageTranslator::translatedLocales()).
    /** @param array<string, string> $parameters */
    private function writingLanguage(Request $request, Page $page, string $route, array $parameters = []): ?Response
    {
        return $this->negotiator->redirectToAskedLanguage($request, $this->pageTranslator->translatedLocales($page), $route, $parameters);
    }

    // What a non-prefixed url answers depends on the language the browser asks for - that language's url for a visitor the site has been translated for, the page itself for everyone else - which a shared cache has to be told, or the first visitor's answer would be handed to every one after them. The redirect carries it as much as the page does: a cached 302 would send every visitor into one visitor's language
    private function varyOnLanguage(Request $request, Response $response): Response
    {
        return $this->negotiator->vary($request, $response);
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

    // HOME. The same home page, in another language: the writing language keeps "/" byte for byte, the others go through "/{_locale}/". The pattern holds the languages the site declares beside the one it is written in, and matches nothing while there are none (see c975LSiteBundle::loadExtension()), so a single-language site only ever answers on the second
    #[Route(
        path: '/{_locale}/',
        name: 'page_home_localized',
        requirements: ['_locale' => '%c975l_config.locales_pattern%'],
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

            $this->requireTranslated($request, $homePage);

            $otherLanguage = $this->writingLanguage($request, $homePage, 'page_home');
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
            '_locale' => '%c975l_config.locales_pattern%',
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

        // The database collation finds a page whatever the case its slug is written in, so "/pages/About" would answer the very content of "/pages/about" - two urls for one page, and a way around an access rule a site writes on the stored slug. A 301 to the stored slug settles both, before anything of the page is rendered. A draft is left to the gate's 404, so the redirect never tells an unpublished slug exists
        if (null !== $pageObject && $pageObject->isPublished() && $pageObject->getSlug() !== $slug) {
            [$route, $parameters] = $this->sameLanguage('page_display', ['page' => (string) $pageObject->getSlug()]);

            return $this->redirectToRoute($route, $parameters, 301);
        }

        $detailHtml = null;
        $detailTitle = null;

        // No exact Page for this slug: the last segment may be a "collection" block's item slug, carried by the Page one level up (see resolveCollectionDetail())
        if (null === $pageObject && str_contains($slug, '/')) {
            $parentPage = $this->parentPageOf($slug);

            // Resolving the detail renders its blocks and their internal links (see UiBundle's BlockExtension::localizeLinks): the gate confirms the language the response is read in, and turns a deleted or unpublished page away, before anything of the detail is rendered
            if (null !== $parentPage) {
                $answered = $this->gate($request, $parentPage, $slug);
                if (null !== $answered) {
                    return $answered;
                }

                [$pageObject, $detailHtml, $detailTitle] = $this->resolveCollectionDetail($parentPage, $slug);
            }
        }

        if (null === $pageObject) {
            throw $this->createNotFoundException();
        }

        return $this->renderPage($request, $pageObject, $slug, $detailHtml, $detailTitle);
    }

    // What a page's url has to pass before anything of it is rendered: a deleted page is gone for good, an unpublished one was never there, and a request written in another language is answered in that one. A response back is the answer to return as is; null means the request may be served here. Called twice on an item detail - once in display() before the detail is rendered, once through renderPage() - the second run being the same computation on a page that has already passed
    private function gate(Request $request, Page $page, string $slug): ?Response
    {
        if ($page->isDeleted()) {
            throw new GoneHttpException();
        }
        if (!$page->isPublished()) {
            throw $this->createNotFoundException();
        }

        $this->requireTranslated($request, $page);

        $otherLanguage = $this->writingLanguage($request, $page, 'page_display', ['page' => $slug]);

        return null !== $otherLanguage ? $this->varyOnLanguage($request, $otherLanguage) : null;
    }

    // The page a slug resolved to, once it is one a visitor may read
    private function renderPage(Request $request, Page $pageObject, string $slug, ?string $detailHtml, ?string $detailTitle): Response
    {
        $answered = $this->gate($request, $pageObject, $slug);
        if (null !== $answered) {
            return $answered;
        }

        return $this->varyOnLanguage($request, $this->render(
            '@c975LSite/pages/page.html.twig',
            [
                'page' => $pageObject,
                'detailHtml' => $detailHtml,
                'detailTitle' => $detailTitle,
            ]
        ));
    }

    // The Page one level up from an item detail's slug, the one carrying the "collection" block that resolves its last segment
    private function parentPageOf(string $slug): ?Page
    {
        return $this->pageService->findForDisplay(substr($slug, 0, (int) strrpos($slug, '/')));
    }

    // Tries, on a parent page its caller has already found and gated, the slug's last segment as a "collection" block's item slug, resolved against the block's own source, then rendered via a separate Page (the block's "detailPage") whose own blocks render normally, with "collectionItem" (see CollectionItemContext) set for the duration of this render - no Page/Block row persisted per item (see README, "Item detail pages"); tries each "collection" block on the page independently, so only the one whose source resolves this item slug wins; @return array{0: ?Page, 1: ?string, 2: ?string}
    private function resolveCollectionDetail(Page $parentPage, string $slug): array
    {
        $itemSlug = substr($slug, (int) strrpos($slug, '/') + 1);

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

        // Same fallback as display(), minus its gate: a preview is exactly the screen for a page the public gates turn away, and none of its html is cached (see disableCache above)
        if (null === $pageObject && str_contains($slug, '/')) {
            $parentPage = $this->parentPageOf($slug);

            if (null !== $parentPage) {
                [$pageObject, $detailHtml, $detailTitle] = $this->resolveCollectionDetail($parentPage, $slug);
            }
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
