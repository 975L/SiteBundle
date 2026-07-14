<?php

namespace App\Tests\Controller;

class ResetPasswordControllerTest extends FunctionalTestCase
{
    public function testRequestPageIsSuccessful(): void
    {
        $client = $this->createAuthenticatedClient();
        $client->request('GET', '/reset-password');

        $this->assertResponseIsSuccessful();
    }

    public function testCheckEmailPageIsSuccessful(): void
    {
        $client = $this->createAuthenticatedClient();
        $client->request('GET', '/reset-password/check-email');

        $this->assertResponseIsSuccessful();
    }

    // Whether or not the email matches an account, the controller redirects to check-email
    // without revealing which - see ResetPasswordController::processSendingPasswordResetEmail()
    public function testSubmittingRequestFormRedirectsToCheckEmail(): void
    {
        $client = $this->createAuthenticatedClient();
        $crawler = $client->request('GET', '/reset-password');

        $form = $crawler->filter('form')->form();
        $formName = $form->getName();
        $form[$formName . '[email]'] = 'unknown-email@example.test';

        $client->submit($form);

        $this->assertResponseRedirects('/reset-password/check-email');
    }
}
