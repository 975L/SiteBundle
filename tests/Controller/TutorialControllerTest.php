<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\SiteBundle\Tests\Controller;

use c975L\ConfigBundle\Service\LocalizedUrlGenerator;
use c975L\SiteBundle\Controller\TutorialController;
use c975L\SiteBundle\Entity\Page;
use c975L\SiteBundle\Repository\PageRepository;
use c975L\SiteBundle\Service\TutorialCatalog;
use c975L\UiBundle\Service\FormPrefillHelper;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Contracts\Translation\TranslatorInterface;

class TutorialControllerTest extends TestCase
{
    private const array FILM = ['slug' => 'site-page-creation', 'locale' => 'fr', 'version' => 1758000000, 'steps' => [['label' => 'Ouvrir', 'start' => 7.4], ['label' => 'Enregistrer', 'start' => 16.4]]];

    private function page(string $slug): Page
    {
        return new Page()->setSlug($slug);
    }

    private function controller(?Page $tutorials, ?Page $contact, bool $filmed = true): TutorialController
    {
        $catalog = $this->createStub(TutorialCatalog::class);
        $catalog->method('find')->willReturn($filmed ? self::FILM : null);

        $pageRepository = $this->createStub(PageRepository::class);
        $pageRepository->method('findOneByCollectionSource')->willReturn($tutorials);
        $pageRepository->method('findOneByFormBlockName')->willReturn($contact);

        $urlGenerator = $this->createStub(LocalizedUrlGenerator::class);
        $urlGenerator->method('path')->willReturnCallback(static fn (string $route, array $parameters): string => '/pages/' . $parameters['page']);

        return new TutorialController($catalog, $pageRepository, $urlGenerator);
    }

    // The back office's film link lands on the film itself, on whichever page holds the tutorials
    public function testTheFilmLinkOpensTheFilmOnTheTutorialsPage(): void
    {
        $response = $this->controller($this->page('tutoriels'), null)->film(new Request(), 'site-page-creation');

        $this->assertSame('/pages/tutoriels#site-page-creation', $response->headers->get('Location'));
    }

    // A project not filmed yet lands on the page rather than on a 404: its link is written before the film is shot
    public function testAProjectWithNoFilmLandsOnThePage(): void
    {
        $response = $this->controller($this->page('tutoriels'), null, false)->film(new Request(), 'site-trash');

        $this->assertSame('/pages/tutoriels', $response->headers->get('Location'));
    }

    public function testNoTutorialsPageIsANotFound(): void
    {
        $this->expectException(NotFoundHttpException::class);

        $this->controller(null, null)->film(new Request(), 'site-page-creation');
    }

    // The contact form opens with a subject naming the film and the step, written here rather than read from the url
    public function testAReportFillsTheContactFormSubject(): void
    {
        $prefill = $this->createMock(FormPrefillHelper::class);
        $prefill->expects($this->once())->method('prefill')->with($this->anything(), 'contact', ['subject' => 'subject:site-page-creation:step:2']);

        $translator = $this->createStub(TranslatorInterface::class);
        $translator->method('trans')->willReturnCallback(static fn (string $key, array $parameters): string => match ($key) {
            'label.tutorial_report_subject' => 'subject:' . $parameters['%slug%'] . ':' . $parameters['%where%'],
            'label.tutorial_report_subject_step' => 'step:' . $parameters['%number%'],
            default => $key,
        });

        $response = $this->controller(null, $this->page('contact'))->report(new Request(), $prefill, $translator, 'site-page-creation', 2);

        $this->assertSame('/pages/contact', $response->headers->get('Location'));
    }

    // A step the film does not have, or a site with no contact form, has nothing to report to
    public function testAReportNeedsAnExistingStepAndAContactPage(): void
    {
        $translator = $this->createStub(TranslatorInterface::class);
        $prefill = $this->createStub(FormPrefillHelper::class);

        foreach ([[$this->page('contact'), 3], [null, 1]] as [$contact, $step]) {
            try {
                $this->controller(null, $contact)->report(new Request(), $prefill, $translator, 'site-page-creation', $step);
                $this->fail('A report with nothing to report to was accepted.');
            } catch (NotFoundHttpException) {
                $this->addToAssertionCount(1);
            }
        }
    }
}
