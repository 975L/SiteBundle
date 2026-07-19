<?php

namespace App\Tests\Controller;

use c975L\SiteBundle\Entity\Page;
use c975L\UiBundle\Entity\Block;
use Doctrine\ORM\EntityManagerInterface;

// The reset-password *request* (the "reset_password_request" Form/FormAction) is now covered by
// ResetPasswordRequestFormActionTest - this controller only keeps the signed reset-token link, see ResetPasswordController
class ResetPasswordControllerTest extends FunctionalTestCase
{
    // No token stored in session (e.g. /reset-password/reset/{token} visited a second time, after the
    // token-in-URL redirect already consumed it into session, then the session itself expired)
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

    // A token that was never really issued by ResetPasswordHelper (e.g. tampered/expired) fails validateTokenAndFetchUser() - redirectToRequestPage()'s fallback then sends the visitor to the home page, since no Page currently carries the "reset_password_request" form Block
    public function testResetRedirectsToHomeWhenTokenIsInvalid(): void
    {
        $client = $this->createAuthenticatedClient();

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
