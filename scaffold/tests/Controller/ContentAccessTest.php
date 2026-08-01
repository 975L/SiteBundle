<?php

namespace App\Tests\Controller;

use c975L\SiteBundle\Entity\Page;
use c975L\SiteBundle\Entity\Redirect;
use c975L\SiteBundle\Repository\PageRepository;
use c975L\SiteBundle\Repository\RedirectRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;

class ContentAccessTest extends FunctionalTestCase
{
    private KernelBrowser $client;

    public function setUp(): void
    {
        $this->client = $this->createAuthenticatedClient();
    }

    // Checks every published page is accessible on its canonical (slashless) url ('home' redirects to the site root instead, see PageController::display())
    public function testAllPublishedPagesAreAccessible(): void
    {
        $pages = static::getContainer()->get(PageRepository::class)->findAllOrdered();
        $this->assertNotEmpty($pages, 'Aucune page publiée en base, le test ne couvre rien');

        $failures = [];
        foreach ($pages as $page) {
            $url = '/pages/' . $page->getSlug();
            $this->client->request('GET', $url);
            $status = $this->client->getResponse()->getStatusCode();
            $expectedStatus = 'home' === $page->getSlug() ? 301 : 200;
            if ($status !== $expectedStatus) {
                $failures[] = sprintf('%s -> %d (attendu %d)', $url, $status, $expectedStatus);
            }
        }

        $this->assertEmpty($failures, implode("\n", $failures));
    }

    // Checks the trailing slash form redirects to the canonical url instead of serving the same content twice, on the first published page that is not 'home' (which reaches the site root in one hop instead, see PageController::display())
    public function testTrailingSlashRedirectsToCanonicalUrl(): void
    {
        foreach (static::getContainer()->get(PageRepository::class)->findAllOrdered() as $page) {
            if ('home' === $page->getSlug()) {
                continue;
            }

            $url = '/pages/' . $page->getSlug();
            $this->client->request('GET', $url . '/');
            $response = $this->client->getResponse();

            $this->assertSame(301, $response->getStatusCode());
            $this->assertSame($url, $response->headers->get('Location'));

            return;
        }

        $this->fail('Aucune page publiée en base, le test ne couvre rien');
    }

    // Checks every stored redirect answers as declared, plus fabricated temporary and "gone" ones so both code paths are always exercised even on a site with no such row right now (DAMA rolls them back after the test)
    public function testAllRedirectsPointToTheirTarget(): void
    {
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $temporaryRedirect = (new Redirect())
            ->setFromPath('/temporary-redirect-test')
            ->setToUrl('/')
            ->setPermanent(false)
        ;
        $entityManager->persist($temporaryRedirect);
        $goneRedirect = (new Redirect())
            ->setFromPath('/temporary-gone-test')
            ->setGone(true)
        ;
        $entityManager->persist($goneRedirect);
        $entityManager->flush();

        $redirects = static::getContainer()->get(RedirectRepository::class)->findAll();
        $failures = [];
        foreach ($redirects as $redirect) {
            $this->client->request('GET', $redirect->getFromPath());
            $response = $this->client->getResponse();

            // A "gone" row has no target at all: the subscriber throws a GoneHttpException instead of redirecting (see RedirectSubscriber::onKernelRequest())
            if ($redirect->isGone()) {
                if (410 !== $response->getStatusCode()) {
                    $failures[] = sprintf('%s -> %d (attendu 410)', $redirect->getFromPath(), $response->getStatusCode());
                }

                continue;
            }

            $expectedStatus = $redirect->isPermanent() ? 301 : 302;
            if ($response->getStatusCode() !== $expectedStatus || $response->headers->get('Location') !== $redirect->getToUrl()) {
                $failures[] = sprintf(
                    '%s -> %d %s (attendu %d %s)',
                    $redirect->getFromPath(),
                    $response->getStatusCode(),
                    $response->headers->get('Location'),
                    $expectedStatus,
                    $redirect->getToUrl()
                );
            }
        }

        $this->assertEmpty($failures, implode("\n", $failures));
    }

    // Checks every deleted page returns 410 Gone: real ones (if any) plus a fabricated one, so this code path is always exercised even on a site with no deleted page right now. A deleted page whose url is covered by a redirect row is skipped, see isCoveredByRedirect()
    public function testDeletedPagesReturn410(): void
    {
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $temporaryPage = (new Page())
            ->setTitle('Temporary deleted page')
            ->setSlug('temporary-deleted-page-test')
            ->setCreation(new \DateTime())
            ->setModification(new \DateTime())
            ->setIsPublished(true)
            ->setIsDeleted(true)
        ;
        $entityManager->persist($temporaryPage);
        $entityManager->flush();

        $deletedPages = $entityManager->getRepository(Page::class)->findBy(['isDeleted' => true]);
        $redirects = static::getContainer()->get(RedirectRepository::class)->findAll();
        $failures = [];
        foreach ($deletedPages as $page) {
            $url = '/pages/' . $page->getSlug();
            if ($this->isCoveredByRedirect($url, $redirects)) {
                continue;
            }

            $this->client->request('GET', $url);
            $status = $this->client->getResponse()->getStatusCode();
            if (410 !== $status) {
                $failures[] = sprintf('%s -> %d (attendu 410)', $url, $status);
            }
        }

        $this->assertEmpty($failures, implode("\n", $failures));
    }

    // Checks every draft (unpublished, not deleted) page returns 404: real ones (if any) plus a fabricated one, so this code path is always exercised even once every real draft has been published. A draft whose url is covered by a redirect row is skipped, see isCoveredByRedirect()
    public function testUnpublishedPagesReturn404(): void
    {
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $temporaryPage = (new Page())
            ->setTitle('Temporary draft page')
            ->setSlug('temporary-draft-page-test')
            ->setCreation(new \DateTime())
            ->setModification(new \DateTime())
            ->setIsPublished(false)
            ->setIsDeleted(false)
        ;
        $entityManager->persist($temporaryPage);
        $entityManager->flush();

        $drafts = $entityManager->getRepository(Page::class)->findBy(['isPublished' => false, 'isDeleted' => false]);
        $redirects = static::getContainer()->get(RedirectRepository::class)->findAll();
        $failures = [];
        foreach ($drafts as $page) {
            $url = '/pages/' . $page->getSlug();
            if ($this->isCoveredByRedirect($url, $redirects)) {
                continue;
            }

            $this->client->request('GET', $url);
            $status = $this->client->getResponse()->getStatusCode();
            if (404 !== $status) {
                $failures[] = sprintf('%s -> %d (attendu 404)', $url, $status);
            }
        }

        $this->assertEmpty($failures, implode("\n", $failures));
    }

    // Tells whether a redirect row answers for the url, exactly or through a "*" prefix one. The RedirectSubscriber runs before the router (priority 33), so such a page never reaches PageController and answers with the redirect instead of its own 410/404 - which is the point of the row, the content moved somewhere else. Those rows are covered on their own by testAllRedirectsPointToTheirTarget()
    /** @param Redirect[] $redirects */
    private function isCoveredByRedirect(string $url, array $redirects): bool
    {
        foreach ($redirects as $redirect) {
            $fromPath = (string) $redirect->getFromPath();
            if ($fromPath === $url) {
                return true;
            }

            if (str_ends_with($fromPath, '*') && str_starts_with($url, rtrim($fromPath, '*'))) {
                return true;
            }
        }

        return false;
    }
}
