<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\SiteBundle\Service;

use c975L\ConfigBundle\Contract\UserInterface;
use c975L\SiteBundle\Entity\Page;
use c975L\SiteBundle\Repository\PageRepository;
use c975L\UiBundle\Entity\Block;
use c975L\UiBundle\Entity\EmailBlock;
use c975L\UiBundle\Entity\EmailTemplate;
use c975L\UiBundle\Entity\Form;
use c975L\UiBundle\Entity\FormField;
use c975L\UiBundle\Repository\EmailTemplateRepository;
use c975L\UiBundle\Repository\FormRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

class DefaultPagesImporter
{
    // name => [type, label, url], one set per locale - the generic "form" Block's FormSubmissionType renders FormField labels as literal text (translation_domain: false, an admin is expected to type real text, not a key) - so these have to be actual words, picked once for kernel.default_locale since Form::$name is unique site-wide (one "contact" Form, not one per locale). No placeholder is ever seeded, a field showing its label alone until an admin types one in the back-office. "url" is only ever set on REGISTER_CORE_FIELDS' "cgu" entry below, appended as a clickable link next to its label (see FormSubmissionType)
    private const CONTACT_CORE_FIELDS = [
        'fr' => [
            'name' => [FormField::TYPE_TEXT, 'Nom', null],
            'email' => [FormField::TYPE_EMAIL, 'Email', null],
            'subject' => [FormField::TYPE_TEXT, 'Sujet', null],
            'message' => [FormField::TYPE_TEXTAREA, 'Message', null],
        ],
        'en' => [
            'name' => [FormField::TYPE_TEXT, 'Name', null],
            'email' => [FormField::TYPE_EMAIL, 'Email', null],
            'subject' => [FormField::TYPE_TEXT, 'Subject', null],
            'message' => [FormField::TYPE_TEXTAREA, 'Message', null],
        ],
        'es' => [
            'name' => [FormField::TYPE_TEXT, 'Nombre', null],
            'email' => [FormField::TYPE_EMAIL, 'Email', null],
            'subject' => [FormField::TYPE_TEXT, 'Asunto', null],
            'message' => [FormField::TYPE_TEXTAREA, 'Mensaje', null],
        ],
    ];

    // Same shape as CONTACT_CORE_FIELDS, for the "register" Form - processed the same generic way as "contact" (c975L\UiBundle\Controller\FormController), see scaffold's App\Service\RegisterFormAction for the "register" FormActionInterface key. "cgu"'s url points at that locale's own terms-of-use legal page, seeded a few lines below in each locale's page list - kept as a plain relative "/pages/{slug}" path (no router involved here) since it's only ever read back once by FormSubmissionType, exactly like every other seeded field
    private const REGISTER_CORE_FIELDS = [
        'fr' => [
            'email' => [FormField::TYPE_EMAIL, 'Email', null],
            'plainPassword' => [FormField::TYPE_PASSWORD_REPEATED, 'Mot de passe', null],
            'cgu' => [FormField::TYPE_CHECKBOX, 'J\'accepte les conditions générales d\'utilisation', '/pages/conditions-generales-d-utilisation'],
        ],
        'en' => [
            'email' => [FormField::TYPE_EMAIL, 'Email', null],
            'plainPassword' => [FormField::TYPE_PASSWORD_REPEATED, 'Password', null],
            'cgu' => [FormField::TYPE_CHECKBOX, 'I accept the terms of use', '/pages/terms-of-use'],
        ],
        'es' => [
            'email' => [FormField::TYPE_EMAIL, 'Email', null],
            'plainPassword' => [FormField::TYPE_PASSWORD_REPEATED, 'Contraseña', null],
            'cgu' => [FormField::TYPE_CHECKBOX, 'Acepto las condiciones de uso', '/pages/condiciones-de-uso'],
        ],
    ];

    // Same shape as CONTACT_CORE_FIELDS, for the "reset_password_request" Form - see scaffold's App\Service\ResetPasswordRequestFormAction for the "reset_password_request" FormActionInterface key
    private const RESET_PASSWORD_REQUEST_CORE_FIELDS = [
        'fr' => [
            'email' => [FormField::TYPE_EMAIL, 'Email', null],
        ],
        'en' => [
            'email' => [FormField::TYPE_EMAIL, 'Email', null],
        ],
        'es' => [
            'email' => [FormField::TYPE_EMAIL, 'Email', null],
        ],
    ];

    // One EmailBlock tuple set per locale, unused positions left null; placeholders are resolved at send time
    private const CONTACT_NOTIFICATION_BLOCKS = [
        'fr' => [
            [EmailBlock::TYPE_HEADING, 'Nouveau message via {{ form_name }}', EmailBlock::LEVEL_H2, null, null, null],
            [EmailBlock::TYPE_FIELDS_TABLE, null, null, null, null, null],
        ],
        'en' => [
            [EmailBlock::TYPE_HEADING, 'New message via {{ form_name }}', EmailBlock::LEVEL_H2, null, null, null],
            [EmailBlock::TYPE_FIELDS_TABLE, null, null, null, null, null],
        ],
        'es' => [
            [EmailBlock::TYPE_HEADING, 'Nuevo mensaje vía {{ form_name }}', EmailBlock::LEVEL_H2, null, null, null],
            [EmailBlock::TYPE_FIELDS_TABLE, null, null, null, null, null],
        ],
    ];

    // "{{ signed_url }}"/"{{ expires_at }}" are resolved by EmailVerifier's caller
    private const ACCOUNT_VALIDATION_BLOCKS = [
        'fr' => [
            [EmailBlock::TYPE_HEADING, 'Confirmez votre adresse email', EmailBlock::LEVEL_H1, null, null, null],
            [EmailBlock::TYPE_TEXT, null, null, 'Merci de votre inscription. Cliquez sur le bouton ci-dessous pour confirmer votre adresse email.', null, null],
            [EmailBlock::TYPE_BUTTON, null, null, null, 'Confirmer mon email', '{{ signed_url }}'],
            [EmailBlock::TYPE_TEXT, null, null, '{{ expires_at }}', null, null],
        ],
        'en' => [
            [EmailBlock::TYPE_HEADING, 'Confirm your email address', EmailBlock::LEVEL_H1, null, null, null],
            [EmailBlock::TYPE_TEXT, null, null, 'Thanks for registering. Click the button below to confirm your email address.', null, null],
            [EmailBlock::TYPE_BUTTON, null, null, null, 'Confirm my email', '{{ signed_url }}'],
            [EmailBlock::TYPE_TEXT, null, null, '{{ expires_at }}', null, null],
        ],
        'es' => [
            [EmailBlock::TYPE_HEADING, 'Confirma tu dirección de email', EmailBlock::LEVEL_H1, null, null, null],
            [EmailBlock::TYPE_TEXT, null, null, 'Gracias por registrarte. Haz clic en el botón de abajo para confirmar tu dirección de email.', null, null],
            [EmailBlock::TYPE_BUTTON, null, null, null, 'Confirmar mi email', '{{ signed_url }}'],
            [EmailBlock::TYPE_TEXT, null, null, '{{ expires_at }}', null, null],
        ],
    ];

    // "{{ reset_url }}"/"{{ expires_at }}" are resolved by ResetPasswordRequestFormAction
    private const PASSWORD_RESET_BLOCKS = [
        'fr' => [
            [EmailBlock::TYPE_HEADING, 'Réinitialisation de votre mot de passe', EmailBlock::LEVEL_H1, null, null, null],
            [EmailBlock::TYPE_TEXT, null, null, 'Vous avez demandé la réinitialisation de votre mot de passe. Cliquez sur le bouton ci-dessous pour en choisir un nouveau.', null, null],
            [EmailBlock::TYPE_BUTTON, null, null, null, 'Réinitialiser mon mot de passe', '{{ reset_url }}'],
            [EmailBlock::TYPE_TEXT, null, null, '{{ expires_at }}', null, null],
        ],
        'en' => [
            [EmailBlock::TYPE_HEADING, 'Reset your password', EmailBlock::LEVEL_H1, null, null, null],
            [EmailBlock::TYPE_TEXT, null, null, 'You requested a password reset. Click the button below to choose a new one.', null, null],
            [EmailBlock::TYPE_BUTTON, null, null, null, 'Reset my password', '{{ reset_url }}'],
            [EmailBlock::TYPE_TEXT, null, null, '{{ expires_at }}', null, null],
        ],
        'es' => [
            [EmailBlock::TYPE_HEADING, 'Restablece tu contraseña', EmailBlock::LEVEL_H1, null, null, null],
            [EmailBlock::TYPE_TEXT, null, null, 'Has solicitado restablecer tu contraseña. Haz clic en el botón de abajo para elegir una nueva.', null, null],
            [EmailBlock::TYPE_BUTTON, null, null, null, 'Restablecer mi contraseña', '{{ reset_url }}'],
            [EmailBlock::TYPE_TEXT, null, null, '{{ expires_at }}', null, null],
        ],
    ];

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly PageRepository $pageRepository,
        private readonly FormRepository $formRepository,
        private readonly EmailTemplateRepository $emailTemplateRepository,
        private readonly Security $security,
        #[Autowire('%kernel.default_locale%')]
        private readonly string $defaultLocale,
        #[Autowire('%kernel.enabled_locales%')]
        private readonly array $enabledLocales,
    ) {
    }

    // $onPage, if given, is called for each page not yet in database as fn(array $def): array{import: bool, isPublished: bool} and lets a command decide interactively whether to import it and with which isPublished value. Without it (default), every page is imported using the isPublished value from getDefinitions(). Returns ['created' => int, 'skipped' => int, 'summarised' => list<string>] - the slugs rather than a count for the last one, since it is the only case where the command rewrites content of a page that already existed, and a caller has to be able to name them back to whoever ran it
    public function import(?callable $onPage = null): array
    {
        $counts = ['created' => 0, 'skipped' => 0, 'backfilled' => 0, 'summarised' => 0, 'formBackfilled' => 0];
        $summarised = [];
        $now = new \DateTime();
        // Security only guarantees its own UserInterface, Page relates to the c975L one the application's User entity implements
        $user = $this->security->getUser();
        $user = $user instanceof UserInterface ? $user : null;
        $definitions = $this->getDefinitions();

        // Always imports the default locale, plus any locale declared in framework.enabled_locales; the default locale comes first so the homepage keeps a deterministic title
        foreach (array_unique([$this->defaultLocale, ...$this->enabledLocales]) as $locale) {
            foreach ($definitions[$locale] ?? [] as $def) {
                $counters = $this->importDefinition($def, $now, $user, $onPage);
                foreach ($counters as $counter) {
                    ++$counts[$counter];
                }

                if (\in_array('summarised', $counters, true)) {
                    $summarised[] = $def['slug'];
                }
            }
        }

        if ($counts['created'] > 0 || $counts['backfilled'] > 0) {
            $this->em->flush();
        }

        return ['created' => $counts['created'], 'skipped' => $counts['skipped'], 'summarised' => $summarised];
    }

    // Fills in the meta/og description of a page that already existed, and says whether it did. Only ever writes into an empty field: a description an admin typed - or deliberately emptied back to nothing else than whitespace - is never overwritten, however many times the command is re-run. Pages are matched on their slug alone by the caller, so one renamed in the back-office simply matches no definition and is left alone
    private function backfillSummary(Page $page, array $def): bool
    {
        if (!isset($def['summary']) || '' !== trim((string) $page->getSummarySocialNetwork())) {
            return false;
        }

        $page->setSummarySocialNetwork($def['summary']);

        return true;
    }

    // The counters import() must increment for this definition, empty when it contributes nothing
    // @return list<string>
    private function importDefinition(array $def, \DateTime $now, ?UserInterface $user, ?callable $onPage): array
    {
        // Skips definitions tied to a bundle (i.e. Shop's "terms of sales") that isn't installed
        if (isset($def['requiresClass']) && !class_exists($def['requiresClass'])) {
            return [];
        }

        $existingPage = $this->pageRepository->findOneBy(['slug' => $def['slug']]);
        if ($existingPage) {
            $counters = ['skipped'];

            // A page created before this bundle seeded descriptions renders none at all - filled in here so re-running the command is all it takes, on this site and on every other one
            if ($this->backfillSummary($existingPage, $def)) {
                $counters[] = 'summarised';
            }

            // The page exists but its Form/EmailTemplate may not, buildPage() never running for it
            $formName = $this->formBlockNameFromPageDef($def);
            if (null !== $formName) {
                $this->ensureFormAndEmailTemplateExist($formName);
                $counters[] = 'formBackfilled';
            }

            // Anything written above needs the flush import() only performs when something actually changed
            if (\count($counters) > 1) {
                $counters[] = 'backfilled';
            }

            return $counters;
        }

        if (null !== $onPage) {
            $decision = $onPage($def);
            if (!$decision['import']) {
                return [];
            }
            $def['isPublished'] = $decision['isPublished'];
        }

        $this->em->persist($this->buildPage($def, $now, $user));

        return ['created'];
    }

    private function buildPage(array $def, \DateTime $now, ?UserInterface $user): Page
    {
        $page = (new Page())
            ->setTitle($def['title'])
            ->setSlug($def['slug'])
            ->setChangeFrequency($def['changeFrequency'])
            ->setPriority($def['priority'])
            ->setIsPublished($def['isPublished'])
            // The meta description AND og:description of the page, see layout.html.twig. Seeded for every default page whose content is standardized enough to describe once here - the legal ones, "contact", and the account ones: left empty they render no description at all, which is one of the reasons a search engine crawls a page and then declines to index it. The account pages get one despite being non-indexable, since og:description is what a messaging app or a social network renders when someone shares the link, and "noindex" does nothing to stop that. Every text is kept within ContentQualityAnalyzer's own 50-160 character window, so a freshly created site doesn't start with a health check warning on the very pages it just seeded. Only "home" is left null: its description belongs to the site itself, not to a bundle default
            ->setSummarySocialNetwork($def['summary'] ?? null)
            // Only the account-related pages opt out (see the definitions below), every other default page is indexable
            ->setIsIndexable($def['isIndexable'] ?? true)
            ->setCreation($now)
            ->setModification($now);

        if (null !== $user) {
            $page->setUser($user);
        }

        if (isset($def['model'])) {
            $block = (new Block())
                ->setKind('legal_model')
                ->setPosition(1)
                ->setData(['model' => $def['model'], 'latestUpdate' => $now->format('Y-m-d')]);
            $this->em->persist($block);
            $page->addBlock($block);
        }

        if (isset($def['block'])) {
            $kind = $def['block']['kind'];
            $formName = $this->formBlockNameFromPageDef($def);
            if (null !== $formName) {
                $this->ensureFormAndEmailTemplateExist($formName);
            }

            $block = (new Block())
                ->setKind($kind)
                ->setPosition(1)
                ->setData($def['block']['data'] ?? []);
            $this->em->persist($block);
            $page->addBlock($block);
        }

        return $page;
    }

    // Returns the Form name a "form"-kind page definition points at ("contact"/"register"/"reset_password_request"), or null for any other Block kind - shared by buildPage() (page being newly created) and import()'s "page already exists" branch, so a Form/EmailTemplate gets backfilled either way
    private function formBlockNameFromPageDef(array $def): ?string
    {
        if ('form' !== ($def['block']['kind'] ?? null)) {
            return null;
        }

        return $def['block']['data']['name'] ?? null;
    }

    // Public wrapper around ensureFormAndEmailTemplateExist(), for a single {kind, data} block array (as found in a Page content export - see PageImportProvider) rather than this class' own $def['block'] shape. Lets a Page pushed from another environment keep a working "contact"/"register"/"reset_password_request" Form even though only the Page+Blocks themselves were exported, not the Form/EmailTemplate content
    public function ensureFormBlockDependenciesExist(array $blockData): void
    {
        $formName = 'form' === ($blockData['kind'] ?? null) ? ($blockData['data']['name'] ?? null) : null;
        if (null !== $formName) {
            $this->ensureFormAndEmailTemplateExist($formName);
        }
    }

    // Idempotent - there's no per-app scaffold controller calling this on every request anymore (register/reset_password_request/contact are all rendered by UiBundle's generic FormController, not a dedicated controller), so import() itself is the only thing that can seed/backfill a Form+EmailTemplate, on both the "page newly created" and "page already exists" paths - otherwise a site whose page already existed before a given Form/EmailTemplate was introduced would never get backfilled, no matter how many times the import command is re-run
    private function ensureFormAndEmailTemplateExist(string $formName): void
    {
        match ($formName) {
            'contact' => $this->ensureContactFormExists(),
            'register' => $this->ensureRegisterFormExists(),
            'reset_password_request' => $this->ensureResetPasswordRequestFormExists(),
            default => null,
        };
    }

    private function ensureContactFormExists(): void
    {
        $this->ensureFormExists('contact', self::CONTACT_CORE_FIELDS, 'send_email', ['senderEmailField' => 'email', 'offerReceiveCopy' => true, 'template' => '@c975LSite/emails/contact_notification.html.twig']);
        $this->ensureEmailTemplateExists('contact_notification', self::CONTACT_NOTIFICATION_BLOCKS);
    }

    private function ensureRegisterFormExists(): void
    {
        $this->ensureFormExists('register', self::REGISTER_CORE_FIELDS, 'register');
        $this->ensureEmailTemplateExists('account_validation', self::ACCOUNT_VALIDATION_BLOCKS);
    }

    private function ensureResetPasswordRequestFormExists(): void
    {
        $this->ensureFormExists('reset_password_request', self::RESET_PASSWORD_REQUEST_CORE_FIELDS, 'reset_password_request');
        $this->ensureEmailTemplateExists('password_reset', self::PASSWORD_RESET_BLOCKS);
    }

    // Idempotent - seeds a restricted c975L\UiBundle\Entity\Form (name/fields locked, label/placeholder/order still editable, see FormCrudController) so the Block referencing it works right away. $action names the FormActionInterface key processing a submission (c975L\UiBundle\Service\SendEmailFormAction for "contact", scaffold's own RegisterFormAction/ResetPasswordRequestFormAction for the other two). A Form seeded by an earlier version of this bundle (e.g. before register/reset_password_request gained their own action, or before FormField gained "url" - see UPGRADE.md) is backfilled in place instead of left stale. Only touches a field's "url" when it's still null (its seeded default) - once an admin has edited it (blank or otherwise), that edit is never overwritten
    private function ensureFormExists(string $name, array $coreFieldsByLocale, ?string $action = null, ?array $actionConfig = null): void
    {
        $fields = $coreFieldsByLocale[$this->defaultLocale] ?? $coreFieldsByLocale['en'];

        $existing = $this->formRepository->findOneBy(['name' => $name]);
        if (null !== $existing) {
            $this->backfillForm($existing, $fields, $action, $actionConfig);

            return;
        }

        $this->em->persist($this->buildForm($name, $fields, $action, $actionConfig));
    }

    // A Form seeded by an earlier version of this bundle brought up to date in place - only ever on a still-restricted Form/field, so nothing an admin has taken over is touched
    private function backfillForm(Form $form, array $fields, ?string $action, ?array $actionConfig): void
    {
        if ($form->isRestricted() && $form->getAction() !== $action) {
            $form->setAction($action);
            $form->setActionConfig($actionConfig);
            $this->em->persist($form);
        }

        foreach ($form->getFields() as $field) {
            $url = $fields[$field->getName()][2] ?? null;
            if ($field->isRestricted() && null === $field->getUrl() && null !== $url) {
                $field->setUrl($url);
                $this->em->persist($field);
            }
        }
    }

    // $fields entries are [type, label, url] tuples, in the order they're declared
    private function buildForm(string $name, array $fields, ?string $action, ?array $actionConfig): Form
    {
        $form = (new Form())
            ->setName($name)
            ->setAction($action)
            ->setRestricted(true)
            ->setActionConfig($actionConfig);

        $position = 0;
        foreach ($fields as $fieldName => [$type, $label, $url]) {
            $form->addField(
                (new FormField())
                    ->setName($fieldName)
                    ->setLabel($label)
                    ->setType($type)
                    ->setUrl($url)
                    ->setRequired(true)
                    ->setPosition($position++)
                    ->setRestricted(true)
            );
        }

        return $form;
    }

    // Idempotent, seeding a restricted EmailTemplate whose name is locked but whose blocks stay editable
    private function ensureEmailTemplateExists(string $name, array $blocksByLocale): void
    {
        if (null !== $this->emailTemplateRepository->findOneBy(['name' => $name])) {
            return;
        }

        $blocks = $blocksByLocale[$this->defaultLocale] ?? $blocksByLocale['en'];

        $emailTemplate = (new EmailTemplate())
            ->setName($name)
            ->setRestricted(true);

        $position = 0;
        foreach ($blocks as [$type, $heading, $level, $content, $label, $url]) {
            $block = (new EmailBlock())
                ->setType($type)
                ->setHeading($heading)
                ->setLevel($level)
                ->setContent($content)
                ->setLabel($label)
                ->setUrl($url)
                ->setPosition($position++)
            ;
            $emailTemplate->addBlock($block);
        }

        $this->em->persist($emailTemplate);
    }

    // Returns the default-locale legal pages' slugs, keyed by model and in the fixed display order below - used by SiteCreateCommand to offer them as footer menu items. A definition whose bundle isn't installed (e.g. terms-of-sales without ShopBundle) is skipped.
    public function getLegalPageSlugsByModel(): array
    {
        $order = ['france/legal-notice', 'france/privacy-policy', 'france/terms-of-use', 'france/terms-of-sales', 'france/cookies', 'france/copyright'];

        $slugsByModel = [];
        foreach ($this->getDefinitions()[$this->defaultLocale] ?? [] as $def) {
            if (isset($def['model']) && (!isset($def['requiresClass']) || class_exists($def['requiresClass']))) {
                $slugsByModel[$def['model']] = $def['slug'];
            }
        }

        $ordered = [];
        foreach ($order as $model) {
            if (isset($slugsByModel[$model])) {
                $ordered[$model] = $slugsByModel[$model];
            }
        }

        return $ordered;
    }

    // Definitions are keyed by locale; the "home" slug is intentionally identical across locales since PageController looks it up literally and only one can ever exist
    private function getDefinitions(): array
    {
        return [
            'fr' => [
                [
                    'title' => 'Accueil',
                    'slug' => 'home',
                    'changeFrequency' => 'daily',
                    'priority' => 10,
                    'isPublished' => true,
                ],
                [
                    'title' => 'Mentions légales',
                    'slug' => 'mentions-legales',
                    'summary' => 'Mentions légales du site : éditeur, directeur de la publication, hébergeur et coordonnées de contact, conformément à la réglementation en vigueur.',
                    'model' => 'france/legal-notice',
                    'changeFrequency' => 'yearly',
                    'priority' => 1,
                    'isPublished' => true,
                ],
                [
                    'title' => 'Règles de confidentialité',
                    'slug' => 'regles-de-confidentialite',
                    'summary' => 'Comment vos données personnelles sont collectées, utilisées et conservées, et comment exercer vos droits d\'accès, de rectification et de suppression.',
                    'model' => 'france/privacy-policy',
                    'changeFrequency' => 'yearly',
                    'priority' => 1,
                    'isPublished' => true,
                ],
                [
                    'title' => 'Conditions générales d\'utilisation',
                    'slug' => 'conditions-generales-d-utilisation',
                    'summary' => 'Les conditions générales d\'utilisation du site : accès au service, droits et obligations de chacun, propriété intellectuelle et responsabilités.',
                    'model' => 'france/terms-of-use',
                    'changeFrequency' => 'yearly',
                    'priority' => 1,
                    'isPublished' => true,
                ],
                [
                    'title' => 'Conditions générales de vente',
                    'slug' => 'conditions-generales-de-vente',
                    'summary' => 'Les conditions générales de vente : commande, prix, paiement, livraison, droit de rétractation et garanties applicables aux achats sur ce site.',
                    'model' => 'france/terms-of-sales',
                    'changeFrequency' => 'yearly',
                    'priority' => 1,
                    'isPublished' => false,
                    'requiresClass' => 'c975L\\ShopBundle\\c975LShopBundle',
                ],
                [
                    'title' => 'Utilisation des cookies',
                    'slug' => 'cookies',
                    'summary' => 'Quels cookies ce site dépose, à quoi ils servent, combien de temps ils sont conservés, et comment accepter, refuser ou modifier votre choix.',
                    'model' => 'france/cookies',
                    'changeFrequency' => 'yearly',
                    'priority' => 1,
                    'isPublished' => true,
                ],
                [
                    'title' => 'Copyright',
                    'slug' => 'copyright',
                    'summary' => 'Les conditions de réutilisation des contenus de ce site : textes, images et documents, droits d\'auteur et démarche pour demander une autorisation.',
                    'model' => 'france/copyright',
                    'changeFrequency' => 'yearly',
                    'priority' => 1,
                    'isPublished' => true,
                ],
                [
                    'title' => 'Créer un compte',
                    'slug' => 'creer-un-compte',
                    'summary' => 'Créez votre compte en quelques instants pour accéder à votre espace personnel et suivre vos demandes.',
                    'changeFrequency' => 'yearly',
                    'priority' => 1,
                    'isPublished' => true,
                    'isIndexable' => false,
                    'block' => ['kind' => 'form', 'data' => ['name' => 'register']],
                ],
                [
                    'title' => 'Mot de passe oublié',
                    'slug' => 'mot-de-passe-oublie',
                    'summary' => 'Mot de passe oublié ? Indiquez votre adresse email pour recevoir un lien de réinitialisation et retrouver l\'accès à votre compte.',
                    'changeFrequency' => 'yearly',
                    'priority' => 1,
                    'isPublished' => true,
                    'isIndexable' => false,
                    'block' => ['kind' => 'form', 'data' => ['name' => 'reset_password_request']],
                ],
                [
                    'title' => 'Contact',
                    'slug' => 'contact',
                    'summary' => 'Un formulaire pour nous écrire : question, demande d\'information ou de devis. Nous vous répondons dans les meilleurs délais.',
                    'changeFrequency' => 'yearly',
                    'priority' => 1,
                    'isPublished' => true,
                    'block' => ['kind' => 'form', 'data' => ['name' => 'contact']],
                ],
            ],
            'en' => [
                [
                    'title' => 'Home',
                    'slug' => 'home',
                    'changeFrequency' => 'daily',
                    'priority' => 10,
                    'isPublished' => true,
                ],
                [
                    'title' => 'Legal notice',
                    'slug' => 'legal-notice',
                    'summary' => 'Legal notice for this website: publisher, publication director, hosting provider and contact details, as required by applicable regulations.',
                    'model' => 'france/legal-notice',
                    'changeFrequency' => 'yearly',
                    'priority' => 1,
                    'isPublished' => true,
                ],
                [
                    'title' => 'Privacy policy',
                    'slug' => 'privacy-policy',
                    'summary' => 'How your personal data is collected, used and stored on this website, and how to exercise your rights of access, correction and deletion.',
                    'model' => 'france/privacy-policy',
                    'changeFrequency' => 'yearly',
                    'priority' => 1,
                    'isPublished' => true,
                ],
                [
                    'title' => 'Terms of use',
                    'slug' => 'terms-of-use',
                    'summary' => 'The terms of use of this website: access to the service, rights and obligations of each party, intellectual property and liability.',
                    'model' => 'france/terms-of-use',
                    'changeFrequency' => 'yearly',
                    'priority' => 1,
                    'isPublished' => true,
                ],
                [
                    'title' => 'Terms of sales',
                    'slug' => 'terms-of-sales',
                    'summary' => 'The terms of sale: ordering, prices, payment, delivery, right of withdrawal and warranties applying to purchases made on this website.',
                    'model' => 'france/terms-of-sales',
                    'changeFrequency' => 'yearly',
                    'priority' => 1,
                    'isPublished' => false,
                ],
                [
                    'title' => 'Cookies usage',
                    'slug' => 'cookies-usage',
                    'summary' => 'Which cookies this website sets, what they are used for, how long they are kept, and how to accept, refuse or change your choice.',
                    'model' => 'france/cookies',
                    'changeFrequency' => 'yearly',
                    'priority' => 1,
                    'isPublished' => true,
                ],
                [
                    'title' => 'Copyright',
                    'slug' => 'copyright-notice',
                    'summary' => 'The conditions for reusing the contents of this website: texts, images and documents, copyright and how to request permission.',
                    'model' => 'france/copyright',
                    'changeFrequency' => 'yearly',
                    'priority' => 1,
                    'isPublished' => true,
                ],
                [
                    'title' => 'Register',
                    'slug' => 'register',
                    'summary' => 'Create your account in a few moments to access your personal area and keep track of your requests.',
                    'changeFrequency' => 'yearly',
                    'priority' => 1,
                    'isPublished' => true,
                    'isIndexable' => false,
                    'block' => ['kind' => 'form', 'data' => ['name' => 'register']],
                ],
                [
                    'title' => 'Forgot password',
                    'slug' => 'forgot-password',
                    'summary' => 'Forgot your password? Enter your email address to receive a reset link and get back into your account.',
                    'changeFrequency' => 'yearly',
                    'priority' => 1,
                    'isPublished' => true,
                    'isIndexable' => false,
                    'block' => ['kind' => 'form', 'data' => ['name' => 'reset_password_request']],
                ],
                [
                    'title' => 'Contact',
                    'slug' => 'contact',
                    'summary' => 'A form to write to us: a question, a request for information or a quote. We answer as quickly as we can.',
                    'changeFrequency' => 'yearly',
                    'priority' => 1,
                    'isPublished' => true,
                    'block' => ['kind' => 'form', 'data' => ['name' => 'contact']],
                ],
            ],
            'es' => [
                [
                    'title' => 'Inicio',
                    'slug' => 'home',
                    'changeFrequency' => 'daily',
                    'priority' => 10,
                    'isPublished' => true,
                ],
                [
                    'title' => 'Aviso legal',
                    'slug' => 'aviso-legal',
                    'summary' => 'Aviso legal del sitio: editor, director de publicación, proveedor de alojamiento y datos de contacto, conforme a la normativa vigente.',
                    'model' => 'france/legal-notice',
                    'changeFrequency' => 'yearly',
                    'priority' => 1,
                    'isPublished' => true,
                ],
                [
                    'title' => 'Política de privacidad',
                    'slug' => 'politica-de-privacidad',
                    'summary' => 'Cómo se recogen, utilizan y conservan sus datos personales en este sitio, y cómo ejercer sus derechos de acceso, rectificación y supresión.',
                    'model' => 'france/privacy-policy',
                    'changeFrequency' => 'yearly',
                    'priority' => 1,
                    'isPublished' => true,
                ],
                [
                    'title' => 'Condiciones de uso',
                    'slug' => 'condiciones-de-uso',
                    'summary' => 'Las condiciones de uso del sitio: acceso al servicio, derechos y obligaciones de cada parte, propiedad intelectual y responsabilidades.',
                    'model' => 'france/terms-of-use',
                    'changeFrequency' => 'yearly',
                    'priority' => 1,
                    'isPublished' => true,
                ],
                [
                    'title' => 'Condiciones de venta',
                    'slug' => 'condiciones-de-venta',
                    'summary' => 'Las condiciones de venta: pedido, precios, pago, entrega, derecho de desistimiento y garantías aplicables a las compras en este sitio.',
                    'model' => 'france/terms-of-sales',
                    'changeFrequency' => 'yearly',
                    'priority' => 1,
                    'isPublished' => false,
                ],
                [
                    'title' => 'Uso de cookies',
                    'slug' => 'uso-de-cookies',
                    'summary' => 'Qué cookies deposita este sitio, para qué sirven, cuánto tiempo se conservan y cómo aceptar, rechazar o modificar su elección.',
                    'model' => 'france/cookies',
                    'changeFrequency' => 'yearly',
                    'priority' => 1,
                    'isPublished' => true,
                ],
                [
                    'title' => 'Copyright',
                    'slug' => 'aviso-de-copyright',
                    'summary' => 'Las condiciones de reutilización de los contenidos de este sitio: textos, imágenes y documentos, derechos de autor y cómo pedir autorización.',
                    'model' => 'france/copyright',
                    'changeFrequency' => 'yearly',
                    'priority' => 1,
                    'isPublished' => true,
                ],
                [
                    'title' => 'Crear una cuenta',
                    'slug' => 'crear-una-cuenta',
                    'summary' => 'Cree su cuenta en unos instantes para acceder a su espacio personal y hacer seguimiento de sus solicitudes.',
                    'changeFrequency' => 'yearly',
                    'priority' => 1,
                    'isPublished' => true,
                    'isIndexable' => false,
                    'block' => ['kind' => 'form', 'data' => ['name' => 'register']],
                ],
                [
                    'title' => 'Contraseña olvidada',
                    'slug' => 'contrasena-olvidada',
                    'summary' => '¿Ha olvidado su contraseña? Indique su correo electrónico para recibir un enlace de restablecimiento y recuperar el acceso.',
                    'changeFrequency' => 'yearly',
                    'priority' => 1,
                    'isPublished' => true,
                    'isIndexable' => false,
                    'block' => ['kind' => 'form', 'data' => ['name' => 'reset_password_request']],
                ],
                [
                    'title' => 'Contacto',
                    'slug' => 'contacto',
                    'summary' => 'Un formulario para escribirnos: una pregunta, una solicitud de información o de presupuesto. Le respondemos lo antes posible.',
                    'changeFrequency' => 'yearly',
                    'priority' => 1,
                    'isPublished' => true,
                    'block' => ['kind' => 'form', 'data' => ['name' => 'contact']],
                ],
            ],
        ];
    }
}
