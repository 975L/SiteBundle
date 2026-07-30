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
use c975L\SiteBundle\Service\PageServiceInterface;
use c975L\SiteBundle\Twig\CollectionItemContext;
use c975L\UiBundle\Entity\Block;
use c975L\UiBundle\Registry\CollectionSourceRegistry;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
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

    // HOME
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
        path: '/pages/{page}',
        name: 'page_display',
        requirements: [
            'page' => '^(?!pdf)([a-zA-Z0-9\-\/]+)',
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

        // No exact Page for this slug: the last segment may be a "collection" block's item slug, carried by the Page one level up (see resolveCollectionDetail())
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
