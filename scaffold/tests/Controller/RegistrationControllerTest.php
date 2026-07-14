<?php

namespace App\Tests\Controller;

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

        $form = $crawler->filter('form')->form();
        $formName = $form->getName();

        $form[$formName . '[email]'] = 'new-registration@example.test';
        $form[$formName . '[plainPassword][plainPassword]'] = 'Str0ngPassword!';
        $form[$formName . '[plainPassword][confirmPassword]'] = 'Str0ngPassword!';
        $form[$formName . '[cgu]'] = '1';

        $client->submit($form);

        $this->assertResponseRedirects();
    }
}
