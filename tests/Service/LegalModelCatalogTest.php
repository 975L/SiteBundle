<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\SiteBundle\Tests\Service;

use c975L\SiteBundle\Service\LegalModelCatalog;
use PHPUnit\Framework\TestCase;

class LegalModelCatalogTest extends TestCase
{
    public function testChoicesAreGroupedByCountry(): void
    {
        $choices = (new LegalModelCatalog())->choices();

        $this->assertArrayHasKey('France', $choices);
        $this->assertSame('france/cookies', $choices['France']['label.cookies_policy']);
    }

    public function testAllFlattensEveryCountryIntoOneList(): void
    {
        $all = (new LegalModelCatalog())->all();

        $this->assertContains('france/cookies', $all);
        $this->assertContains('france/terms-of-use', $all);
        $this->assertSame(array_values($all), $all);
        $this->assertSame(array_unique($all), $all);
    }

    // What keeps a value read from the database out of a template path
    public function testHasOnlyAnswersTrueForAShippedModel(): void
    {
        $catalog = new LegalModelCatalog();

        $this->assertTrue($catalog->has('france/legal-notice'));
        $this->assertFalse($catalog->has('elsewhere/invented'));
        $this->assertFalse($catalog->has('../../../etc/passwd'));
        $this->assertFalse($catalog->has(''));
    }

    public function testLabelReturnsTheTranslationKeyOfAShippedModel(): void
    {
        $this->assertSame('label.privacy_policy', (new LegalModelCatalog())->label('france/privacy-policy'));
    }

    // An identifier nothing ships is shown as it is rather than silently labelled as something else
    public function testLabelFallsBackOnTheIdentifierItself(): void
    {
        $this->assertSame('elsewhere/invented', (new LegalModelCatalog())->label('elsewhere/invented'));
    }

    public function testEveryShippedModelHasATemplateInTheAuthoringLocale(): void
    {
        foreach ((new LegalModelCatalog())->all() as $model) {
            $this->assertFileExists(
                \dirname(__DIR__, 2) . '/templates/models/' . $model . '.' . LegalModelCatalog::FALLBACK_LOCALE . '.html.twig',
            );
        }
    }
}
