<?php

namespace App\Tests\Controller;

use c975L\SiteBundle\Entity\Page;
use c975L\SiteBundle\Repository\PageRepository;
use c975L\UiBundle\Entity\Block;
use Doctrine\ORM\EntityManagerInterface;

// This controller only keeps the signed reset-token link, the request itself being covered elsewhere
class ResetPasswordControllerTest extends FunctionalTestCase
{
    // No token in session, the redirect having consumed it and the session then expired
    public function testResetThrowsNotFoundWhenNoTokenIsStoredInSession(): void
    {
        $client = $this->createAuthenticatedClient();

        $client->request('GET', '/reset-password/reset');

        $this->assertResponseStatusCodeSame(404);
    }

    // A token in the URL is stored in session and stripped from the URL via redirect, before ever being validated
    public function testResetWithATokenInTheUrlRedirectsToStripIt(): void
    {
        $client = $this->createAuthenticatedClient();

        $client->request('GET', '/reset-password/reset/some-token');

        $this->assertResponseRedirects('/reset-password/reset');
    }

    // A token that was never really issued by ResetPasswordHelper (e.g. tampered/expired) fails validateTokenAndFetchUser() - redirectToRequestPage()'s fallback then sends the visitor to the home page. The real "Mot de passe oublié" Page (see DefaultPagesImporter) normally carries the "reset_password_request" form Block - unpublishing it within the test transaction (rolled back after) genuinely reproduces that fallback
    public function testResetRedirectsToHomeWhenTokenIsInvalid(): void
    {
        $client = $this->createAuthenticatedClient();

        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $page = static::getContainer()->get(PageRepository::class)->findOneByFormBlockName('reset_password_request');
        $page?->setIsPublished(false);
        $entityManager->flush();

        $client->request('GET', '/reset-password/reset/some-invalid-token');
        $client->request('GET', '/reset-password/reset');

        $this->assertResponseRedirects('/');
    }

    // Once a real Page carries the "reset_password_request" form Block (see PageRepository::findOneByFormBlockName()), redirectToRequestPage() sends the visitor there instead of the bare home page
    public function testResetRedirectsToThePageCarryingTheResetPasswordRequestFormBlockWhenTokenIsInvalid(): void
    {
        $client = $this->createAuthenticatedClient();

        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $block = (new Block())->setKind('form')->setData(['name' => 'reset_password_request']);
        $page = (new Page())
            ->setTitle('Forgot password')
            ->setSlug('forgot-password-test')
            ->setCreation(new \DateTime())
            ->setModification(new \DateTime())
            ->setIsPublished(true)
        ;
        $page->addBlock($block);
        $entityManager->persist($page);
        $entityManager->flush();

        $client->request('GET', '/reset-password/reset/some-invalid-token');
        $client->request('GET', '/reset-password/reset');

        $this->assertResponseRedirects('/pages/forgot-password-test');
    }
}
