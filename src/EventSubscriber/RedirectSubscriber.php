<?php
/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\SiteBundle\EventSubscriber;

use c975L\SiteBundle\Repository\RedirectRepository;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

class RedirectSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly RedirectRepository $redirectRepository
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        // Priority 33 runs before RouterListener (priority 32)
        return [KernelEvents::REQUEST => ['onKernelRequest', 33]];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $path = $event->getRequest()->getPathInfo();
        if ('/' === $path) {
            return;
        }

        $redirect = $this->redirectRepository->findOneByFromPath($path);
        if (null === $redirect) {
            return;
        }

        $status = $redirect->isPermanent() ? 301 : 302;
        $event->setResponse(new RedirectResponse($redirect->getToUrl(), $status));
    }
}
