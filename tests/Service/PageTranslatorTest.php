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
use c975L\SiteBundle\Service\PageTranslator;
use c975L\UiBundle\Service\ContentTranslator;
use PHPUnit\Framework\TestCase;

class PageTranslatorTest extends TestCase
{
    private function createPage(): Page
    {
        $page = new Page();
        $page->setSlug('ateliers')->setTitle('Nos ateliers')->setSummarySocialNetwork('Un atelier par mois');
        new \ReflectionProperty(Page::class, 'id')->setValue($page, 12);

        return $page;
    }

    // A site declaring a single language returns what it has always returned: the content translator answers it without reading anything
    public function testTheTextsAreLeftAloneWhenNothingIsTranslated(): void
    {
        $contentTranslator = $this->createStub(ContentTranslator::class);
        $contentTranslator->method('translate')->willReturnArgument(2);

        $pageTranslator = new PageTranslator($contentTranslator);
        $page = $this->createPage();

        $this->assertSame('Nos ateliers', $pageTranslator->getTitle($page));
        $this->assertSame('Un atelier par mois', $pageTranslator->getSummarySocialNetwork($page));
    }

    public function testTheTranslatedTitleIsTheOneRead(): void
    {
        $contentTranslator = $this->createStub(ContentTranslator::class);
        $contentTranslator->method('translate')->willReturn(['title' => 'Our workshops', 'summarySocialNetwork' => 'One a month']);

        $pageTranslator = new PageTranslator($contentTranslator);

        $this->assertSame('Our workshops', $pageTranslator->getTitle($this->createPage()));
    }

    // A page never saved has no id: nothing to hang a translation on, and nothing to read
    public function testAPageWithNoIdReadsNothing(): void
    {
        $contentTranslator = $this->createMock(ContentTranslator::class);
        $contentTranslator->expects($this->never())->method('all');

        $this->assertSame([], new PageTranslator($contentTranslator)->all(new Page()));
    }

    // What a language screen offers: what that language already says, and the source text between brackets where it says nothing yet - both the thing to translate and the mark of what is left to do
    public function testALanguageScreenIsOfferedTheTranslationOrTheBracketedSource(): void
    {
        $contentTranslator = $this->createStub(ContentTranslator::class);
        $contentTranslator->method('isActive')->willReturn(true);
        $contentTranslator->method('all')->willReturn(['es' => ['title' => 'Nuestros talleres']]);

        $values = new PageTranslator($contentTranslator)->promptValues($this->createPage(), 'es');

        $this->assertSame('Nuestros talleres', $values['title']);
        $this->assertSame('[Un atelier par mois]', $values['summarySocialNetwork']);
    }

    // Left as it was offered, the bracketed source is a prompt to translate rather than a translation: stored, it would come out on the front reading "[Un atelier par mois]"
    public function testAFieldHandedBackHoldingItsBracketsIsStagedAsNothing(): void
    {
        $contentTranslator = $this->createMock(ContentTranslator::class);
        $contentTranslator->expects($this->once())
            ->method('stage')
            ->with(PageTranslator::OWNER, 12, 'es', ['title' => 'Nuestros talleres', 'summarySocialNetwork' => null]);

        new PageTranslator($contentTranslator)->stage($this->createPage(), 'es', [
            'title' => 'Nuestros talleres',
            'summarySocialNetwork' => '[Un atelier par mois]',
        ]);
    }

    // A page that has never been saved has no id to name in the translation table
    public function testAPageWithNoIdStagesNothing(): void
    {
        $contentTranslator = $this->createMock(ContentTranslator::class);
        $contentTranslator->expects($this->never())->method('stage');

        new PageTranslator($contentTranslator)->stage(new Page()->setTitle('x'), 'es', ['title' => 'y']);
    }
}
