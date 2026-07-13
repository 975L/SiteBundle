<?php
/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\SiteBundle\Tests\EventSubscriber;

use c975L\SiteBundle\Entity\Redirect;
use c975L\SiteBundle\EventSubscriber\RedirectSubscriber;
use c975L\SiteBundle\Repository\RedirectRepository;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\KernelEvents;

// Lives under src/Tests (not a sibling tests/ dir) so it stays autoloadable by consuming apps,
// whose attribute route loader recursively reflects every class under the bundle root
class RedirectSubscriberTest extends TestCase
{
    private function createEvent(string $path, bool $isMainRequest = true): RequestEvent
    {
        $kernel = $this->createStub(HttpKernelInterface::class);
        $requestType = $isMainRequest ? HttpKernelInterface::MAIN_REQUEST : HttpKernelInterface::SUB_REQUEST;

        return new RequestEvent($kernel, Request::create($path), $requestType);
    }

    // Runs before RouterListener (priority 33 > 32) so a redirect can short-circuit routing entirely
    public function testGetSubscribedEventsRunsBeforeRouterListener(): void
    {
        $this->assertSame([KernelEvents::REQUEST => ['onKernelRequest', 33]], RedirectSubscriber::getSubscribedEvents());
    }

    // A path matching a stored redirect gets a response set, with the status matching its permanent flag
    public function testOnKernelRequestSetsPermanentRedirectResponse(): void
    {
        $redirect = (new Redirect())->setFromPath('/old')->setToUrl('/new')->setPermanent(true);
        $repository = $this->createStub(RedirectRepository::class);
        $repository->method('findOneByFromPath')->willReturn($redirect);
        $subscriber = new RedirectSubscriber($repository);
        $event = $this->createEvent('/old');

        $subscriber->onKernelRequest($event);

        $response = $event->getResponse();
        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertSame('/new', $response->getTargetUrl());
        $this->assertSame(301, $response->getStatusCode());
    }

    // A non-permanent redirect yields a 302 instead of a 301
    public function testOnKernelRequestSetsTemporaryRedirectResponse(): void
    {
        $redirect = (new Redirect())->setFromPath('/old')->setToUrl('/new')->setPermanent(false);
        $repository = $this->createStub(RedirectRepository::class);
        $repository->method('findOneByFromPath')->willReturn($redirect);
        $subscriber = new RedirectSubscriber($repository);
        $event = $this->createEvent('/old');

        $subscriber->onKernelRequest($event);

        $this->assertSame(302, $event->getResponse()->getStatusCode());
    }

    // No matching redirect: no response is set, request proceeds normally
    public function testOnKernelRequestDoesNothingWhenNoRedirectMatches(): void
    {
        $repository = $this->createStub(RedirectRepository::class);
        $repository->method('findOneByFromPath')->willReturn(null);
        $subscriber = new RedirectSubscriber($repository);
        $event = $this->createEvent('/unknown');

        $subscriber->onKernelRequest($event);

        $this->assertNull($event->getResponse());
    }

    // The homepage ('/') is never looked up, avoiding a pointless query on the hottest path
    public function testOnKernelRequestSkipsHomepage(): void
    {
        $repository = $this->createMock(RedirectRepository::class);
        $repository->expects($this->never())->method('findOneByFromPath');
        $subscriber = new RedirectSubscriber($repository);
        $event = $this->createEvent('/');

        $subscriber->onKernelRequest($event);

        $this->assertNull($event->getResponse());
    }

    // Sub-requests (e.g. ESI/fragments) are ignored entirely
    public function testOnKernelRequestIgnoresSubRequests(): void
    {
        $repository = $this->createMock(RedirectRepository::class);
        $repository->expects($this->never())->method('findOneByFromPath');
        $subscriber = new RedirectSubscriber($repository);
        $event = $this->createEvent('/old', isMainRequest: false);

        $subscriber->onKernelRequest($event);

        $this->assertNull($event->getResponse());
    }
}
