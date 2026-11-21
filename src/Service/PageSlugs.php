<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\SiteBundle\Service;

use c975L\ConfigBundle\Entity\Redirect;
use c975L\ConfigBundle\Repository\RedirectRepository;
use c975L\ConfigBundle\Service\SiteLocales;
use c975L\SiteBundle\Entity\Page;
use c975L\SiteBundle\Repository\PageRepository;
use c975L\UiBundle\Service\UniqueSlug;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\String\Slugger\SluggerInterface;

// A page's slug and the urls it is read at, kept in order wherever a page is written - the back office (see PageCrudController) and an agent (see SiteMcpProvider) - so neither can leave a url behind the other would have redirected
class PageSlugs
{
    // Page::$slug is VARCHAR(100): a generated base is cut so UniqueSlug's "-N" suffix still fits
    private const int SLUG_LENGTH = 100;

    // Room left by a slug base for that suffix
    private const int SLUG_SUFFIX_ROOM = 4;

    public function __construct(
        private readonly PageRepository $pageRepository,
        private readonly RedirectRepository $redirectRepository,
        private readonly SluggerInterface $slugger,
        private readonly SiteLocales $siteLocales,
        private readonly PageTranslator $pageTranslator,
    ) {
    }

    // New page - normalizes its slug and keeps it unique, then takes its urls back from any redirect left on them
    public function prepareNew(EntityManagerInterface $entityManager, Page $page): void
    {
        $this->normalize($page);
        $this->releasePaths($entityManager, $page);
    }

    // Updated page - resyncs the slug when the title changes, then creates a redirect from the old slug when it changes on an already published page
    public function prepareUpdate(EntityManagerInterface $entityManager, Page $page): void
    {
        $originalData = $entityManager->getUnitOfWork()->getOriginalEntityData($page);
        $originalSlug = $originalData['slug'] ?? null;
        $originalTitle = $originalData['title'] ?? null;
        // The state before the save, not what the page becomes in it
        $originalIsPublished = (bool) ($originalData['isPublished'] ?? false);

        // The home page's slug is fixed, its title can change without affecting it
        if ('home' !== $originalSlug && null !== $originalTitle && $originalTitle !== $page->getTitle()) {
            $page->setSlug((string) $page->getTitle());
        }

        $this->normalize($page);

        // An unpublished page was never served under its old slug, so renaming it leaves no url to redirect from
        if (null !== $originalSlug && $originalIsPublished && $originalSlug !== $page->getSlug()) {
            $this->redirectChange($entityManager, $page, $originalSlug, (string) $page->getSlug());
        }

        // Published or not: the day it goes online, its urls must reach it
        $this->releasePaths($entityManager, $page);
    }

    // Normalizes the slug entered by the user (removes accents, spaces, uppercase...) and appends -2, -3... when another page holds it: the UniqueEntity check ran on the slug as typed, or before a title change recomputed it
    public function normalize(Page $page): void
    {
        $slug = $page->getSlug();
        if (null !== $slug) {
            $page->setSlug($this->unique($slug, $page));
        }
    }

    // A slug no other page holds, its base cut so the suffix still fits the column
    public function unique(string $base, ?Page $page): string
    {
        return UniqueSlug::build(
            $this->slugger,
            mb_substr(strtolower($this->slugger->slug($base)->toString()), 0, self::SLUG_LENGTH - self::SLUG_SUFFIX_ROOM),
            fn (string $candidate): bool => null !== ($other = $this->pageRepository->findOneBy(['slug' => $candidate])) && $other->getId() !== $page?->getId()
        );
    }

    // The page's urls for that slug, keyed by language: the writing language's "/pages/{slug}", and "/{locale}/pages/{slug}" for every language it is translated in
    /** @return array<string, string> */
    public function paths(Page $page, string $slug): array
    {
        $default = $this->siteLocales->getDefaultLocale();
        $paths = [$default => '/pages/' . $slug];
        foreach ($this->pageTranslator->translatedLocales($page) as $locale) {
            if ($locale !== $default) {
                $paths[$locale] = '/' . $locale . '/pages/' . $slug;
            }
        }

        return $paths;
    }

    // The page wins over a redirect: one left on its urls by a removed page ("gone") or a renamed one would answer before the router ever reaches it (see RedirectSubscriber), and a redirect from the new slug back would loop (prestations -> prestations-2, then renamed back)
    private function releasePaths(EntityManagerInterface $entityManager, Page $page): void
    {
        foreach ($this->paths($page, (string) $page->getSlug()) as $path) {
            $redirect = $this->redirectRepository->findOneByFromPath($path);
            if (null !== $redirect) {
                $entityManager->remove($redirect);
            }
        }
    }

    // Redirects each old url of the page, in every language it is read in, to the new one, reusing an existing redirect if the old slug already had one
    private function redirectChange(EntityManagerInterface $entityManager, Page $page, string $oldSlug, string $newSlug): void
    {
        $toUrls = $this->paths($page, $newSlug);
        foreach ($this->paths($page, $oldSlug) as $locale => $fromPath) {
            $redirect = $this->redirectRepository->findOneByFromPath($fromPath)
                ?? new Redirect()->setFromPath($fromPath);

            $redirect
                ->setToUrl($toUrls[$locale])
                ->setPermanent(true);

            $entityManager->persist($redirect);
        }
    }
}
