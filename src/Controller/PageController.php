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
use c975L\SiteBundle\Entity\Page;
use c975L\SiteBundle\Management\PageTemplateApplier;
use c975L\SiteBundle\Management\PageTemplateRegistry;
use c975L\SiteBundle\Management\SiteThemePresetProvider;
use c975L\SiteBundle\Service\PageServiceInterface;
use c975L\SiteBundle\Twig\CollectionItemContext;
use c975L\UiBundle\Registry\CollectionSourceRegistry;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\GoneHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Twig\Environment;
/**
 * Main Site Controller class
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
        private readonly ?SiteThemePresetProvider $themePresetProvider = null,
        private readonly ?PageTemplateRegistry $pageTemplateRegistry = null,
        private readonly PageTemplateApplier $pageTemplateApplier = new PageTemplateApplier(),
    ) {
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

//HOME
    #[Route(
        path: '/',
        name: 'page_home',
        methods: ['GET']
    )]
    public function home()
    {
        $homePage = $this->pageService->findOneBySlug('home');
        if ($homePage) {
            return $this->render(
                '@c975LSite/pages/page.html.twig',
                ['page' => $homePage]
            );
        }

        throw $this->createNotFoundException();
    }

// REDIRECT PAGES POST, PUT, PATCH REQUESTS
    #[Route(
        path: '/pages/{page}',
        name: 'redirect_pages_wrong_methods',
        requirements: [
            'page' => '^(?!pdf)([a-zA-Z0-9\-\/]+)'
        ],
        methods: ['POST', 'PUT', 'PATCH']
    )]
    public function redirectPagesWrongMethods()
    {
        // 303 tells the client to replay the request as GET, unlike 301/302 which are meant to preserve the method
        return $this->redirectToRoute('page_home', [], 303);
    }

//DISPLAY
    #[Route(
        path: '/pages/{page}',
        name: 'page_display',
        requirements: [
            'page' => '^(?!pdf)([a-zA-Z0-9\-\/]+)'
        ],
        methods: ['GET']
    )]
    public function display($page)
    {
        $slug = rtrim($page, '/');

        // The home page only has one canonical URL: the site root
        if ('home' === $slug) {
            return $this->redirectToRoute('page_home', [], 301);
        }

        $pageObject = $this->pageService->findForDisplay($slug);
        $detailHtml = null;
        $detailTitle = null;

        // No exact Page for this slug: the last segment may still be an item slug of a "collection"
        // block carried by the Page one level up (e.g. /pages/vitrine-blocks/ui) - see
        // resolveCollectionDetail() for why this needs no Page/Block row per item
        if (null === $pageObject && str_contains($slug, '/')) {
            [$pageObject, $detailHtml, $detailTitle] = $this->resolveCollectionDetail($slug);
        }

        if ($pageObject) {
            if ($pageObject->isDeleted()) {
                throw new GoneHttpException();
            }
            if (!$pageObject->isPublished()) {
                throw $this->createNotFoundException();
            }
            return $this->render(
                '@c975LSite/pages/page.html.twig',
                ['page' => $pageObject, 'detailHtml' => $detailHtml, 'detailTitle' => $detailTitle]
            );
        }

        throw $this->createNotFoundException();
    }

    // Tries the slug's last "/" segment as an item slug of a "collection" block on the Page found at
    // everything before it - e.g. "vitrine-blocks/ui" resolves against the real "vitrine-blocks" Page's
    // collection source, then renders a SEPARATE, real Page (the collection block's own "detailPage"
    // slug) as this item's detail view: that Page's own blocks render normally, with "collectionItem"
    // (see CollectionItemContext) set to the item's data for the duration of this render - not just a
    // single hardcoded template, any block on that Page can use it. No Page/Block row is persisted per
    // item - see SiteBundle's README ("Item detail pages", under "Collection entries"). Tries each
    // "collection" block on the page independently (a page can carry several, each with its own
    // source/detailPage) rather than merging their fields, so only the block whose source actually
    // resolves this item slug wins.
    // @return array{0: ?Page, 1: ?string, 2: ?string}
    private function resolveCollectionDetail(string $slug): array
    {
        $lastSlash = strrpos($slug, '/');
        $parentPage = $this->pageService->findForDisplay(substr($slug, 0, $lastSlash));
        if (null === $parentPage) {
            return [null, null, null];
        }

        $itemSlug = substr($slug, $lastSlash + 1);

        foreach ($parentPage->getBlocks() as $block) {
            if ('collection' !== $block->getKind()) {
                continue;
            }

            $data = $block->getData();
            $source = $data['source'] ?? null;
            $detailPageSlug = $data['detailPage'] ?? null;
            if (null === $source || null === $detailPageSlug) {
                continue;
            }

            $itemData = $this->collectionSourceRegistry->detail($source, $itemSlug);
            if (null === $itemData) {
                continue;
            }

            $detailPage = $this->pageService->findForDisplay($detailPageSlug);
            if (null === $detailPage) {
                continue;
            }

            $this->collectionItemContext->set($itemData);
            $html = $this->twig->render('@c975LSite/pages/_blocks.html.twig', ['blocks' => $detailPage->getBlocks()]);

            return [$parentPage, $html, $itemData['title'] ?? null];
        }

        return [null, null, null];
    }

// PREVIEW
    #[Route(
        path: '/pages/{page}/preview',
        name: 'page_preview',
        requirements: [
            'page' => '^(?!pdf)([a-zA-Z0-9\-\/]+)'
        ],
        methods: ['GET'],
        priority: 1
    )]
    public function preview($page, Request $request)
    {
        $this->denyAccessUnlessGranted($this->configService->get('site-role-editor'));

        $slug = rtrim($page, '/');
        $pageObject = $this->pageService->findForDisplay($slug);
        $detailHtml = null;
        $detailTitle = null;

        // Same fallback as display(): lets an editor preview an unpublished Page's own collection
        // detail views (e.g. /pages/vitrine-blocks/ui/preview) before publishing
        if (null === $pageObject && str_contains($slug, '/')) {
            [$pageObject, $detailHtml, $detailTitle] = $this->resolveCollectionDetail($slug);
        }

        if (null === $pageObject || $pageObject->isDeleted()) {
            throw $this->createNotFoundException();
        }

        // Optional ?preset=<slug>: previews a theme preset's colors/fonts/shape for this request only
        // (see templates/pages/page.html.twig), without writing anything to site_config - lets an
        // editor judge a site-wide preset's effect before committing to it via "Apply preset"
        $previewPreset = $this->themePresetProvider?->getPresets()[(string) $request->query->get('preset')] ?? null;

        // A preset can point to a page-template whose blocks demo its full look (see
        // SiteThemePresetProvider) - built here as transient, unpersisted Blocks so this request's
        // render shows the preset's intended arrangement without touching the real page's content
        $previewBlocks = null;
        if (null !== $previewPreset && null !== $previewPreset['pageTemplate']) {
            $template = $this->pageTemplateRegistry?->get($previewPreset['pageTemplate']);
            if (null !== $template) {
                $previewBlocks = $this->pageTemplateApplier->build($template);
            }
        }

        return $this->render(
            '@c975LSite/pages/page.html.twig',
            [
                'page' => $pageObject,
                'isPreview' => true,
                'previewPreset' => $previewPreset,
                'previewBlocks' => $previewBlocks,
                'detailHtml' => $detailHtml,
                'detailTitle' => $detailTitle,
            ]
        )->setPrivate();
    }
}