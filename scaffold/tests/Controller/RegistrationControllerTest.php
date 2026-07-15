<?php

namespace App\Tests\Controller;

use App\Entity\User;
use c975L\ConfigBundle\Entity\Config;
use c975L\ConfigBundle\Service\ConfigServiceInterface;
use Doctrine\ORM\EntityManagerInterface;

class RegistrationControllerTest extends FunctionalTestCase
{
    // Forces "user-registration-enabled" to a known state for the duration of the test, instead of
    // reading whatever is currently configured in the (mutable, environment-specific) database - a
    // config-dependent branch left untested is a bug hiding place, not a shortcut. DAMA rolls the
    // change back after the test, the real config is never actually touched.
    // Called after createClient() (kernel already booted), never before.
    private function forceRegistrationEnabled(bool $enabled): void
    {
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $config = $entityManager->getRepository(Config::class)->findOneBy(['slug' => 'user-registration-enabled']);
        $config->setValue($enabled ? 'true' : 'false');
        $entityManager->flush();

        static::getContainer()->get(ConfigServiceInterface::class)->invalidateCache();
    }

    public function testRegisterPageIsSuccessfulWhenRegistrationEnabled(): void
    {
        $client = $this->createAuthenticatedClient();
        $this->forceRegistrationEnabled(true);

        $client->request('GET', '/register');

        $this->assertResponseIsSuccessful();
    }

    public function testRegisterPageReturns403WhenRegistrationDisabled(): void
    {
        $client = $this->createAuthenticatedClient();
        $this->forceRegistrationEnabled(false);

        $client->request('GET', '/register');

        $this->assertResponseStatusCodeSame(403);
    }

    public function testSubmittingRegistrationFormRedirectsHome(): void
    {
        $client = $this->createAuthenticatedClient();
        $this->forceRegistrationEnabled(true);

        $crawler = $client->request('GET', '/register');
        $this->backdateFormTimer($client, 'registration_started_at');

        $form = $crawler->filter('form')->form();
        $formName = $form->getName();

        // gmail.com resolves (has MX records), unlike the previous "example.test" - the DnsEmail
        // constraint on User::$email now rejects non-resolvable domains
        $form[$formName . '[email]'] = 'new-registration@gmail.com';
        $form[$formName . '[plainPassword][plainPassword]'] = 'Str0ngPassword!';
        $form[$formName . '[plainPassword][confirmPassword]'] = 'Str0ngPassword!';
        $form[$formName . '[cgu]'] = '1';
        if ($form->has($formName . '[gdpr]')) {
            $form[$formName . '[gdpr]'] = '1';
        }

        $client->submit($form);

        $this->assertResponseRedirects();
        $this->assertNotNull(
            static::getContainer()->get(EntityManagerInterface::class)
                ->getRepository(User::class)
                ->findOneBy(['email' => 'new-registration@gmail.com']),
            'The user should have been persisted.'
        );
    }

    // ".invalid" is reserved by RFC 2606 to never resolve - exercises the DnsEmail
    // constraint (App\Validator\Constraints\DnsEmail) wired on User::$email
    public function testSubmittingRegistrationFormWithUnresolvableEmailDomainReRendersForm(): void
    {
        $client = $this->createAuthenticatedClient();
        $this->forceRegistrationEnabled(true);

        $crawler = $client->request('GET', '/register');
        $this->backdateFormTimer($client, 'registration_started_at');

        $form = $crawler->filter('form')->form();
        $formName = $form->getName();

        $form[$formName . '[email]'] = 'bot@definitely-not-a-real-domain-xyz123.invalid';
        $form[$formName . '[plainPassword][plainPassword]'] = 'Str0ngPassword!';
        $form[$formName . '[plainPassword][confirmPassword]'] = 'Str0ngPassword!';
        $form[$formName . '[cgu]'] = '1';

        $client->submit($form);

        // AbstractController::render() sets a 422 status when it detects an invalid form
        // among the passed parameters - the form is re-rendered, not a plain 200
        $this->assertResponseIsUnprocessable();
    }

    // A filled honeypot field ("website") means a bot filled every input blindly - the
    // controller must silently redirect without creating any account
    public function testHoneypotFilledDoesNotCreateAccount(): void
    {
        $client = $this->createAuthenticatedClient();
        $this->forceRegistrationEnabled(true);

        $crawler = $client->request('GET', '/register');
        $this->backdateFormTimer($client, 'registration_started_at');

        $form = $crawler->filter('form')->form();
        $formName = $form->getName();

        $form[$formName . '[email]'] = 'bot-honeypot@gmail.com';
        $form[$formName . '[plainPassword][plainPassword]'] = 'Str0ngPassword!';
        $form[$formName . '[plainPassword][confirmPassword]'] = 'Str0ngPassword!';
        $form[$formName . '[cgu]'] = '1';
        if ($form->has($formName . '[gdpr]')) {
            $form[$formName . '[gdpr]'] = '1';
        }
        $form[$formName . '[website]'] = 'https://spam.example';

        $client->submit($form);

        $this->assertResponseRedirects();
        $this->assertNull(
            static::getContainer()->get(EntityManagerInterface::class)
                ->getRepository(User::class)
                ->findOneBy(['email' => 'bot-honeypot@gmail.com']),
            'A honeypot-filled submission must not create an account.'
        );
    }

    // Submitting right after the form was displayed (no time elapsed, unlike every other test
    // here which calls backdateRegistrationTimer()) looks like a scripted bot, not a human
    public function testSubmittingTooQuicklyDoesNotCreateAccount(): void
    {
        $client = $this->createAuthenticatedClient();
        $this->forceRegistrationEnabled(true);

        $crawler = $client->request('GET', '/register');

        $form = $crawler->filter('form')->form();
        $formName = $form->getName();

        $form[$formName . '[email]'] = 'too-fast@gmail.com';
        $form[$formName . '[plainPassword][plainPassword]'] = 'Str0ngPassword!';
        $form[$formName . '[plainPassword][confirmPassword]'] = 'Str0ngPassword!';
        $form[$formName . '[cgu]'] = '1';
        if ($form->has($formName . '[gdpr]')) {
            $form[$formName . '[gdpr]'] = '1';
        }

        $client->submit($form);

        $this->assertResponseRedirects();
        $this->assertNull(
            static::getContainer()->get(EntityManagerInterface::class)
                ->getRepository(User::class)
                ->findOneBy(['email' => 'too-fast@gmail.com']),
            'A submission faster than the minimum delay must not create an account.'
        );
    }
}
