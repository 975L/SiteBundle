<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\SiteBundle\Controller;

use c975L\ConfigBundle\Service\LocalizedUrlGenerator;
use c975L\SiteBundle\Repository\PageRepository;
use c975L\SiteBundle\Service\TutorialCatalog;
use c975L\SiteBundle\Service\TutorialCollectionSourceProvider;
use c975L\UiBundle\Service\FormPrefillHelper;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\Translation\TranslatorInterface;

// The two addresses the tutorial films need beside the page showing them, which is an ordinary page holding a "collection" block on the tutorials source: one a back office links a film with, one a visitor reports a step with
class TutorialController extends AbstractController
{
    public function __construct(
        private readonly TutorialCatalog $catalog,
        private readonly PageRepository $pageRepository,
        private readonly LocalizedUrlGenerator $localizedUrlGenerator,
    ) {
    }

    // FILM - what ConfigBundle's guided projects link their film with, knowing its slug and nothing of the page holding it. A project with no film lands on the tutorials page rather than on a 404: the link is written before the film is shot
    #[Route(
        path: '/tutorials/film/{slug}',
        name: 'site_tutorial_film',
        requirements: ['slug' => '[a-z0-9.-]+'],
        methods: ['GET'],
    )]
    public function film(Request $request, string $slug): Response
    {
        $page = $this->pageRepository->findOneByCollectionSource(TutorialCollectionSourceProvider::SOURCE);
        if (null === $page) {
            throw $this->createNotFoundException();
        }

        $url = $this->localizedUrlGenerator->path('page_display', ['page' => $page->getSlug()]);

        return $this->redirect(null === $this->catalog->find($slug, $request->getLocale()) ? $url : $url . '#' . $slug);
    }

    // REPORT - the contact form, its subject filled in with the film and the step. Step 0 stands for the whole film. The subject is written here rather than taken from the url, so a report always names a film and a step that exist
    #[Route(
        path: '/tutorials/{slug}/report/{step}',
        name: 'site_tutorial_report',
        requirements: ['slug' => '[a-z0-9.-]+', 'step' => '\d+'],
        methods: ['GET'],
    )]
    public function report(Request $request, FormPrefillHelper $prefillHelper, TranslatorInterface $translator, string $slug, int $step): Response
    {
        $tutorial = $this->catalog->find($slug, $request->getLocale());
        $contact = $this->pageRepository->findOneByFormBlockName(TutorialCollectionSourceProvider::CONTACT_FORM);
        if (null === $tutorial || null === $contact || $step > \count($tutorial['steps'])) {
            throw $this->createNotFoundException();
        }

        // Written in the visitor's language, who reads it in the contact form before sending it
        $where = 0 === $step
            ? $translator->trans('label.tutorial_report_subject_whole', [], 'site')
            : $translator->trans('label.tutorial_report_subject_step', ['%number%' => $step, '%label%' => $tutorial['steps'][$step - 1]['label']], 'site');
        $prefillHelper->prefill($request, TutorialCollectionSourceProvider::CONTACT_FORM, [
            'subject' => $translator->trans('label.tutorial_report_subject', [
                '%slug%' => $tutorial['slug'],
                '%locale%' => $tutorial['locale'],
                '%date%' => date('d/m/Y', $tutorial['version']),
                '%where%' => $where,
            ], 'site'),
        ]);

        return $this->redirect($this->localizedUrlGenerator->path('page_display', ['page' => $contact->getSlug()]));
    }
}
