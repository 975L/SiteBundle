<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\SiteBundle\Tests\Service;

use c975L\ConfigBundle\Service\ConfigServiceInterface;
use c975L\SiteBundle\Service\LegalModelCatalog;
use c975L\SiteBundle\Service\LegalModelPlaceholders;
use c975L\SiteBundle\Service\LegalModelRenderer;
use PHPUnit\Framework\TestCase;
use Twig\Environment;
use Twig\Loader\ArrayLoader;

class LegalModelRendererTest extends TestCase
{
    private const MODEL = 'france/cookies';

    // A stand-in for a real model, same shape as the shipped ones: an untagged "latest update" paragraph, a
    // tagged loose <div>, a plain <section>, and a <section> holding two <h3> sub-units
    private const TEMPLATE = <<<'HTML'
        <div class="legal">
        	<p class="text text-center">Updated: {{ latestUpdate }}</p>
        	<div data-legal-id="intro">Intro of %site-name%</div>
        	<section data-legal-id="one"><h2>One</h2><div>Body one</div></section>
        	<section data-legal-id="two"><h2>Two</h2><div>Lead two</div><h3 data-legal-id="two.a">A</h3><div>Body A</div><h3 data-legal-id="two.b">B</h3><div>Body B</div></section>
        </div>
        HTML;

    public function testRendersModelUntouchedAndResolvesPlaceholdersWhenNothingIsCustomized(): void
    {
        $html = $this->renderer()->render(self::MODEL, '2026-01-01', [], 'fr');

        $this->assertStringContainsString('Intro of Acme', $html);
        $this->assertStringNotContainsString('%site-name%', $html);
        $this->assertStringContainsString('Updated: 2026-01-01', $html);
        $this->assertStringContainsString('<h2>One</h2>', $html);
        $this->assertStringContainsString('Body B', $html);
    }

    // The whole point of the sparse delta: an untouched block must not even go through the DOM
    public function testUncustomizedOutputIsTheTemplateVerbatim(): void
    {
        $this->assertSame(
            str_replace(['%site-name%', '{{ latestUpdate }}'], ['Acme', ''], self::TEMPLATE),
            $this->renderer()->render(self::MODEL, null, [], 'fr'),
        );
    }

    public function testUnknownModelRendersNothing(): void
    {
        $this->assertSame('', $this->renderer()->render('elsewhere/invented', null, [], 'fr'));
    }

    // A locale the bundle has no template for falls back on the authoring one rather than throwing
    public function testUnknownLocaleFallsBackOnTheAuthoringLocale(): void
    {
        $this->assertStringContainsString('<h2>One</h2>', $this->renderer()->render(self::MODEL, null, [], 'de'));
    }

    public function testHiddenSectionDisappearsAndTheRestSurvives(): void
    {
        $html = $this->renderer()->render(self::MODEL, null, ['hidden' => ['one']], 'fr');

        $this->assertStringNotContainsString('Body one', $html);
        $this->assertStringNotContainsString('<h2>One</h2>', $html);
        $this->assertStringContainsString('<h2>Two</h2>', $html);
        $this->assertStringContainsString('Intro of Acme', $html);
    }

    public function testHiddenSubSectionTakesItsBodyWithItAndLeavesItsSiblingsAlone(): void
    {
        $html = $this->renderer()->render(self::MODEL, null, ['hidden' => ['two.a']], 'fr');

        $this->assertStringNotContainsString('Body A', $html);
        $this->assertStringNotContainsString('>A</h3>', $html);
        $this->assertStringContainsString('Body B', $html);
        $this->assertStringContainsString('Lead two', $html);
    }

    public function testOverrideReplacesTheBodyButKeepsTheBundleHeading(): void
    {
        $html = $this->renderer()->render(self::MODEL, null, [
            'overrides' => ['one' => ['content' => '<p>Ours, at %site-name%</p>']],
        ], 'fr');

        $this->assertStringContainsString('<h2>One</h2>', $html);
        $this->assertStringContainsString('<p>Ours, at Acme</p>', $html);
        $this->assertStringNotContainsString('Body one', $html);
    }

    public function testOverrideCanRetitleTheSection(): void
    {
        $html = $this->renderer()->render(self::MODEL, null, [
            'overrides' => ['one' => ['title' => 'Renamed', 'content' => '<p>Ours</p>']],
        ], 'fr');

        $this->assertStringContainsString('<h2>Renamed</h2>', $html);
        $this->assertStringNotContainsString('<h2>One</h2>', $html);
    }

    // A section's own override only ever touches its lead, never the sub-sections that follow it
    public function testOverridingASectionLeavesItsSubSectionsAlone(): void
    {
        $html = $this->renderer()->render(self::MODEL, null, [
            'overrides' => ['two' => ['content' => '<p>New lead</p>']],
        ], 'fr');

        $this->assertStringContainsString('<p>New lead</p>', $html);
        $this->assertStringNotContainsString('Lead two', $html);
        $this->assertStringContainsString('Body A', $html);
        $this->assertStringContainsString('Body B', $html);
    }

    public function testOverridingASubSectionReplacesOnlyItsOwnBody(): void
    {
        $html = $this->renderer()->render(self::MODEL, null, [
            'overrides' => ['two.a' => ['content' => '<p>Rewritten A</p>']],
        ], 'fr');

        $this->assertStringContainsString('<p>Rewritten A</p>', $html);
        $this->assertStringNotContainsString('Body A', $html);
        $this->assertStringContainsString('Body B', $html);
        $this->assertStringContainsString('Lead two', $html);
    }

    // The intro ships headingless, and the screen counts a title typed there as a real edit: it has to render
    public function testTitlingAHeadinglessUnitCreatesItsHeading(): void
    {
        $html = $this->renderer()->render(self::MODEL, null, [
            'overrides' => ['intro' => ['title' => 'Foreword', 'content' => '<p>Ours</p>']],
        ], 'fr');

        $this->assertStringContainsString('<div data-legal-id="intro"><h2>Foreword</h2><p>Ours</p></div>', $html);
    }

    public function testAHeadinglessUnitLeftWithoutATitleGetsNoHeading(): void
    {
        $html = $this->renderer()->render(self::MODEL, null, [
            'overrides' => ['intro' => ['content' => '<p>Ours</p>']],
        ], 'fr');

        $this->assertStringContainsString('<div data-legal-id="intro"><p>Ours</p></div>', $html);
    }

    public function testPositionMovesAUnitWithoutTouchingTheUntaggedParagraph(): void
    {
        $html = $this->renderer()->render(self::MODEL, null, ['positions' => ['one' => 100]], 'fr');

        $this->assertGreaterThan(strpos($html, '<h2>Two</h2>'), strpos($html, '<h2>One</h2>'));
        // The "latest update" paragraph carries no identifier and stays ahead of every unit
        $this->assertLessThan(strpos($html, 'Intro of Acme'), strpos($html, 'Updated:'));
    }

    public function testAddedTopLevelSectionIsRenderedWithItsOwnHeading(): void
    {
        $html = $this->renderer()->render(self::MODEL, null, [
            'extra' => [['id' => 'x1', 'parent' => '', 'title' => 'Ours', 'content' => '<p>Our clause</p>']],
        ], 'fr');

        $this->assertStringContainsString('<h2>Ours</h2>', $html);
        $this->assertStringContainsString('<p>Our clause</p>', $html);
    }

    public function testAddedSubSectionLandsInsideItsParentSection(): void
    {
        $html = $this->renderer()->render(self::MODEL, null, [
            'extra' => [['id' => 'x2', 'parent' => 'two', 'title' => 'Sub', 'content' => '<p>Nested</p>']],
        ], 'fr');

        $sectionTwo = substr($html, (int) strpos($html, '<section data-legal-id="two"'));
        $this->assertStringContainsString('<h3>Sub</h3>', substr($sectionTwo, 0, (int) strpos($sectionTwo, '</section>')));
    }

    // A headingless <section> is invalid HTML, so an addition with no title is wrapped in a <div>
    public function testAddedSectionWithoutATitleIsADiv(): void
    {
        $html = $this->renderer()->render(self::MODEL, null, [
            'extra' => [['id' => 'x3', 'parent' => '', 'title' => '', 'content' => '<p>Just text</p>']],
        ], 'fr');

        $this->assertStringContainsString('<div data-legal-id="x3"><p>Just text</p></div>', $html);
    }

    // An added sub-section is a unit of its own: reordering its siblings must not drag it along
    public function testAddedSubSectionKeepsItsOwnPlaceWhenItsSiblingsAreReordered(): void
    {
        $html = $this->renderer()->render(self::MODEL, null, [
            'positions' => ['two.b' => 0, 'two.a' => 1],
            'extra' => [['id' => 'x4', 'parent' => 'two', 'title' => 'Sub', 'content' => '<p>Nested</p>']],
        ], 'fr');

        $this->assertLessThan(strpos($html, '>A</h3>'), strpos($html, '>B</h3>'));
        $this->assertGreaterThan(strpos($html, 'Body A'), strpos($html, '<h3>Sub</h3>'));
    }

    public function testUnitsListsEveryLevelInDocumentOrder(): void
    {
        $units = $this->renderer()->units(self::MODEL, 'fr');

        $this->assertSame(['intro', 'one', 'two', 'two.a', 'two.b'], array_column($units, 'id'));
        $this->assertSame([1, 2, 2, 3, 3], array_column($units, 'level'));
        $this->assertSame(['', 'One', 'Two', 'A', 'B'], array_column($units, 'title'));
    }

    // Hashes fingerprint the wording, so two different units never collide and a config value never leaks in
    public function testUnitsCarryDistinctHashesAndKeepTheirPlaceholders(): void
    {
        $units = array_combine(array_column($this->renderer()->units(self::MODEL, 'fr'), 'id'), $this->renderer()->units(self::MODEL, 'fr'));

        $this->assertStringContainsString('%site-name%', $units['intro']['html']);
        $this->assertNotSame($units['one']['hash'], $units['two']['hash']);
        $this->assertSame(LegalModelRenderer::hash('<div>Body one</div>'), $units['one']['hash']);
    }

    public function testHashIgnoresWhitespaceOnly(): void
    {
        $this->assertSame(LegalModelRenderer::hash("<p>a</p>\n\t<p>b</p>"), LegalModelRenderer::hash('<p>a</p> <p>b</p>'));
        $this->assertNotSame(LegalModelRenderer::hash('<p>a</p>'), LegalModelRenderer::hash('<p>b</p>'));
    }

    private function renderer(): LegalModelRenderer
    {
        $config = $this->createStub(ConfigServiceInterface::class);
        $config->method('get')->willReturnCallback(
            static fn (string $slug): ?string => 'site-name' === $slug ? 'Acme' : null
        );

        $twig = new Environment(new ArrayLoader([
            '@c975LSite/models/' . self::MODEL . '.fr.html.twig' => self::TEMPLATE,
        ]));

        return new LegalModelRenderer($twig, new LegalModelPlaceholders($config), new LegalModelCatalog());
    }
}
