<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\SiteBundle\Service;

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
    // name => [type, label, placeholder, url], one set per locale - unlike ContactFormBundle's own DefaultFormsImporter (whose labels are translation keys, rendered by its own ContactFormType with a real translation_domain), the generic "form" Block's FormSubmissionType renders FormField labels as literal text (translation_domain: false, an admin is expected to type real text, not a key) - so these have to be actual words, picked once for kernel.default_locale since Form::$name is unique site-wide (one "contact" Form, not one per locale). "url" is only ever set on REGISTER_CORE_FIELDS' "cgu" entry below, appended as a clickable link next to its label (see FormSubmissionType)
    private const CONTACT_CORE_FIELDS = [
        'fr' => [
            'name' => [FormField::TYPE_TEXT, 'Nom', 'Jean Dupont', null],
            'email' => [FormField::TYPE_EMAIL, 'Email', 'email', null],
            'subject' => [FormField::TYPE_TEXT, 'Sujet', null, null],
            'message' => [FormField::TYPE_TEXTAREA, 'Message', null, null],
        ],
        'en' => [
            'name' => [FormField::TYPE_TEXT, 'Name', 'John Doe', null],
            'email' => [FormField::TYPE_EMAIL, 'Email', 'john.doe@example.com', null],
            'subject' => [FormField::TYPE_TEXT, 'Subject', null, null],
            'message' => [FormField::TYPE_TEXTAREA, 'Message', null, null],
        ],
        'es' => [
            'name' => [FormField::TYPE_TEXT, 'Nombre', 'Juan Pérez', null],
            'email' => [FormField::TYPE_EMAIL, 'Email', 'juan.perez@ejemplo.es', null],
            'subject' => [FormField::TYPE_TEXT, 'Asunto', null, null],
            'message' => [FormField::TYPE_TEXTAREA, 'Mensaje', null, null],
        ],
    ];

    // Same shape as CONTACT_CORE_FIELDS, for the "register" Form - processed the same generic way as "contact" (c975L\UiBundle\Controller\FormController), see scaffold's App\Service\RegisterFormAction for the "register" FormActionInterface key. "cgu"'s url points at that locale's own terms-of-use legal page, seeded a few lines below in each locale's page list - kept as a plain relative "/pages/{slug}" path (no router involved here) since it's only ever read back once by FormSubmissionType, exactly like every other seeded field
    private const REGISTER_CORE_FIELDS = [
        'fr' => [
            'email' => [FormField::TYPE_EMAIL, 'Email', 'email', null],
            'plainPassword' => [FormField::TYPE_PASSWORD_REPEATED, 'Mot de passe', null, null],
            'cgu' => [FormField::TYPE_CHECKBOX, 'J\'accepte les conditions générales d\'utilisation', null, '/pages/conditions-generales-d-utilisation'],
        ],
        'en' => [
            'email' => [FormField::TYPE_EMAIL, 'Email', 'john.doe@example.com', null],
            'plainPassword' => [FormField::TYPE_PASSWORD_REPEATED, 'Password', null, null],
            'cgu' => [FormField::TYPE_CHECKBOX, 'I accept the terms of use', null, '/pages/terms-of-use'],
        ],
        'es' => [
            'email' => [FormField::TYPE_EMAIL, 'Email', 'juan.perez@ejemplo.es', null],
            'plainPassword' => [FormField::TYPE_PASSWORD_REPEATED, 'Contraseña', null, null],
            'cgu' => [FormField::TYPE_CHECKBOX, 'Acepto las condiciones de uso', null, '/pages/condiciones-de-uso'],
        ],
    ];

    // Same shape as CONTACT_CORE_FIELDS, for the "reset_password_request" Form - see scaffold's App\Service\ResetPasswordRequestFormAction for the "reset_password_request" FormActionInterface key
    private const RESET_PASSWORD_REQUEST_CORE_FIELDS = [
        'fr' => [
            'email' => [FormField::TYPE_EMAIL, 'Email', 'email', null],
        ],
        'en' => [
            'email' => [FormField::TYPE_EMAIL, 'Email', 'john.doe@example.com', null],
        ],
        'es' => [
            'email' => [FormField::TYPE_EMAIL, 'Email', 'juan.perez@ejemplo.es', null],
        ],
    ];

    // One set of EmailBlock defs per locale, each a [type, heading, level, content, label, url] tuple (unused
    // positions left null) - see ensureEmailTemplateExists(). "contact_notification" is embedded via
    // email_template_body() inside templates/emails/contact_notification.html.twig, itself referenced by the
    // "contact" Form's actionConfig.template (see ensureFormExists() call below); its {{ form_name }}/{{ fields }}
    // placeholders are resolved by EmailTemplateRenderer at send time
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

    // "{{ signed_url }}"/"{{ expires_at }}" are resolved by EmailVerifier's caller (App\Service\RegisterFormAction) - see
    // templates/emails/confirmation_email.html.twig
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

    // "{{ reset_url }}"/"{{ expires_at }}" are resolved by App\Service\ResetPasswordRequestFormAction - see
    // templates/emails/reset_password_email.html.twig
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

    // $onPage, if given, is called for each page not yet in database as fn(array $def): array{import: bool, isPublished: bool} and lets a command decide interactively whether to import it and with which isPublished value. Without it (default), every page is imported using the isPublished value from getDefinitions(). Returns ['created' => int, 'skipped' => int]
    public function import(?callable $onPage = null): array
    {
        $created = 0;
        $skipped = 0;
        $backfilled = false;
        $now = new \DateTime();
        $user = $this->security->getUser();
        $definitions = $this->getDefinitions();

        // Always imports the default locale, plus any locale declared in framework.enabled_locales; the default locale comes first so the homepage keeps a deterministic title
        $locales = array_unique([$this->defaultLocale, ...$this->enabledLocales]);

        foreach ($locales as $locale) {
            if (!isset($definitions[$locale])) {
                continue;
            }

            foreach ($definitions[$locale] as $def) {
                // Skips definitions tied to a bundle (i.e. Shop's "terms of sales") that isn't installed
                if (isset($def['requiresClass']) && !class_exists($def['requiresClass'])) {
                    continue;
                }

                if ($this->pageRepository->findOneBy(['slug' => $def['slug']])) {
                    $skipped++;
                    // The page itself already exists, but its "form" Block's own Form/EmailTemplate might still be
                    // missing (see formBlockNameFromPageDef()/ensureFormAndEmailTemplateExist()) - buildPage() below
                    // never runs for this definition since it's about to be skipped
                    $formName = $this->formBlockNameFromPageDef($def);
                    if (null !== $formName) {
                        $this->ensureFormAndEmailTemplateExist($formName);
                        $backfilled = true;
                    }
                    continue;
                }

                if (null !== $onPage) {
                    $decision = $onPage($def);
                    if (!$decision['import']) {
                        continue;
                    }
                    $def['isPublished'] = $decision['isPublished'];
                }

                $this->em->persist($this->buildPage($def, $now, $user));
                $created++;
            }
        }

        if ($created > 0 || $backfilled) {
            $this->em->flush();
        }

        return ['created' => $created, 'skipped' => $skipped];
    }

    private function buildPage(array $def, \DateTime $now, mixed $user): Page
    {
        $page = (new Page())
            ->setTitle($def['title'])
            ->setSlug($def['slug'])
            ->setChangeFrequency($def['changeFrequency'])
            ->setPriority($def['priority'])
            ->setIsPublished($def['isPublished'])
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
            if ($existing->isRestricted() && $existing->getAction() !== $action) {
                $existing->setAction($action);
                $existing->setActionConfig($actionConfig);
                $this->em->persist($existing);
            }

            foreach ($existing->getFields() as $existingField) {
                $url = $fields[$existingField->getName()][3] ?? null;
                if ($existingField->isRestricted() && null === $existingField->getUrl() && null !== $url) {
                    $existingField->setUrl($url);
                    $this->em->persist($existingField);
                }
            }

            return;
        }

        $form = (new Form())
            ->setName($name)
            ->setAction($action)
            ->setRestricted(true)
            ->setActionConfig($actionConfig);

        $position = 0;
        foreach ($fields as $fieldName => [$type, $label, $placeholder, $url]) {
            $field = (new FormField())
                ->setName($fieldName)
                ->setLabel($label)
                ->setType($type)
                ->setPlaceholder($placeholder)
                ->setUrl($url)
                ->setRequired(true)
                ->setPosition($position++)
                ->setRestricted(true)
            ;
            $form->addField($field);
        }

        $this->em->persist($form);
    }

    // Idempotent - seeds a restricted c975L\UiBundle\Entity\EmailTemplate (name locked, blocks still editable, see
    // EmailTemplateCrudController), same principle as ensureFormExists() above. $blocksByLocale entries are
    // [type, heading, level, content, label, url] tuples (see EmailBlock's own docblock for what each kind uses)
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
                    'title'           => 'Accueil',
                    'slug'            => 'home',
                    'changeFrequency' => 'daily',
                    'priority'        => 10,
                    'isPublished'        => true,
                ],
                [
                    'title'           => 'Mentions légales',
                    'slug'            => 'mentions-legales',
                    'model'           => 'france/legal-notice',
                    'changeFrequency' => 'yearly',
                    'priority'        => 1,
                    'isPublished'        => true,
                ],
                [
                    'title'           => 'Règles de confidentialité',
                    'slug'            => 'regles-de-confidentialite',
                    'model'           => 'france/privacy-policy',
                    'changeFrequency' => 'yearly',
                    'priority'        => 1,
                    'isPublished'        => true,
                ],
                [
                    'title'           => 'Conditions générales d\'utilisation',
                    'slug'            => 'conditions-generales-d-utilisation',
                    'model'           => 'france/terms-of-use',
                    'changeFrequency' => 'yearly',
                    'priority'        => 1,
                    'isPublished'        => true,
                ],
                [
                    'title'           => 'Conditions générales de vente',
                    'slug'            => 'conditions-generales-de-vente',
                    'model'           => 'france/terms-of-sales',
                    'changeFrequency' => 'yearly',
                    'priority'        => 1,
                    'isPublished'        => false,
                    'requiresClass'   => 'c975L\\ShopBundle\\c975LShopBundle',
                ],
                [
                    'title'           => 'Utilisation des cookies',
                    'slug'            => 'cookies',
                    'model'           => 'france/cookies',
                    'changeFrequency' => 'yearly',
                    'priority'        => 1,
                    'isPublished'        => true,
                ],
                [
                    'title'           => 'Copyright',
                    'slug'            => 'copyright',
                    'model'           => 'france/copyright',
                    'changeFrequency' => 'yearly',
                    'priority'        => 1,
                    'isPublished'        => true,
                ],
                [
                    'title'           => 'Créer un compte',
                    'slug'            => 'creer-un-compte',
                    'changeFrequency' => 'yearly',
                    'priority'        => 1,
                    'isPublished'        => true,
                    'block'           => ['kind' => 'form', 'data' => ['name' => 'register']],
                ],
                [
                    'title'           => 'Mot de passe oublié',
                    'slug'            => 'mot-de-passe-oublie',
                    'changeFrequency' => 'yearly',
                    'priority'        => 1,
                    'isPublished'        => true,
                    'block'           => ['kind' => 'form', 'data' => ['name' => 'reset_password_request']],
                ],
                [
                    'title'           => 'Contact',
                    'slug'            => 'contact',
                    'changeFrequency' => 'yearly',
                    'priority'        => 1,
                    'isPublished'        => true,
                    'block'           => ['kind' => 'form', 'data' => ['name' => 'contact']],
                ],
            ],
            'en' => [
                [
                    'title'           => 'Home',
                    'slug'            => 'home',
                    'changeFrequency' => 'daily',
                    'priority'        => 10,
                    'isPublished'        => true,
                ],
                [
                    'title'           => 'Legal notice',
                    'slug'            => 'legal-notice',
                    'model'           => 'france/legal-notice',
                    'changeFrequency' => 'yearly',
                    'priority'        => 1,
                    'isPublished'        => true,
                ],
                [
                    'title'           => 'Privacy policy',
                    'slug'            => 'privacy-policy',
                    'model'           => 'france/privacy-policy',
                    'changeFrequency' => 'yearly',
                    'priority'        => 1,
                    'isPublished'        => true,
                ],
                [
                    'title'           => 'Terms of use',
                    'slug'            => 'terms-of-use',
                    'model'           => 'france/terms-of-use',
                    'changeFrequency' => 'yearly',
                    'priority'        => 1,
                    'isPublished'        => true,
                ],
                [
                    'title'           => 'Terms of sales',
                    'slug'            => 'terms-of-sales',
                    'model'           => 'france/terms-of-sales',
                    'changeFrequency' => 'yearly',
                    'priority'        => 1,
                    'isPublished'        => false,
                ],
                [
                    'title'           => 'Cookies usage',
                    'slug'            => 'cookies-usage',
                    'model'           => 'france/cookies',
                    'changeFrequency' => 'yearly',
                    'priority'        => 1,
                    'isPublished'        => true,
                ],
                [
                    'title'           => 'Copyright',
                    'slug'            => 'copyright-notice',
                    'model'           => 'france/copyright',
                    'changeFrequency' => 'yearly',
                    'priority'        => 1,
                    'isPublished'        => true,
                ],
                [
                    'title'           => 'Register',
                    'slug'            => 'register',
                    'changeFrequency' => 'yearly',
                    'priority'        => 1,
                    'isPublished'        => true,
                    'block'           => ['kind' => 'form', 'data' => ['name' => 'register']],
                ],
                [
                    'title'           => 'Forgot password',
                    'slug'            => 'forgot-password',
                    'changeFrequency' => 'yearly',
                    'priority'        => 1,
                    'isPublished'        => true,
                    'block'           => ['kind' => 'form', 'data' => ['name' => 'reset_password_request']],
                ],
                [
                    'title'           => 'Contact',
                    'slug'            => 'contact',
                    'changeFrequency' => 'yearly',
                    'priority'        => 1,
                    'isPublished'        => true,
                    'block'           => ['kind' => 'form', 'data' => ['name' => 'contact']],
                ],
            ],
            'es' => [
                [
                    'title'           => 'Inicio',
                    'slug'            => 'home',
                    'changeFrequency' => 'daily',
                    'priority'        => 10,
                    'isPublished'        => true,
                ],
                [
                    'title'           => 'Aviso legal',
                    'slug'            => 'aviso-legal',
                    'model'           => 'france/legal-notice',
                    'changeFrequency' => 'yearly',
                    'priority'        => 1,
                    'isPublished'        => true,
                ],
                [
                    'title'           => 'Política de privacidad',
                    'slug'            => 'politica-de-privacidad',
                    'model'           => 'france/privacy-policy',
                    'changeFrequency' => 'yearly',
                    'priority'        => 1,
                    'isPublished'        => true,
                ],
                [
                    'title'           => 'Condiciones de uso',
                    'slug'            => 'condiciones-de-uso',
                    'model'           => 'france/terms-of-use',
                    'changeFrequency' => 'yearly',
                    'priority'        => 1,
                    'isPublished'        => true,
                ],
                [
                    'title'           => 'Condiciones de venta',
                    'slug'            => 'condiciones-de-venta',
                    'model'           => 'france/terms-of-sales',
                    'changeFrequency' => 'yearly',
                    'priority'        => 1,
                    'isPublished'        => false,
                ],
                [
                    'title'           => 'Uso de cookies',
                    'slug'            => 'uso-de-cookies',
                    'model'           => 'france/cookies',
                    'changeFrequency' => 'yearly',
                    'priority'        => 1,
                    'isPublished'        => true,
                ],
                [
                    'title'           => 'Copyright',
                    'slug'            => 'aviso-de-copyright',
                    'model'           => 'france/copyright',
                    'changeFrequency' => 'yearly',
                    'priority'        => 1,
                    'isPublished'        => true,
                ],
                [
                    'title'           => 'Crear una cuenta',
                    'slug'            => 'crear-una-cuenta',
                    'changeFrequency' => 'yearly',
                    'priority'        => 1,
                    'isPublished'        => true,
                    'block'           => ['kind' => 'form', 'data' => ['name' => 'register']],
                ],
                [
                    'title'           => 'Contraseña olvidada',
                    'slug'            => 'contrasena-olvidada',
                    'changeFrequency' => 'yearly',
                    'priority'        => 1,
                    'isPublished'        => true,
                    'block'           => ['kind' => 'form', 'data' => ['name' => 'reset_password_request']],
                ],
                [
                    'title'           => 'Contacto',
                    'slug'            => 'contacto',
                    'changeFrequency' => 'yearly',
                    'priority'        => 1,
                    'isPublished'        => true,
                    'block'           => ['kind' => 'form', 'data' => ['name' => 'contact']],
                ],
            ],
        ];
    }
}
