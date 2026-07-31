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
use c975L\SiteBundle\Service\LegalModelCustomizer;
use c975L\SiteBundle\Service\LegalModelPlaceholders;
use c975L\SiteBundle\Service\LegalModelRenderer;
use PHPUnit\Framework\TestCase;
use Twig\Environment;
use Twig\Loader\ArrayLoader;

class LegalModelCustomizerTest extends TestCase
{
    private const MODEL = 'france/cookies';

    // The class attributes matter here: they are exactly what Trix strips on its way back
    private const TEMPLATE = <<<'HTML'
        <div class="legal">
        	<section data-legal-id="one"><h2>One</h2><div class="text text-justify">Body one</div></section>
        	<section data-legal-id="two"><h2>Two</h2><div>Lead two</div><h3 data-legal-id="two.a">A</h3><div>Body A</div><h3 data-legal-id="two.b">B</h3><div>Body B</div></section>
        </div>
        HTML;

    // The revised bundle text, as a later release would ship it
    private const TEMPLATE_REVISED = <<<'HTML'
        <div class="legal">
        	<section data-legal-id="one"><h2>One</h2><div class="text text-justify">Body one, reworded</div></section>
        	<section data-legal-id="two"><h2>Two</h2><div>Lead two</div><h3 data-legal-id="two.a">A</h3><div>Body A</div><h3 data-legal-id="two.b">B</h3><div>Body B</div></section>
        </div>
        HTML;

    // The screen is edited in place, so every field arrives carrying the text that is currently rendered
    public function testFormRowsArePrefilledWithTheBundleText(): void
    {
        $customizer = $this->customizer(self::TEMPLATE);
        $rows = $customizer->toFormData($customizer->units(self::MODEL, 'fr'), []);

        $this->assertSame('One', $rows['units'][0]['title']);
        $this->assertSame('<div class="text text-justify">Body one</div>', $rows['units'][0]['content']);
        $this->assertFalse($rows['units'][0]['hidden']);
    }

    public function testFormRowsShowTheClientsOwnVersionWhereThereIsOne(): void
    {
        $customizer = $this->customizer(self::TEMPLATE);
        $rows = $customizer->toFormData($customizer->units(self::MODEL, 'fr'), [
            'overrides' => ['one' => ['title' => 'Renamed', 'content' => '<p>Ours</p>']],
        ]);

        $this->assertSame('Renamed', $rows['units'][0]['title']);
        $this->assertSame('<p>Ours</p>', $rows['units'][0]['content']);
    }

    // Opening the screen and saving it untouched must store nothing: the block stays on the updatable path
    public function testAnUntouchedScreenProducesNoDelta(): void
    {
        $customizer = $this->customizer(self::TEMPLATE);
        $units = $customizer->units(self::MODEL, 'fr');

        $this->assertSame([], $customizer->toCustomization($customizer->toFormData($units, []), $units, 'fr'));
    }

    // The one that decides whether any of this works: Trix hands back its own serialization, class attributes
    // dropped. Taking that for a rewrite would freeze every section of every model on its first save.
    public function testTrixStrippingTheTemplateClassesIsNotAnEdit(): void
    {
        $customizer = $this->customizer(self::TEMPLATE);
        $units = $customizer->units(self::MODEL, 'fr');

        $delta = $customizer->toCustomization([
            'units' => [
                ['id' => 'one', 'position' => 0, 'title' => 'One', 'content' => '<div>Body one</div>'],
            ],
        ], $units, 'fr');

        $this->assertSame([], $delta);
    }

    public function testRewritingTheTextIsStoredAndStampedWithWhatItReplaces(): void
    {
        $customizer = $this->customizer(self::TEMPLATE);
        $units = $customizer->units(self::MODEL, 'fr');

        $delta = $customizer->toCustomization([
            'units' => [['id' => 'one', 'position' => 0, 'title' => 'One', 'content' => '<div>Ours instead</div>']],
        ], $units, 'fr');

        $this->assertSame(['one'], array_keys($delta['overrides']));
        $this->assertSame('<div>Ours instead</div>', $delta['overrides']['one']['content']);
        $this->assertSame(LegalModelRenderer::hash('<div class="text text-justify">Body one</div>'), $delta['overrides']['one']['baseHash']);
        $this->assertSame('fr', $delta['overrides']['one']['baseLocale']);
    }

    public function testRetitlingAloneIsStoredToo(): void
    {
        $customizer = $this->customizer(self::TEMPLATE);
        $units = $customizer->units(self::MODEL, 'fr');

        $delta = $customizer->toCustomization([
            'units' => [['id' => 'one', 'position' => 0, 'title' => 'Renamed', 'content' => '<div>Body one</div>']],
        ], $units, 'fr');

        $this->assertSame('Renamed', $delta['overrides']['one']['title']);
    }

    public function testHidingAUnitIsStoredWithoutMakingItAnOverride(): void
    {
        $customizer = $this->customizer(self::TEMPLATE);
        $units = $customizer->units(self::MODEL, 'fr');

        $delta = $customizer->toCustomization([
            'units' => [['id' => 'one', 'hidden' => true, 'position' => 0, 'title' => 'One', 'content' => '<div>Body one</div>']],
        ], $units, 'fr');

        $this->assertSame(['one'], $delta['hidden']);
        $this->assertArrayNotHasKey('overrides', $delta);
    }

    // Positions come from the drag handle, which numbers every row of a scope - untouched, that numbering is
    // the natural order and must not be stored
    public function testDraggingNothingStoresNoPosition(): void
    {
        $customizer = $this->customizer(self::TEMPLATE);
        $units = $customizer->units(self::MODEL, 'fr');

        $delta = $customizer->toCustomization([
            'units' => [
                ['id' => 'one', 'position' => 0, 'title' => 'One', 'content' => '<div>Body one</div>'],
                ['id' => 'two', 'position' => 1, 'title' => 'Two', 'content' => '<div>Lead two</div>'],
                ['id' => 'two.a', 'position' => 0, 'title' => 'A', 'content' => '<div>Body A</div>'],
                ['id' => 'two.b', 'position' => 1, 'title' => 'B', 'content' => '<div>Body B</div>'],
            ],
        ], $units, 'fr');

        $this->assertSame([], $delta);
    }

    public function testReorderingOneScopePinsThatScopeAndLeavesTheOtherAlone(): void
    {
        $customizer = $this->customizer(self::TEMPLATE);
        $units = $customizer->units(self::MODEL, 'fr');

        $delta = $customizer->toCustomization([
            'units' => [
                ['id' => 'one', 'position' => 0, 'title' => 'One', 'content' => '<div>Body one</div>'],
                ['id' => 'two', 'position' => 1, 'title' => 'Two', 'content' => '<div>Lead two</div>'],
                // The two sub-sections swapped
                ['id' => 'two.a', 'position' => 1, 'title' => 'A', 'content' => '<div>Body A</div>'],
                ['id' => 'two.b', 'position' => 0, 'title' => 'B', 'content' => '<div>Body B</div>'],
            ],
        ], $units, 'fr');

        $this->assertSame(['two.b' => 0, 'two.a' => 1], $delta['positions']);
    }

    // What the screen re-reads to lay its cards out: a moved sub-section stays under the section it documents
    public function testOrderedKeepsSubSectionsUnderTheirOwnSection(): void
    {
        $customizer = $this->customizer(self::TEMPLATE);
        $units = $customizer->units(self::MODEL, 'fr');

        $ordered = $customizer->ordered($units, ['two.b' => 0, 'two.a' => 1]);

        $this->assertSame(['one', 'two', 'two.b', 'two.a'], array_column($ordered, 'id'));
    }

    // Saving the screen untouched must leave the block exactly as it was found - not carrying an empty
    // "customization" key it did not have before
    public function testApplyingAnEmptyDeltaLeavesTheBlockDataUntouched(): void
    {
        $data = ['model' => self::MODEL, 'latestUpdate' => '2026-07-27'];

        $this->assertSame($data, $this->customizer(self::TEMPLATE)->apply($data, []));
    }

    public function testApplyingAnEmptyDeltaDropsAPreviouslyStoredOne(): void
    {
        $customizer = $this->customizer(self::TEMPLATE);
        $data = ['model' => self::MODEL, 'customization' => ['hidden' => ['one']]];

        $this->assertSame(['model' => self::MODEL], $customizer->apply($data, []));
    }

    public function testApplyingADeltaMergesItInWithoutLosingTheRestOfTheData(): void
    {
        $customizer = $this->customizer(self::TEMPLATE);
        $data = ['model' => self::MODEL, 'latestUpdate' => '2026-07-27'];

        $this->assertSame(
            ['model' => self::MODEL, 'latestUpdate' => '2026-07-27', 'customization' => ['hidden' => ['one']]],
            $customizer->apply($data, ['hidden' => ['one']]),
        );
    }

    public function testAddedSectionsGetAnIdentifierWhenTheyHaveNoneYet(): void
    {
        $customizer = $this->customizer(self::TEMPLATE);
        $units = $customizer->units(self::MODEL, 'fr');

        $delta = $customizer->toCustomization([
            'units' => [],
            'extra' => [
                ['id' => '', 'parent' => 'one', 'title' => 'Ours', 'content' => '<p>Added</p>'],
                ['id' => '', 'parent' => '', 'title' => '', 'content' => ''],
            ],
        ], $units, 'fr');

        $this->assertCount(1, $delta['extra'], 'an empty row is not a section');
        $this->assertNotSame('', $delta['extra'][0]['id']);
        $this->assertSame('one', $delta['extra'][0]['parent']);
    }

    // A row pointing at a unit the model no longer has is dropped rather than stored against a dead identifier
    public function testRowsForVanishedUnitsAreDropped(): void
    {
        $customizer = $this->customizer(self::TEMPLATE);
        $units = $customizer->units(self::MODEL, 'fr');

        $delta = $customizer->toCustomization([
            'units' => [['id' => 'gone', 'hidden' => true, 'position' => 0, 'title' => 'x', 'content' => '<p>x</p>']],
        ], $units, 'fr');

        $this->assertSame([], $delta);
    }

    // The point of C: the bundle reworded a passage this client had already replaced
    public function testDriftIsReportedWhenTheBundleTextMovedUnderAnOverride(): void
    {
        $before = $this->customizer(self::TEMPLATE);
        $delta = $before->toCustomization([
            'units' => [['id' => 'one', 'position' => 0, 'title' => 'One', 'content' => '<p>Ours</p>']],
        ], $before->units(self::MODEL, 'fr'), 'fr');

        $drifted = $this->customizer(self::TEMPLATE_REVISED)->drifted(self::MODEL, $delta);

        $this->assertSame(['one'], array_keys($drifted));
        $this->assertStringContainsString('reworded', $drifted['one']['html']);
    }

    public function testNoDriftWhenTheBundleTextIsUnchanged(): void
    {
        $customizer = $this->customizer(self::TEMPLATE);
        $delta = $customizer->toCustomization([
            'units' => [['id' => 'one', 'position' => 0, 'title' => 'One', 'content' => '<p>Ours</p>']],
        ], $customizer->units(self::MODEL, 'fr'), 'fr');

        $this->assertSame([], $this->customizer(self::TEMPLATE)->drifted(self::MODEL, $delta));
    }

    // Hiding or moving a section is not an override: there is no bundle text to drift away from
    public function testHiddenAndMovedUnitsNeverDrift(): void
    {
        $customization = ['hidden' => ['one'], 'positions' => ['two' => 5]];

        $this->assertSame([], $this->customizer(self::TEMPLATE_REVISED)->drifted(self::MODEL, $customization));
    }

    private function customizer(string $template): LegalModelCustomizer
    {
        $config = $this->createStub(ConfigServiceInterface::class);
        $config->method('get')->willReturn(null);

        $twig = new Environment(new ArrayLoader([
            '@c975LSite/models/' . self::MODEL . '.fr.html.twig' => $template,
        ]));

        return new LegalModelCustomizer(
            new LegalModelRenderer($twig, new LegalModelPlaceholders($config), new LegalModelCatalog())
        );
    }
}
