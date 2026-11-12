<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\SiteBundle\Tests;

use c975L\ConfigBundle\Test\CodeRulesTestCase;

// The audit's rules replayed on the bundle's code and templates (see ConfigBundle's README, "CodeRulesTestCase")
class CodeRulesTest extends CodeRulesTestCase
{
    protected function sourceDirectory(): string
    {
        return __DIR__ . '/../src';
    }

    protected function sourceNamespace(): string
    {
        return 'c975L\SiteBundle';
    }

    // The legitimate cases, each with its reason
    protected function allowed(): array
    {
        return [
            self::PRE_FLUSH => [
                'c975L\SiteBundle\Entity\Page' => 'Page::unreferenceWhenUnpublished() is the entity\'s own callback, run for Page rows alone and writing only its own isIndexable from its own isPublished, with no query: a PreUpdate write would come after the change set is computed and be lost',
            ],
            self::RAW => [
                'emails/emailTemplateLayout.html.twig: bodyHtml|raw' => 'The e-mail body EmailTemplateRenderer has already rendered through each block\'s own Twig template, escaped there',
                'pages/page.html.twig: detailHtml|raw' => 'The collection detail page PageController has already rendered through _blocks.html.twig, escaped there',
            ],
        ];
    }
}
