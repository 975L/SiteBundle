<?php
/*
 * (c) 2018: 975L <contact@975l.com>
 * (c) 2018: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\SiteBundle\Controller;

use c975L\ConfigBundle\Service\ConfigServiceInterface;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

/**
 * Main Controller class
 * @author Laurent Marquet <laurent.marquet@laposte.net>
 * @copyright 2018 975L <contact@975l.com>
 */
class SiteController extends Controller
{
//DASHBOARD

    /**
     * Displays the dashboard
     * @return Response
     * @throws AccessDeniedException
     *
     * @Route("/site/dashboard",
     *      name="site_dashboard")
     * @Method({"GET", "HEAD"})
     */
    public function dashboard(Request $request)
    {
        $this->denyAccessUnlessGranted('c975LSite-dashboard', null);

        return $this->render('@c975LSite/pages/dashboard.html.twig');
    }

//CONFIG

    /**
     * Displays the configuration
     * @return Response
     * @throws AccessDeniedException
     *
     * @Route("/site/config",
     *      name="site_config")
     * @Method({"GET", "HEAD", "POST"})
     */
    public function config(Request $request, ConfigServiceInterface $configService)
    {
        $this->denyAccessUnlessGranted('c975LSite-config', null);

        //Defines form
        $form = $configService->createForm('c975l/site-bundle');
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            //Validates config
            $configService->setConfig($form);

            //Redirects
            return $this->redirectToRoute('site_dashboard');
        }

        //Renders the config form
        return $this->render('@c975LConfig/forms/config.html.twig', array(
            'form' => $form->createView(),
            'toolbar' => '@c975LSite',
        ));
    }
}
