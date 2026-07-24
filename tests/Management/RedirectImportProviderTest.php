<?php
/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\SiteBundle\Tests\Management;

use c975L\SiteBundle\Entity\Redirect;
use c975L\SiteBundle\Management\RedirectImportProvider;
use c975L\SiteBundle\Repository\RedirectRepository;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;

class RedirectImportProviderTest extends TestCase
{
    private function createRedirectRepository(?Redirect $existingRedirect = null): RedirectRepository
    {
        $repository = $this->createStub(RedirectRepository::class);
        $repository->method('findOneByFromPath')->willReturn($existingRedirect);

        return $repository;
    }

    public function testSupportsImportOnlyMatchesSiteRedirectKind(): void
    {
        $provider = new RedirectImportProvider($this->createStub(EntityManagerInterface::class), $this->createRedirectRepository());

        $this->assertTrue($provider->supportsImport('site_redirect'));
        $this->assertFalse($provider->supportsImport('site_page'));
    }

    public function testImportCreatesANewRedirect(): void
    {
        $persisted = [];
        $em = $this->createStub(EntityManagerInterface::class);
        $em->method('persist')->willReturnCallback(static function (object $entity) use (&$persisted): void {
            $persisted[] = $entity;
        });

        $provider = new RedirectImportProvider($em, $this->createRedirectRepository());

        $result = $provider->import([[
            'fromPath' => '/old-page',
            'toUrl' => '/new-page',
            'permanent' => true,
        ]]);

        $this->assertSame(['created' => 1, 'updated' => 0], $result);
        $this->assertSame('/old-page', $persisted[0]->getFromPath());
        $this->assertSame('/new-page', $persisted[0]->getToUrl());
        $this->assertTrue($persisted[0]->isPermanent());
    }

    public function testImportOverwritesAnExistingRedirect(): void
    {
        $existing = (new Redirect())->setFromPath('/old-page')->setToUrl('/somewhere')->setPermanent(false);

        $provider = new RedirectImportProvider($this->createStub(EntityManagerInterface::class), $this->createRedirectRepository($existing));

        $result = $provider->import([[
            'fromPath' => '/old-page',
            'toUrl' => '/new-page',
            'permanent' => true,
        ]]);

        $this->assertSame(['created' => 0, 'updated' => 1], $result);
        $this->assertSame('/new-page', $existing->getToUrl());
        $this->assertTrue($existing->isPermanent());
    }
}
