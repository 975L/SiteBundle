<?php
/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */
namespace c975L\SiteBundle\Controller\Management;

use c975L\ConfigBundle\Service\ConfigServiceInterface;
use c975L\SiteBundle\Command\MessengerCleanupCommand;
use c975L\SiteBundle\Service\MessengerFailedMessageService;
use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminRoute;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Contracts\Translation\TranslatorInterface;

class MessengerFailedController extends AbstractController
{
    // EasyAdmin prefixes this with the Dashboard's own route name, giving management_XXX
    public const PURGE_ROUTE = 'management_site_messenger_failed_purge';

    public function __construct(
        private readonly MessengerFailedMessageService $messengerFailedMessageService,
        private readonly MessengerCleanupCommand $messengerCleanupCommand,
        private readonly ConfigServiceInterface $configService,
        private readonly TranslatorInterface $translator,
    ) {
    }

    // Shows the failed Messenger messages in a readable form to ROLE_SUPER_ADMIN;
    // ROLE_ADMIN only sees a reassuring "already signaled" message, no technical detail
    #[AdminRoute(
        path: '/site/messenger-failed',
        name: 'site_messenger_failed',
    )]
    public function index(): Response
    {
        $this->denyAccessUnlessGranted($this->configService->get('site-role-admin'));

        return $this->render('@c975LSite/management/messenger_failed_index.html.twig', [
            'messages' => $this->messengerFailedMessageService->findAll(),
        ]);
    }

    // Runs the cleanup command immediately (purge + digest email if new important failures exist)
    #[AdminRoute(
        path: '/site/messenger-failed/purge',
        name: 'site_messenger_failed_purge',
        options: ['methods' => ['POST']]
    )]
    public function purgeNow(Request $request): RedirectResponse
    {
        $this->denyAccessUnlessGranted('ROLE_SUPER_ADMIN');

        if ($this->isCsrfTokenValid(self::PURGE_ROUTE, $request->request->get('_token'))) {
            $stats = $this->messengerCleanupCommand->cleanup();
            $this->addFlash('success', $this->translator->trans(
                'flash.messenger_purged',
                ['%count%' => $stats['purged']],
                'site',
            ));
        }

        return $this->redirectToRoute('management_site_messenger_failed');
    }
}
