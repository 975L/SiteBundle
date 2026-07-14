<?php

namespace App\Tests\Controller;

use c975L\SiteBundle\Entity\Page;
use c975L\SiteBundle\Entity\Redirect;
use c975L\SiteBundle\Repository\PageRepository;
use c975L\SiteBundle\Repository\RedirectRepository;
use Doctrine\ORM\EntityManagerInterface;

class ContentAccessTest extends FunctionalTestCase
{
    private $client;

    public function setUp(): void
    {
        $this->client = $this->createAuthenticatedClient();
    }

    //Checks every published page is accessible ('home' redirects to the site root instead, see PageController::display())
    public function testAllPublishedPagesAreAccessible(): void
    {
        $pages = static::getContainer()->get(PageRepository::class)->findAllOrdered();
        $this->assertNotEmpty($pages, 'Aucune page publiée en base, le test ne couvre rien');

        $failures = [];
        foreach ($pages as $page) {
            $url = '/pages/' . $page->getSlug() . '/';
            $this->client->request('GET', $url);
            $status = $this->client->getResponse()->getStatusCode();
            $expectedStatus = 'home' === $page->getSlug() ? 301 : 200;
            if ($status !== $expectedStatus) {
                $failures[] = sprintf('%s -> %d (attendu %d)', $url, $status, $expectedStatus);
            }
        }

        $this->assertEmpty($failures, implode("\n", $failures));
    }

    //Checks every stored redirect points to its target, plus a fabricated temporary one
    //(no permanent=0 row currently exists, DAMA rolls this back after the test)
    public function testAllRedirectsPointToTheirTarget(): void
    {
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $temporaryRedirect = (new Redirect())
            ->setFromPath('/temporary-redirect-test')
            ->setToUrl('/')
            ->setPermanent(false)
        ;
        $entityManager->persist($temporaryRedirect);
        $entityManager->flush();

        $redirects = static::getContainer()->get(RedirectRepository::class)->findAll();
        $failures = [];
        foreach ($redirects as $redirect) {
            $this->client->request('GET', $redirect->getFromPath());
            $response = $this->client->getResponse();
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

    //Checks every deleted page returns 410 Gone: real ones (if any) plus a fabricated one, so this code
    //path is always exercised even on a site with no deleted page right now
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
        $failures = [];
        foreach ($deletedPages as $page) {
            $url = '/pages/' . $page->getSlug() . '/';
            $this->client->request('GET', $url);
            $status = $this->client->getResponse()->getStatusCode();
            if (410 !== $status) {
                $failures[] = sprintf('%s -> %d (attendu 410)', $url, $status);
            }
        }

        $this->assertEmpty($failures, implode("\n", $failures));
    }

    //Checks every draft (unpublished, not deleted) page returns 404: real ones (if any) plus a fabricated one,
    //so this code path is always exercised even once every real draft has been published
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
        $failures = [];
        foreach ($drafts as $page) {
            $url = '/pages/' . $page->getSlug() . '/';
            $this->client->request('GET', $url);
            $status = $this->client->getResponse()->getStatusCode();
            if (404 !== $status) {
                $failures[] = sprintf('%s -> %d (attendu 404)', $url, $status);
            }
        }

        $this->assertEmpty($failures, implode("\n", $failures));
    }
}
