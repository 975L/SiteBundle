<?php
/*
 * (c) 2025: 975L <contact@975l.com>
 * (c) 2025: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\SiteBundle\Controller;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\HttpKernel\Exception\GoneHttpException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Main Site Controller class
 * @author Laurent Marquet <laurent.marquet@laposte.net>
 * @copyright 2025 975L <contact@975l.com>
 */
class PageController extends AbstractController
{
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
            'pages/home.html.twig',
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
        //Redirected
        } elseif (is_file($pageFolder . 'pages/redirected/' . $page)) {
            return $this->redirectToRoute('page_display', ['page' => trim(file_get_contents($pageFolder . 'pages/redirected/' . $page))]);
        //Deleted
        } elseif (is_file($pageFolder . 'pages/deleted/' . $page)) {
            throw new GoneHttpException();
        }

        throw $this->createNotFoundException();
    }
}