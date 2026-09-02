<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\SiteBundle\Tests\Service;

use c975L\SiteBundle\Service\EmailLayoutTemplateProvider;
use c975L\UiBundle\Entity\EmailBlock;
use PHPUnit\Framework\TestCase;
use Symfony\Contracts\Translation\TranslatorInterface;

class EmailLayoutTemplateProviderTest extends TestCase
{
    private function createProvider(): EmailLayoutTemplateProvider
    {
        $translator = $this->createStub(TranslatorInterface::class);
        $translator->method('trans')->willReturnCallback(static fn (string $id, array $parameters, ?string $domain, ?string $locale): string => $id . '@' . $locale);

        return new EmailLayoutTemplateProvider($translator);
    }

    // The four sentences fullLayout.html.twig reads by name, each seeded in every language this bundle ships a catalogue for
    public function testEveryLayoutTemplateIsDeclaredInThreeLocales(): void
    {
        $templates = $this->createProvider()->getEmailTemplates();

        $this->assertSame(['layout_no_spam', 'layout_hello', 'layout_closing', 'layout_sent_by'], array_keys($templates));

        foreach ($templates as $name => $byLocale) {
            $this->assertSame(['fr', 'en', 'es'], array_keys($byLocale), sprintf('"%s" does not ship every locale', $name));
        }
    }

    // Html and not text: the signature carries a line break and the site's name in bold, which a text block would escape
    public function testEachTemplateHoldsASingleHtmlBlockCarryingItsOwnWording(): void
    {
        $blocks = $this->createProvider()->getEmailTemplates()['layout_closing']['en'];

        $this->assertCount(1, $blocks);
        $this->assertSame(EmailBlock::TYPE_HTML, $blocks[0][0]);
        $this->assertSame('text.email_layout_closing@en', $blocks[0][3]);
    }

    // Each locale reads its own catalogue rather than falling back on the site's, a Spanish row otherwise holding French sentences
    public function testEachLocaleIsTranslatedInItsOwnLanguage(): void
    {
        $templates = $this->createProvider()->getEmailTemplates();

        $this->assertSame('text.email_layout_hello@fr', $templates['layout_hello']['fr'][0][3]);
        $this->assertSame('text.email_layout_hello@es', $templates['layout_hello']['es'][0][3]);
    }
}
