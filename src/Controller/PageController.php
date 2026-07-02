<?php
/*
 * (c) 2025: 975L <contact@975l.com>
 * (c) 2025: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\SiteBundle\Controller;

use c975L\SiteBundle\Service\PageServiceInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpKernel\Exception\GoneHttpException;
use Symfony\Component\Routing\Attribute\Route;
/**
 * Main Site Controller class
 * @author Laurent Marquet <laurent.marquet@laposte.net>
 * @copyright 2026 975L <contact@975l.com>
 */
class PageController extends AbstractController
{

    public function __construct(
        private readonly PageServiceInterface $pageService
    ) {
    }

//HOME
    #[Route(
        '/',
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
            )->setMaxAge(3600);
        }

        return $this->render(
            'pages/home.html.twig', ['pages' => $this->pageService->findAll()],
        )->setMaxAge(3600);
    }

// REDIRECT HOME
    #[Route(
        '/pages',
        name: 'page_redirect_home',
        methods: ['GET']
    )]
    public function redirectPages()
    {
        return $this->redirectToRoute('page_home');
    }

// REDIRECT POST REQUESTS
    #[Route(
        '/',
        name: 'redirect_home_post',
        methods: ['POST']
    )]
    public function redirectIndexPost()
    {
        return $this->redirectToRoute('page_home');
    }

//DISPLAY
    #[Route(
        '/pages/{page}',
        name: 'page_display',
        requirements: [
            'page' => '^(?!pdf)([a-zA-Z0-9\-\/]+)'
        ],
        methods: ['GET']
    )]
    #[Route( // Kept for backward compatibility with former c975L/PageEditBundle
        '/pages/{page}',
        name: 'pageedit_display',
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

        if ($pageObject) {
            if ($pageObject->isDeleted()) {
                throw new GoneHttpException();
            }
            if (!$pageObject->isPublished()) {
                throw $this->createNotFoundException();
            }

            return $this->render(
                '@c975LSite/pages/page.html.twig',
                ['page' => $pageObject]
            )->setMaxAge(3600);
        }

        throw $this->createNotFoundException();
    }
}