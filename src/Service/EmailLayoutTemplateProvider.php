<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\SiteBundle\Service;

use c975L\UiBundle\Contract\EmailTemplateProviderInterface;
use c975L\UiBundle\Entity\EmailBlock;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * The four sentences that wrap every e-mail this site sends: the anti-spam note, the greeting, the closing and the
 * signature (see templates/emails/fullLayout.html.twig, which reads them by name).
 *
 * They used to be four config entries - "email-text-no-spam" and the rest - which meant a bilingual site said them
 * in one language to everyone. A template is one row per language, written on the screen where the rest of a site's
 * e-mails are written, so the recipient reads them in theirs (see EmailTemplateRenderer::renderNamedBody).
 *
 * One template apiece rather than four slots of a single one: an e-mail block's "label" is a button's own wording,
 * editable, so it cannot serve as the key a layout looks a fragment up by - a name can, and a restricted row keeps it.
 */
class EmailLayoutTemplateProvider implements EmailTemplateProviderInterface
{
    // Where fullLayout.html.twig puts each of them => the catalogue key its default wording is written under
    public const array TEMPLATES = [
        'layout_no_spam' => 'text.email_layout_no_spam',
        'layout_hello' => 'text.email_layout_hello',
        'layout_closing' => 'text.email_layout_closing',
        'layout_sent_by' => 'text.email_layout_sent_by',
    ];

    // The languages this bundle ships a catalogue for - listed rather than read from the site's own, the translator answering every locale by falling back on the default one and a Spanish row would hold French sentences
    private const array LOCALES = ['fr', 'en', 'es'];

    public function __construct(
        private readonly TranslatorInterface $translator,
    ) {
    }

    public function getEmailTemplates(): array
    {
        $templates = [];

        foreach (self::TEMPLATES as $name => $key) {
            foreach (self::LOCALES as $locale) {
                // One html block and nothing else: these are sentences, not composed e-mails - an admin is free to add to them, and the layout renders whatever the row holds
                // Html rather than text because the signature carries a line break and the site's name in bold, which a text block would escape
                $templates[$name][$locale] = [
                    [EmailBlock::TYPE_HTML, null, null, $this->translator->trans($key, [], 'site', $locale), null, null],
                ];
            }
        }

        return $templates;
    }
}
