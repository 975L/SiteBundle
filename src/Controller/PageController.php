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
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\GoneHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
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
    /**
     * Redirects to page_home
     * @return Redirect
     */
    #[Route(
        '/pages',
        name: 'page_redirect_home',
        methods: ['GET']
    )]
    public function redirectPages()
    {
        return $this->redirectToRoute('page_home');
    }

    /**
     * Displays the homepage
     * @return Response
     */
    #[Route(
        '/',
        name: 'page_home',
        methods: ['GET']
    )]
    #[Route( // Kept for backward compatibility with former c975L/PageEditBundle
        '/',
        name: 'pageedit_home',
        methods: ['GET']
    )]
    public function home()
    {
        return $this->render(
            'pages/home.html.twig', ['pages' => $this->pageService->findAll()],
        )->setMaxAge(3600);
    }

//DISPLAY REQUESTED PAGE
    /**
     * Displays the page
     * @return Response
     * @throws AccessDeniedException
     * @throws NotFoundHttpException
     */
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
        $pageFolder = $this->getParameter('kernel.project_dir') . '/templates/';

        $page =  rtrim($page, '/') . '.html.twig';

        // Displays page
        if (is_file($pageFolder . 'pages/' .$page)) {
            return $this->render(
                'pages/' . $page
            )->setMaxAge(3600);
        // Redirected
        } elseif (is_file($pageFolder . 'pages/redirected/' . $page)) {
            return $this->redirectToRoute('page_display', ['page' => trim(file_get_contents($pageFolder . 'pages/redirected/' . $page))]);
        // Deleted
        } elseif (is_file($pageFolder . 'pages/deleted/' . $page)) {
            throw new GoneHttpException();
        // Page in ORM
        } else {
            $pageObject = $this->pageService->findOneBySlug($page);
            if ($pageObject) {
                return $this->render(
                        '@c975LSite/pages/page.html.twig',
                        ['page' => $pageObject]
                    )->setMaxAge(3600);
            }
        }

        throw $this->createNotFoundException();
    }
}