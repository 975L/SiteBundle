<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\SiteBundle\Tests\Service;

use c975L\ConfigBundle\Management\ContentQualityAnalyzer;
use c975L\ConfigBundle\Service\UserFormSeeder;
use c975L\SiteBundle\Entity\Page;
use c975L\SiteBundle\Repository\PageRepository;
use c975L\SiteBundle\Service\DefaultPagesImporter;
use c975L\UiBundle\Contract\EmailTemplateProviderInterface;
use c975L\UiBundle\Contract\FormBlockDependencyProviderInterface;
use c975L\UiBundle\Entity\EmailTemplate;
use c975L\UiBundle\Entity\Form;
use c975L\UiBundle\Entity\FormField;
use c975L\UiBundle\Repository\EmailTemplateRepository;
use c975L\UiBundle\Repository\FormRepository;
use c975L\UiBundle\Service\EmailTemplateFactory;
use c975L\UiBundle\Service\FormSeeder;
use c975L\UiBundle\Service\FormTranslator;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Translation\Loader\XliffFileLoader;
use Symfony\Component\Translation\Translator;
use Symfony\Contracts\Translation\TranslatorInterface;

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

    // Builds a PageRepository double whose findOneBy() answers according to $existingSlugs - $existingSummary is the description those already-existing pages carry, null standing for the page of a site created before this bundle seeded any
    private function createPageRepository(array $existingSlugs = [], ?string $existingSummary = null): PageRepository
    {
        $repository = $this->createStub(PageRepository::class);
        $repository->method('findOneBy')->willReturnCallback(
            static function (array $criteria) use ($existingSlugs, $existingSummary): ?Page {
                if (!\in_array($criteria['slug'], $existingSlugs, true)) {
                    return null;
                }

                return new Page()->setSlug($criteria['slug'])->setSummarySocialNetwork($existingSummary);
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

                // The "links" key is what tells FormSeeder this Form is up to date and needs no backfill - set directly, setLinks([]) removing it instead
                return new Form()->setName($criteria['name'])->setRestricted(true)->setAction($existingForms[$criteria['name']])->setActionConfig(['links' => []]);
            }
        );

        return $repository;
    }

    // Builds an EmailTemplateRepository double - findOneBy(['name' => ...]) reports an EmailTemplate as already existing for any name listed in $existingNames
    private function createEmailTemplateRepository(array $existingNames = []): EmailTemplateRepository
    {
        $repository = $this->createStub(EmailTemplateRepository::class);
        $repository->method('findOneBy')->willReturnCallback(
            static fn (array $criteria): ?EmailTemplate => \in_array($criteria['name'], $existingNames, true) ? new EmailTemplate() : null
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

        // Real seeders rather than doubles: they are the thing under test here as much as the importer is - what ends up persisted is exactly what a consuming app would get
        $formSeeder = new FormSeeder(
            $em,
            $formRepository ?? $this->createFormRepository(),
            $emailTemplateRepository ?? $this->createEmailTemplateRepository(),
            new EmailTemplateFactory(),
            new FormTranslator(),
            $defaultLocale,
        );

        return new DefaultPagesImporter(
            $em,
            $pageRepository,
            $formSeeder,
            new UserFormSeeder($formSeeder, $em, self::translator('config', __DIR__ . '/../../vendor/c975l/core-bundle/ConfigBundle/translations')),
            $security,
            $defaultLocale,
            $enabledLocales,
            self::translator('ui', __DIR__ . '/../../vendor/c975l/core-bundle/UiBundle/translations'),
        );
    }

    // Real catalogues rather than a stub: the seeded wording is read from them, and a mistyped key would be seeded as the key itself
    private static function translator(string $domain, string $dir): TranslatorInterface
    {
        $translator = new Translator('fr');
        $translator->addLoader('xlf', new XliffFileLoader());
        foreach (['fr', 'en', 'es'] as $locale) {
            $translator->addResource('xlf', $dir . '/' . $domain . '.' . $locale . '.xlf', $locale, $domain);
        }

        return $translator;
    }

    // A brand-new fr install has no pages yet: every definition but the ShopBundle-gated one is created
    public function testImportCreatesAllDefinitionsForDefaultLocaleWhenNoneExist(): void
    {
        $persisted = [];
        $repository = $this->createPageRepository();
        $importer = $this->createImporter($repository, $this->createEntityManager($persisted));

        $result = $importer->import();

        // terms-of-sales is gated behind c975L\ShopBundle, not installed here, so it's neither created nor skipped
        $this->assertSame(['created' => 9, 'skipped' => 0, 'summarised' => []], $result);
        $pages = array_values(array_filter($persisted, static fn ($entity) => $entity instanceof Page));
        $this->assertCount(9, $pages);
        $this->assertSame('home', $pages[0]->getSlug());
    }

    // A page is one page in every language (see PageTranslator), so a bilingual site gets one set of pages and not two - it used to get one per declared locale, which meant two legal notices pointing at the same model and, on the one slug the French and English sets share, two rows for "contact" that took the whole command down on the flush
    public function testABilingualSiteGetsOneSetOfPagesAndNoDuplicateSlug(): void
    {
        $persisted = [];
        $repository = $this->createPageRepository();
        $importer = $this->createImporter($repository, $this->createEntityManager($persisted), 'fr', ['fr', 'en']);

        $result = $importer->import();

        $this->assertSame(['created' => 9, 'skipped' => 0, 'summarised' => []], $result);

        $slugs = array_map(
            static fn (Page $page): string => (string) $page->getSlug(),
            array_values(array_filter($persisted, static fn ($entity) => $entity instanceof Page))
        );
        $this->assertSame($slugs, array_unique($slugs));
        $this->assertContains('mentions-legales', $slugs);
        $this->assertNotContains('legal-notice', $slugs);
    }

    // The wording follows the language the site is written in, whatever else it declares
    public function testASiteWrittenInEnglishGetsTheEnglishSet(): void
    {
        $persisted = [];
        $repository = $this->createPageRepository();
        $importer = $this->createImporter($repository, $this->createEntityManager($persisted), 'en', ['en', 'fr']);

        $importer->import();

        $slugs = array_map(
            static fn (Page $page): string => (string) $page->getSlug(),
            array_values(array_filter($persisted, static fn ($entity) => $entity instanceof Page))
        );
        $this->assertContains('legal-notice', $slugs);
        $this->assertNotContains('mentions-legales', $slugs);
    }

    // The account-related pages have no SEO value, so they're seeded out of the sitemap and out of Google's index; every other default page is indexable
    public function testImportSeedsTheAccountPagesAsNonIndexable(): void
    {
        $persisted = [];
        $importer = $this->createImporter($this->createPageRepository(), $this->createEntityManager($persisted));

        $importer->import();

        $indexable = [];
        foreach ($persisted as $entity) {
            if ($entity instanceof Page) {
                $indexable[$entity->getSlug()] = $entity->isIndexable();
            }
        }

        $this->assertFalse($indexable['creer-un-compte']);
        $this->assertFalse($indexable['mot-de-passe-oublie']);
        $this->assertTrue($indexable['home']);
        $this->assertTrue($indexable['contact']);
    }

    // A page rendering no meta description at all is one of the reasons a search engine crawls it and then declines to index it
    public function testImportSeedsAMetaDescriptionOnEveryDefaultPageButHome(): void
    {
        $summaries = $this->importedSummaries();

        foreach (['mentions-legales', 'regles-de-confidentialite', 'conditions-generales-d-utilisation', 'cookies', 'copyright', 'contact'] as $slug) {
            $this->assertNotNull($summaries[$slug], sprintf('The "%s" page is seeded without a meta description.', $slug));
        }

        // A home page's description belongs to the site itself, not to a bundle default
        $this->assertNull($summaries['home']);
    }

    // og:description is what a messaging app or a social network renders when someone shares the link, and "noindex" does nothing to stop a page being shared
    public function testImportSeedsADescriptionOnTheAccountPagesToo(): void
    {
        $summaries = $this->importedSummaries();

        $this->assertNotNull($summaries['creer-un-compte']);
        $this->assertNotNull($summaries['mot-de-passe-oublie']);
    }

    // @return array<string, ?string> the seeded description of every page created by a full import, by slug
    private function importedSummaries(): array
    {
        $persisted = [];
        $importer = $this->createImporter($this->createPageRepository(), $this->createEntityManager($persisted));

        $importer->import();

        $summaries = [];
        foreach ($persisted as $entity) {
            if ($entity instanceof Page) {
                $summaries[$entity->getSlug()] = $entity->getSummarySocialNetwork();
            }
        }

        return $summaries;
    }

    // Seeding a description outside ContentQualityAnalyzer's own window would have a fresh site start with a health check warning on the very pages it just created
    public function testTheSeededDescriptionsRespectTheContentQualityThresholds(): void
    {
        $persisted = [];
        $importer = $this->createImporter($this->createPageRepository(), $this->createEntityManager($persisted));

        $importer->import();

        foreach ($persisted as $entity) {
            $summary = $entity instanceof Page ? $entity->getSummarySocialNetwork() : null;
            if (null === $summary) {
                continue;
            }

            $this->assertGreaterThanOrEqual(ContentQualityAnalyzer::DESCRIPTION_MIN_LENGTH, mb_strlen($summary), sprintf('The "%s" description is too short.', $entity->getSlug()));
            $this->assertLessThanOrEqual(ContentQualityAnalyzer::DESCRIPTION_MAX_LENGTH, mb_strlen($summary), sprintf('The "%s" description is too long.', $entity->getSlug()));
        }
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

        $this->assertSame(0, $result['created']);
        $this->assertSame(9, $result['skipped']);
        $this->assertSame([], $persisted);
    }

    // A site running since before this bundle seeded descriptions gets them filled in by re-running the command - that is the whole point of the backfill, no page being created for it
    public function testImportBackfillsTheDescriptionOfPagesThatAlreadyExistWithoutOne(): void
    {
        $existingSlugs = ['home', 'cookies', 'copyright', 'contact'];
        $persisted = [];
        $repository = $this->createPageRepository($existingSlugs);
        $importer = $this->createImporter($repository, $this->createEntityManager($persisted));

        $result = $importer->import();

        $this->assertContains('cookies', $result['summarised']);
        $this->assertContains('copyright', $result['summarised']);
        $this->assertContains('contact', $result['summarised']);
        // "home" carries no seeded description, so there is nothing to fill in for it
        $this->assertNotContains('home', $result['summarised']);
    }

    // A description an admin typed is never overwritten, however many times the command is re-run
    public function testImportNeverOverwritesADescriptionAlreadySet(): void
    {
        $persisted = [];
        $repository = $this->createPageRepository(['cookies'], 'Le texte que Laurent a écrit lui-même');
        $importer = $this->createImporter($repository, $this->createEntityManager($persisted));

        $result = $importer->import();

        $this->assertNotContains('cookies', $result['summarised']);
    }

    // Whitespace is not a description an admin meant to keep, so it gets filled in like an empty one
    public function testImportBackfillsOverBlankWhitespace(): void
    {
        $persisted = [];
        $repository = $this->createPageRepository(['cookies'], '   ');
        $importer = $this->createImporter($repository, $this->createEntityManager($persisted));

        $result = $importer->import();

        $this->assertContains('cookies', $result['summarised']);
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

    // The contact page gets a generic "form" Block pointing at the "contact" Form by name, and seeds that Form itself (restricted core fields, send_email action)
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

    // No seeded field carries a placeholder - a field shows its label alone, and it's the admin's call to type one in the back-office
    public function testImportSeedsNoPlaceholderOnAnyFormField(): void
    {
        $persisted = [];
        $importer = $this->createImporter($this->createPageRepository(), $this->createEntityManager($persisted));

        $importer->import();

        foreach ($persisted as $entity) {
            if ($entity instanceof Form) {
                foreach ($entity->getFields() as $field) {
                    $this->assertNull($field->getPlaceholder(), \sprintf('%s.%s', $entity->getName(), $field->getName()));
                }
            }
        }
    }

    // Re-running the import must not touch the "contact" Form if it already exists with the expected action
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

        $cgu = new FormField()->setName('cgu')->setLabel('CGU custom')->setType(FormField::TYPE_CHECKBOX)->setRestricted(true);
        $register = new Form()->setName('register')->setRestricted(true)->setAction('register');
        $register->addField($cgu);

        $formRepository = $this->createStub(FormRepository::class);
        $formRepository->method('findOneBy')->willReturnCallback(
            static fn (array $criteria): ?Form => match ($criteria['name']) {
                'register' => $register,
                'reset_password_request' => new Form()->setRestricted(true)->setAction('reset_password_request'),
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
        $this->assertSame(['created' => 1, 'skipped' => 0, 'summarised' => []], $result);
        $this->assertCount(1, $persisted);
        $this->assertFalse($persisted[0]->isPublished());
    }

    // The indexable default only holds for a page seeded published: a page seeded unpublished (a definition holding 'isPublished' => false like "conditions-generales-de-vente", or one the callback above answered "no" for) is unreferenced by Page::unreferenceWhenUnpublished() at the very flush that creates it - played here as Doctrine would - and publishing it later is where its referencing is decided again, by hand
    public function testAPageSeededUnpublishedIsSeededUnreferencedToo(): void
    {
        $persisted = [];
        $repository = $this->createPageRepository();
        $importer = $this->createImporter($repository, $this->createEntityManager($persisted));

        $onPage = static fn (array $def): array => [
            'import' => 'home' === $def['slug'],
            'isPublished' => false,
        ];

        $importer->import($onPage);
        $persisted[0]->unreferenceWhenUnpublished();

        $this->assertFalse($persisted[0]->isIndexable());
    }

    // A locale absent from getDefinitions() (no translation yet) must be silently ignored, not error out
    public function testImportIgnoresEnabledLocaleWithoutDefinitions(): void
    {
        $persisted = [];
        $repository = $this->createPageRepository();
        $importer = $this->createImporter($repository, $this->createEntityManager($persisted), 'fr', ['fr', 'de']);

        $result = $importer->import();

        $this->assertSame(['created' => 9, 'skipped' => 0, 'summarised' => []], $result);
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

    // Both contracts are what the container discovers this service through, and every other test here calls the methods directly - so only an instanceof check catches a "use" line overwritten instead of added, which is how the form-block one was once lost
    public function testItImplementsBothDiscoveryContracts(): void
    {
        $persisted = [];
        $importer = $this->createImporter($this->createPageRepository(), $this->createEntityManager($persisted));

        $this->assertInstanceOf(EmailTemplateProviderInterface::class, $importer);
        $this->assertInstanceOf(FormBlockDependencyProviderInterface::class, $importer);
    }

    // Lets an imported Page keep a working "contact" Form, only the Page and Blocks being exported
    public function testEnsureFormBlockDependenciesExistSeedsTheFormForAFormBlock(): void
    {
        $persisted = [];
        $importer = $this->createImporter($this->createPageRepository(), $this->createEntityManager($persisted));

        $importer->ensureFormBlockDependenciesExist(['kind' => 'form', 'data' => ['name' => 'contact']]);

        $form = $this->findPersistedForm($persisted, 'contact');
        $this->assertNotNull($form);
        $this->assertSame('send_email', $form->getAction());
    }

    // A non-"form" block (e.g. plain text/image) never has a Form/EmailTemplate to backfill
    public function testEnsureFormBlockDependenciesExistIsNoopForNonFormBlocks(): void
    {
        $persisted = [];
        $importer = $this->createImporter($this->createPageRepository(), $this->createEntityManager($persisted));

        $importer->ensureFormBlockDependenciesExist(['kind' => 'text', 'data' => ['content' => 'hello']]);

        $this->assertSame([], $persisted);
    }

    // A "form" block without its "data.name" (malformed/partial export payload) is left alone rather than erroring
    public function testEnsureFormBlockDependenciesExistIsNoopWhenFormNameIsMissing(): void
    {
        $persisted = [];
        $importer = $this->createImporter($this->createPageRepository(), $this->createEntityManager($persisted));

        $importer->ensureFormBlockDependenciesExist(['kind' => 'form', 'data' => []]);

        $this->assertSame([], $persisted);
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
