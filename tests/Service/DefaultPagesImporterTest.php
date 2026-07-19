<?php
/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\SiteBundle\Tests\Service;

use c975L\SiteBundle\Entity\Page;
use c975L\SiteBundle\Repository\PageRepository;
use c975L\SiteBundle\Service\DefaultPagesImporter;
use c975L\UiBundle\Entity\EmailTemplate;
use c975L\UiBundle\Entity\Form;
use c975L\UiBundle\Entity\FormField;
use c975L\UiBundle\Repository\EmailTemplateRepository;
use c975L\UiBundle\Repository\FormRepository;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\Security;

class DefaultPagesImporterTest extends TestCase
{
    // Builds an EntityManager double that records every persisted entity into $persisted
    private function createEntityManager(array &$persisted): EntityManagerInterface
    {
        $em = $this->createStub(EntityManagerInterface::class);
        $em->method('persist')->willReturnCallback(static function (object $entity) use (&$persisted): void {
            $persisted[] = $entity;
        });

        return $em;
    }

    // Builds a PageRepository double whose findOneBy() answers according to $existingSlugs
    private function createPageRepository(array $existingSlugs = []): PageRepository
    {
        $repository = $this->createStub(PageRepository::class);
        $repository->method('findOneBy')->willReturnCallback(
            static function (array $criteria) use ($existingSlugs): ?Page {
                return \in_array($criteria['slug'], $existingSlugs, true) ? new Page() : null;
            }
        );

        return $repository;
    }

    // Builds a FormRepository double - findOneBy(['name' => ...]) reports a Form as already existing (restricted, with the given action already set) for any name present as a key in $existingForms (name => action)
    private function createFormRepository(array $existingForms = []): FormRepository
    {
        $repository = $this->createStub(FormRepository::class);
        $repository->method('findOneBy')->willReturnCallback(
            static function (array $criteria) use ($existingForms): ?Form {
                if (!\array_key_exists($criteria['name'], $existingForms)) {
                    return null;
                }

                return (new Form())->setName($criteria['name'])->setRestricted(true)->setAction($existingForms[$criteria['name']]);
            }
        );

        return $repository;
    }

    // Builds an EmailTemplateRepository double - findOneBy(['name' => ...]) reports an EmailTemplate as already existing for any name listed in $existingNames
    private function createEmailTemplateRepository(array $existingNames = []): EmailTemplateRepository
    {
        $repository = $this->createStub(EmailTemplateRepository::class);
        $repository->method('findOneBy')->willReturnCallback(
            static function (array $criteria) use ($existingNames): ?EmailTemplate {
                return \in_array($criteria['name'], $existingNames, true) ? new EmailTemplate() : null;
            }
        );

        return $repository;
    }

    // Builds the importer with a repository/entity-manager pair and no logged-in user
    private function createImporter(
        PageRepository $pageRepository,
        EntityManagerInterface $em,
        string $defaultLocale = 'fr',
        array $enabledLocales = ['fr'],
        ?FormRepository $formRepository = null,
        ?EmailTemplateRepository $emailTemplateRepository = null,
    ): DefaultPagesImporter {
        $security = $this->createStub(Security::class);
        $security->method('getUser')->willReturn(null);

        return new DefaultPagesImporter(
            $em,
            $pageRepository,
            $formRepository ?? $this->createFormRepository(),
            $emailTemplateRepository ?? $this->createEmailTemplateRepository(),
            $security,
            $defaultLocale,
            $enabledLocales,
        );
    }

    // A brand-new fr install has no pages yet: every definition but the ShopBundle-gated one is created
    public function testImportCreatesAllDefinitionsForDefaultLocaleWhenNoneExist(): void
    {
        $persisted = [];
        $repository = $this->createPageRepository();
        $importer = $this->createImporter($repository, $this->createEntityManager($persisted));

        $result = $importer->import();

        // terms-of-sales is gated behind c975L\ShopBundle, not installed here, so it's neither created nor skipped
        $this->assertSame(['created' => 9, 'skipped' => 0], $result);
        $pages = array_values(array_filter($persisted, static fn ($entity) => $entity instanceof Page));
        $this->assertCount(9, $pages);
        $this->assertSame('home', $pages[0]->getSlug());
    }

    // Re-running the import on a site that already has every page, Form and EmailTemplate must not duplicate anything
    public function testImportSkipsPagesAlreadyPresentInDatabase(): void
    {
        $existingSlugs = ['home', 'mentions-legales', 'regles-de-confidentialite', 'conditions-generales-d-utilisation', 'cookies', 'copyright', 'creer-un-compte', 'mot-de-passe-oublie', 'contact'];
        $persisted = [];
        $repository = $this->createPageRepository($existingSlugs);
        $formRepository = $this->createFormRepository(['register' => 'register', 'reset_password_request' => 'reset_password_request', 'contact' => 'send_email']);
        $emailTemplateRepository = $this->createEmailTemplateRepository(['contact_notification', 'account_validation', 'password_reset']);
        $importer = $this->createImporter($repository, $this->createEntityManager($persisted), formRepository: $formRepository, emailTemplateRepository: $emailTemplateRepository);

        $result = $importer->import();

        $this->assertSame(['created' => 0, 'skipped' => 9], $result);
        $this->assertSame([], $persisted);
    }

    // Definitions carrying a legal 'model' get an attached legal_model block pre-filled with today's date
    public function testImportAttachesLegalModelBlockToLegalPages(): void
    {
        $persisted = [];
        $repository = $this->createPageRepository();
        $importer = $this->createImporter($repository, $this->createEntityManager($persisted));

        $importer->import();

        $legalNotice = current(array_filter($persisted, static fn ($page) => $page instanceof Page && 'mentions-legales' === $page->getSlug()));
        $this->assertNotFalse($legalNotice);
        $this->assertCount(1, $legalNotice->getBlocks());
        $this->assertSame('legal_model', $legalNotice->getBlocks()->first()->getKind());
        $this->assertSame('france/legal-notice', $legalNotice->getBlocks()->first()->getData()['model']);
    }

    // register/reset-password pages get a generic "form" Block pointing at the matching Form by name - same mechanism as "contact", not a dedicated Block kind (see RegisterFormAction/ResetPasswordRequestFormAction)
    public function testImportAttachesRegisterAndResetPasswordBlocks(): void
    {
        $persisted = [];
        $repository = $this->createPageRepository();
        $importer = $this->createImporter($repository, $this->createEntityManager($persisted));

        $importer->import();

        $register = current(array_filter($persisted, static fn ($page) => $page instanceof Page && 'creer-un-compte' === $page->getSlug()));
        $this->assertNotFalse($register);
        $this->assertSame('form', $register->getBlocks()->first()->getKind());
        $this->assertSame('register', $register->getBlocks()->first()->getData()['name']);

        $resetPassword = current(array_filter($persisted, static fn ($page) => $page instanceof Page && 'mot-de-passe-oublie' === $page->getSlug()));
        $this->assertNotFalse($resetPassword);
        $this->assertSame('form', $resetPassword->getBlocks()->first()->getKind());
        $this->assertSame('reset_password_request', $resetPassword->getBlocks()->first()->getData()['name']);
    }

    // Finds a persisted Form by name - "current(array_filter(...))" alone would just grab whichever Form happens to be seeded first, and register/reset_password_request/contact are now all seeded in the same import
    private function findPersistedForm(array $persisted, string $name): ?Form
    {
        foreach ($persisted as $entity) {
            if ($entity instanceof Form && $name === $entity->getName()) {
                return $entity;
            }
        }

        return null;
    }

    // Same as findPersistedForm() above, for the EmailTemplate rows seeded alongside register/reset_password_request/contact
    private function findPersistedEmailTemplate(array $persisted, string $name): ?EmailTemplate
    {
        foreach ($persisted as $entity) {
            if ($entity instanceof EmailTemplate && $name === $entity->getName()) {
                return $entity;
            }
        }

        return null;
    }

    // The contact page gets a generic "form" Block pointing at the "contact" Form by name, and seeds that Form (restricted core fields, send_email action) since it doesn't require c975l/contactform-bundle to be installed
    public function testImportAttachesFormBlockToContactPageAndSeedsTheContactForm(): void
    {
        $persisted = [];
        $repository = $this->createPageRepository();
        $importer = $this->createImporter($repository, $this->createEntityManager($persisted));

        $importer->import();

        $contact = current(array_filter($persisted, static fn ($page) => $page instanceof Page && 'contact' === $page->getSlug()));
        $this->assertNotFalse($contact);
        $this->assertSame('form', $contact->getBlocks()->first()->getKind());
        $this->assertSame('contact', $contact->getBlocks()->first()->getData()['name']);

        $form = $this->findPersistedForm($persisted, 'contact');
        $this->assertNotNull($form);
        $this->assertSame('send_email', $form->getAction());
        $this->assertTrue($form->isRestricted());
        $this->assertSame(['senderEmailField' => 'email', 'offerReceiveCopy' => true, 'template' => '@c975LSite/emails/contact_notification.html.twig'], $form->getActionConfig());
        $this->assertCount(4, $form->getFields());
    }

    // Re-running the import must not touch the "contact" Form if it already exists with the expected action (e.g. c975l/contactform-bundle's own DefaultFormsImporter already created it)
    public function testImportDoesNotReSeedContactFormWhenAlreadyExisting(): void
    {
        $persisted = [];
        $repository = $this->createPageRepository(['contact']);
        $importer = $this->createImporter($repository, $this->createEntityManager($persisted), formRepository: $this->createFormRepository(['contact' => 'send_email']));

        $importer->import();

        $this->assertNull($this->findPersistedForm($persisted, 'contact'));
    }

    // A "contact" Form seeded by an earlier version of this bundle, or by hand, without the expected action gets backfilled in place - not re-created, not left stale
    public function testImportBackfillsContactFormActionWhenStale(): void
    {
        $persisted = [];
        $repository = $this->createPageRepository(['contact']);
        $importer = $this->createImporter($repository, $this->createEntityManager($persisted), formRepository: $this->createFormRepository(['contact' => null]));

        $importer->import();

        $form = $this->findPersistedForm($persisted, 'contact');
        $this->assertNotNull($form);
        $this->assertSame('send_email', $form->getAction());
    }

    // The register/reset-password-request pages each seed their own restricted Form too, processed by the matching FormActionInterface key (RegisterFormAction/ResetPasswordRequestFormAction, scaffold's own), same mechanism as "contact"'s send_email
    public function testImportSeedsTheRegisterAndResetPasswordRequestForms(): void
    {
        $persisted = [];
        $repository = $this->createPageRepository();
        $importer = $this->createImporter($repository, $this->createEntityManager($persisted));

        $importer->import();

        $register = $this->findPersistedForm($persisted, 'register');
        $this->assertNotNull($register);
        $this->assertTrue($register->isRestricted());
        $this->assertSame('register', $register->getAction());
        $this->assertCount(3, $register->getFields());
        $fieldNames = array_map(static fn ($field) => $field->getName(), $register->getFields()->toArray());
        $this->assertSame(['email', 'plainPassword', 'cgu'], $fieldNames);
        $cgu = $register->getFields()->toArray()[2];
        $this->assertSame('/pages/conditions-generales-d-utilisation', $cgu->getUrl());

        $resetPasswordRequest = $this->findPersistedForm($persisted, 'reset_password_request');
        $this->assertNotNull($resetPasswordRequest);
        $this->assertTrue($resetPasswordRequest->isRestricted());
        $this->assertSame('reset_password_request', $resetPasswordRequest->getAction());
        $this->assertCount(1, $resetPasswordRequest->getFields());
    }

    // Re-running the import must not touch "register"/"reset_password_request" if they already exist with the expected action
    public function testImportDoesNotReSeedRegisterOrResetPasswordRequestFormsWhenAlreadyExisting(): void
    {
        $persisted = [];
        $repository = $this->createPageRepository(['creer-un-compte', 'mot-de-passe-oublie']);
        $importer = $this->createImporter($repository, $this->createEntityManager($persisted), formRepository: $this->createFormRepository(['register' => 'register', 'reset_password_request' => 'reset_password_request']));

        $importer->import();

        $this->assertNull($this->findPersistedForm($persisted, 'register'));
        $this->assertNull($this->findPersistedForm($persisted, 'reset_password_request'));
    }

    // A "register"/"reset_password_request" Form seeded before they gained their own action (see UPGRADE.md) gets backfilled in place on the very next import, even though their pages already exist - no manual DB fix-up needed on an upgrading site
    public function testImportBackfillsRegisterAndResetPasswordRequestFormActionsWhenStale(): void
    {
        $persisted = [];
        $repository = $this->createPageRepository(['creer-un-compte', 'mot-de-passe-oublie']);
        $importer = $this->createImporter($repository, $this->createEntityManager($persisted), formRepository: $this->createFormRepository(['register' => null, 'reset_password_request' => null]));

        $importer->import();

        $this->assertSame('register', $this->findPersistedForm($persisted, 'register')?->getAction());
        $this->assertSame('reset_password_request', $this->findPersistedForm($persisted, 'reset_password_request')?->getAction());
    }

    // A "register" Form's "cgu" field seeded before FormField gained "url" (see UPGRADE.md) gets its link backfilled in place on the next import - without touching a label an admin may already have edited
    public function testImportBackfillsRegisterCguFieldUrlWhenStale(): void
    {
        $persisted = [];
        $repository = $this->createPageRepository(['creer-un-compte', 'mot-de-passe-oublie']);

        $cgu = (new FormField())->setName('cgu')->setLabel('CGU custom')->setType(FormField::TYPE_CHECKBOX)->setRestricted(true);
        $register = (new Form())->setName('register')->setRestricted(true)->setAction('register');
        $register->addField($cgu);

        $formRepository = $this->createStub(FormRepository::class);
        $formRepository->method('findOneBy')->willReturnCallback(
            static fn (array $criteria): ?Form => match ($criteria['name']) {
                'register' => $register,
                'reset_password_request' => (new Form())->setRestricted(true)->setAction('reset_password_request'),
                default => null,
            }
        );

        $importer = $this->createImporter($repository, $this->createEntityManager($persisted), formRepository: $formRepository);

        $importer->import();

        $this->assertSame('/pages/conditions-generales-d-utilisation', $cgu->getUrl());
        $this->assertSame('CGU custom', $cgu->getLabel());
    }

    // The interactive command path (SiteCreateCommand) lets a callback veto a page or override its published state
    public function testImportHonoursOnPageCallbackDecisionAndOverride(): void
    {
        $persisted = [];
        $repository = $this->createPageRepository();
        $importer = $this->createImporter($repository, $this->createEntityManager($persisted));

        // Imports only the homepage, forcing it to be unpublished regardless of the built-in default
        $onPage = static fn (array $def): array => [
            'import' => 'home' === $def['slug'],
            'isPublished' => false,
        ];

        $result = $importer->import($onPage);

        // Pages declined by the callback are neither created nor counted as skipped
        $this->assertSame(['created' => 1, 'skipped' => 0], $result);
        $this->assertCount(1, $persisted);
        $this->assertFalse($persisted[0]->isPublished());
    }

    // A locale absent from getDefinitions() (no translation yet) must be silently ignored, not error out
    public function testImportIgnoresEnabledLocaleWithoutDefinitions(): void
    {
        $persisted = [];
        $repository = $this->createPageRepository();
        $importer = $this->createImporter($repository, $this->createEntityManager($persisted), 'fr', ['fr', 'de']);

        $result = $importer->import();

        $this->assertSame(['created' => 9, 'skipped' => 0], $result);
    }

    // The contact/register/reset-password-request pages each seed the matching restricted EmailTemplate too
    public function testImportSeedsTheThreeDefaultEmailTemplates(): void
    {
        $persisted = [];
        $repository = $this->createPageRepository();
        $importer = $this->createImporter($repository, $this->createEntityManager($persisted));

        $importer->import();

        $contactNotification = $this->findPersistedEmailTemplate($persisted, 'contact_notification');
        $this->assertNotNull($contactNotification);
        $this->assertTrue($contactNotification->isRestricted());
        $this->assertCount(2, $contactNotification->getBlocks());

        $accountValidation = $this->findPersistedEmailTemplate($persisted, 'account_validation');
        $this->assertNotNull($accountValidation);
        $this->assertTrue($accountValidation->isRestricted());
        $this->assertCount(4, $accountValidation->getBlocks());
        $urls = array_map(static fn ($block) => $block->getUrl(), $accountValidation->getBlocks()->toArray());
        $this->assertContains('{{ signed_url }}', $urls);

        $passwordReset = $this->findPersistedEmailTemplate($persisted, 'password_reset');
        $this->assertNotNull($passwordReset);
        $this->assertTrue($passwordReset->isRestricted());
        $this->assertCount(4, $passwordReset->getBlocks());
        $urls = array_map(static fn ($block) => $block->getUrl(), $passwordReset->getBlocks()->toArray());
        $this->assertContains('{{ reset_url }}', $urls);
    }

    // Re-running the import must not re-seed an EmailTemplate that already exists
    public function testImportDoesNotReSeedEmailTemplatesWhenAlreadyExisting(): void
    {
        $persisted = [];
        $repository = $this->createPageRepository();
        $importer = $this->createImporter(
            $repository,
            $this->createEntityManager($persisted),
            emailTemplateRepository: $this->createEmailTemplateRepository(['contact_notification', 'account_validation', 'password_reset']),
        );

        $importer->import();

        $this->assertNull($this->findPersistedEmailTemplate($persisted, 'contact_notification'));
        $this->assertNull($this->findPersistedEmailTemplate($persisted, 'account_validation'));
        $this->assertNull($this->findPersistedEmailTemplate($persisted, 'password_reset'));
    }

    // SiteCreateCommand offers legal pages as footer menu items, in a fixed reading order rather than definition order
    public function testGetLegalPageSlugsByModelReturnsSlugsInFixedOrder(): void
    {
        $repository = $this->createPageRepository();
        $persisted = [];
        $importer = $this->createImporter($repository, $this->createEntityManager($persisted));

        $slugsByModel = $importer->getLegalPageSlugsByModel();

        $this->assertSame(
            [
                'france/legal-notice' => 'mentions-legales',
                'france/privacy-policy' => 'regles-de-confidentialite',
                'france/terms-of-use' => 'conditions-generales-d-utilisation',
                'france/cookies' => 'cookies',
                'france/copyright' => 'copyright',
            ],
            $slugsByModel
        );
    }
}
