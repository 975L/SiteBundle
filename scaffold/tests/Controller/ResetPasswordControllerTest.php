<?php

namespace App\Tests\Controller;

use Symfony\Bundle\FrameworkBundle\Test\MailerAssertionsTrait;

class ResetPasswordControllerTest extends FunctionalTestCase
{
    use MailerAssertionsTrait;

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
        $this->backdateFormTimer($client, 'reset_password_started_at');

        $form = $crawler->filter('form')->form();
        $formName = $form->getName();
        $form[$formName . '[email]'] = 'unknown-email@example.test';
        if ($form->has($formName . '[gdpr]')) {
            $form[$formName . '[gdpr]'] = '1';
        }

        $client->submit($form);

        $this->assertResponseRedirects('/reset-password/check-email');
    }

    // A filled honeypot field ("website") must still redirect to check-email (same response as
    // a real request, no hint given to the bot) without ever calling the mailer
    public function testHoneypotFilledRedirectsToCheckEmailWithoutSendingMail(): void
    {
        $client = $this->createAuthenticatedClient();
        $crawler = $client->request('GET', '/reset-password');
        $this->backdateFormTimer($client, 'reset_password_started_at');

        $form = $crawler->filter('form')->form();
        $formName = $form->getName();
        $form[$formName . '[email]'] = $this->authenticatedUser->getEmail();
        if ($form->has($formName . '[gdpr]')) {
            $form[$formName . '[gdpr]'] = '1';
        }
        $form[$formName . '[website]'] = 'https://spam.example';

        $client->submit($form);

        $this->assertResponseRedirects('/reset-password/check-email');
        $this->assertQueuedEmailCount(0);
    }

    // Submitting right after the form was displayed (no time elapsed, unlike the tests above
    // which call backdateFormTimer()) looks like a scripted bot, not a human
    public function testSubmittingTooQuicklyRedirectsWithoutSendingMail(): void
    {
        $client = $this->createAuthenticatedClient();
        $crawler = $client->request('GET', '/reset-password');

        $form = $crawler->filter('form')->form();
        $formName = $form->getName();
        $form[$formName . '[email]'] = $this->authenticatedUser->getEmail();
        if ($form->has($formName . '[gdpr]')) {
            $form[$formName . '[gdpr]'] = '1';
        }

        $client->submit($form);

        $this->assertResponseRedirects('/reset-password/check-email');
        $this->assertQueuedEmailCount(0);
    }
}
