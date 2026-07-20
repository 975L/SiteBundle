<?php

namespace App\Tests\Controller;

use c975L\SiteBundle\Entity\Page;
use c975L\UiBundle\Entity\Block;
use Doctrine\ORM\EntityManagerInterface;

// Registration itself (the "register" Form/FormAction) is now covered by RegisterFormActionTest - this controller only keeps the signed email-verification link, see RegistrationController
class RegistrationControllerTest extends FunctionalTestCase
{
    // The real "Créer un compte" Page (see DefaultPagesImporter) normally carries the "register" form Block - unpublishing it within the test transaction (rolled back after) genuinely reproduces redirectAfterVerification()'s fallback to the home page
    private function unpublishRegisterPage(EntityManagerInterface $entityManager): void
    {
        $page = $entityManager->getRepository(Page::class)->findOneByFormBlockName('register');
        $page?->setIsPublished(false);
        $entityManager->flush();
    }

    public function testVerifyUserEmailRedirectsWhenIdIsMissing(): void
    {
        $client = $this->createAuthenticatedClient();
        $this->unpublishRegisterPage(static::getContainer()->get(EntityManagerInterface::class));

        $client->request('GET', '/verification/email');

        $this->assertResponseRedirects('/');
    }

    public function testVerifyUserEmailRedirectsWhenUserIsNotFound(): void
    {
        $client = $this->createAuthenticatedClient();
        $this->unpublishRegisterPage(static::getContainer()->get(EntityManagerInterface::class));

        $client->request('GET', '/verification/email?id=999999');

        $this->assertResponseRedirects('/');
    }

    // Once a real Page carries the "register" form Block (see PageRepository::findOneByFormBlockName()), redirectAfterVerification() sends the visitor there instead of the bare home page
    public function testVerifyUserEmailRedirectsToThePageCarryingTheRegisterFormBlockWhenUserIsNotFound(): void
    {
        $client = $this->createAuthenticatedClient();

        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $block = (new Block())->setKind('form')->setData(['name' => 'register']);
        $page = (new Page())
            ->setTitle('Create an account')
            ->setSlug('create-an-account-test')
            ->setCreation(new \DateTime())
            ->setModification(new \DateTime())
            ->setIsPublished(true)
        ;
        $page->addBlock($block);
        $entityManager->persist($page);
        $entityManager->flush();

        $client->request('GET', '/verification/email?id=999999');

        $this->assertResponseRedirects('/pages/create-an-account-test');
    }
}
