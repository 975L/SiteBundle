<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\SiteBundle\Tests\Controller\Management;

use c975L\ConfigBundle\Management\GuidedProjectBuilder;
use c975L\SiteBundle\Controller\Management\TutorialFilmController;
use c975L\SiteBundle\Service\TutorialCatalog;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

class TutorialFilmControllerTest extends TestCase
{
    use ControllerContainerTestTrait;

    private string $private;

    protected function setUp(): void
    {
        $this->private = sys_get_temp_dir() . '/tutorial-film-' . uniqid();
        mkdir($this->private . '/medias/films/fr', 0o775, true);
        file_put_contents($this->private . '/medias/films/fr/films.json', json_encode(['back-office' => ['version' => 1759000000]]));
        file_put_contents($this->private . '/medias/films/fr/back-office.webm', 'webm');
    }

    protected function tearDown(): void
    {
        new Filesystem()->remove($this->private);
    }

    // $granted stands for what GuidedProjectBuilder::isGranted() answers for the film's project, $backOffice for the back office's own gate
    private function controller(bool $granted = true, bool $backOffice = true): TutorialFilmController
    {
        $builder = $this->createStub(GuidedProjectBuilder::class);
        $builder->method('isGranted')->willReturn($granted);

        $controller = new TutorialFilmController($builder, new TutorialCatalog($builder, sys_get_temp_dir(), 'fr', $this->private));
        $controller->setContainer($this->createContainer(['security.authorization_checker' => $this->createAuthorizationChecker($backOffice)]));

        return $controller;
    }

    // The film is read off private/ and served with its own content type, revalidated on each reading
    public function testTheFilmIsServedToWhoeverHoldsItsProjectRole(): void
    {
        $response = $this->controller()->film(new Request(), 'fr', 'back-office', 'webm');

        $this->assertSame($this->private . '/medias/films/fr/back-office.webm', $response->getFile()->getPathname());
        $this->assertSame('video/webm', $response->headers->get('Content-Type'));
        $this->assertTrue($response->headers->hasCacheControlDirective('no-cache'));
        $this->assertTrue($response->headers->hasCacheControlDirective('private'));
    }

    // A browser holding the file as it stands gets a 304, once the role is checked again
    public function testAnUnchangedFilmIsNotSentAgain(): void
    {
        $request = new Request(server: ['HTTP_IF_MODIFIED_SINCE' => gmdate('D, d M Y H:i:s', (int) filemtime($this->private . '/medias/films/fr/back-office.webm')) . ' GMT']);

        $this->assertSame(304, $this->controller()->film($request, 'fr', 'back-office', 'webm')->getStatusCode());
    }

    // The project's role is what keeps a film from the accounts it was not filmed for
    public function testAProjectOutOfTheUserReachHasNoFilm(): void
    {
        $this->expectException(NotFoundHttpException::class);

        $this->controller(false)->film(new Request(), 'fr', 'back-office', 'webm');
    }

    // The url names the very file served: a locale without its own film does not get the site's
    public function testALocaleWithoutItsOwnFilmIsNotFound(): void
    {
        $this->expectException(NotFoundHttpException::class);

        $this->controller()->film(new Request(), 'en', 'back-office', 'webm');
    }

    // A file the film lacks (here its subtitles) is a 404, not a 500
    public function testAMissingFileIsNotFound(): void
    {
        $this->expectException(NotFoundHttpException::class);

        $this->controller()->film(new Request(), 'fr', 'back-office', 'vtt');
    }

    // The back office's own gate comes before any project's role
    public function testNobodyOutsideTheBackOfficeIsServed(): void
    {
        $this->expectException(AccessDeniedException::class);

        $this->controller(true, false)->film(new Request(), 'fr', 'back-office', 'webm');
    }
}
