<?php
/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\SiteBundle\Listener;

use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpFoundation\Response;
use Twig\Environment;

class MaintenanceListener
{
    private ?bool $maintenanceMode;
    private ?string $secretToken;

    public function __construct(
        ?bool $maintenanceMode,
        ?string $secretToken,
        private Environment $twig
    ) {
        $this->maintenanceMode = $maintenanceMode;
        $this->secretToken = $secretToken;
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest() || !$this->maintenanceMode) {
            return;
        }

        $request = $event->getRequest();

        // Accès via token in URL : ?t=secret_token
        if ($request->query->get('t') === $this->secretToken) {
            $request->getSession()->set('maintenance_access', true);
            return;
        }

        // Accès via session (valid token)
        if ($request->getSession()->get('maintenance_access')) {
            return;
        }

        // Otherwise maintenance page
        $html = $this->twig->render('@c975LSite/maintenance/index.html.twig');
        $event->setResponse(new Response($html, 503));
    }
}