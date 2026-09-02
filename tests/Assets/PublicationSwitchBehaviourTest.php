<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\SiteBundle\Tests\Assets;

use PHPUnit\Framework\Attributes\Group;

// assets/js/publication-switch.js run over the pair of switches an index row shows side by side
// It reaches out of the cell it is mounted on into its sibling, found through the "data-column" EasyAdmin writes on each of them - two things a file says but cannot demonstrate. What a stale switch costs is the interesting part: left showing "checked" over a row the database holds as false, the next click sends "false" for something already false, unchecking what it displays rather than what it holds
#[Group('browser')]
class PublicationSwitchBehaviourTest extends JsCase
{
    // A published row is left exactly as EasyAdmin rendered it
    public function testAPublishedRowIsLeftAsItWasRendered(): void
    {
        $open = $this->row('return state();');

        $this->assertSame(['checked' => true, 'disabled' => false, 'dimmed' => false], $open, 'A published row had its reference switch touched.');
    }

    // The rule is server-side whatever the entry point, so a row the list draws as referenced when it no longer is has to be put right on arrival
    public function testARowLeftReferencedByAnOlderSaveIsPutRightOnArrival(): void
    {
        $arrived = $this->row('return state();', false);

        $this->assertFalse($arrived['checked'], 'An unpublished row goes on showing as referenced, and the next click on it sends false for something already false.');
        $this->assertTrue($arrived['disabled'], 'The reference switch of an unpublished row can be clicked, and the server undoes it without saying so.');
        $this->assertTrue($arrived['dimmed'], 'The locked switch does not look locked, EasyAdmin dimming its own the other way.');
    }

    public function testUnpublishingFromTheListUnreferencesTheRowAtOnce(): void
    {
        $this->assertSame(
            ['checked' => false, 'disabled' => true, 'dimmed' => true],
            $this->row('published().checked = false; fire(published()); return state();'),
            'Unpublishing a row from the list leaves it showing as referenced.'
        );
    }

    // Publishing back gives the switch back without checking it: what the page is referenced as is the user's call, not a state to guess
    public function testPublishingBackGivesTheSwitchBackWithoutAnsweringForIt(): void
    {
        $this->assertSame(
            ['checked' => false, 'disabled' => false, 'dimmed' => false],
            $this->row('published().checked = true; fire(published()); return state();', false),
            'Publishing a row back either left its reference switch locked, or referenced it without being asked.'
        );
    }

    // A list not showing the isIndexable column at all: there is nothing to mirror, and nothing must throw over it
    public function testARowWithoutTheOtherColumnIsSimplyLeftAlone(): void
    {
        $this->assertTrue(
            (bool) $this->row(
                'published().checked = false;
                 fire(published());

                 return published().checked === false;',
                true,
                '<table><tbody><tr><td data-column="isPublished" data-controller="publication-switch"><div class="ea-switch"><input type="checkbox" checked></div></td></tr></tbody></table>'
            ),
            'A list that does not show the reference column takes the row down with it.'
        );
    }

    // Turbo caches the list as it stands, and a listener left on a detached row mirrors into a cell nobody is looking at
    public function testTheListenerDoesNotOutliveTheCell(): void
    {
        $this->assertTrue(
            (bool) $this->row(
                'const toggle = published();
                 const other = indexable();
                 document.createElement("div").appendChild(root.querySelector("[data-controller]"));
                 await new Promise((r) => setTimeout(r, 0));
                 toggle.checked = false;
                 fire(toggle);

                 return other.checked;'
            ),
            'The controller goes on mirroring into a row it was taken off.'
        );
    }

    private function row(string $probe, bool $published = true, ?string $html = null): mixed
    {
        $preamble = 'const published = () => root.querySelector("[data-column=isPublished] input");
             const indexable = () => root.querySelector("[data-column=isIndexable] input");
             const fire = (el) => el.dispatchEvent(new Event("change", { bubbles: true }));
             const state = () => ({
                 checked: indexable().checked,
                 disabled: indexable().disabled,
                 dimmed: indexable().closest(".ea-switch").classList.contains("ea-switch-disabled"),
             }); ';

        return $this->observe($html ?? $this->page($published), ['publication-switch' => 'publication-switch'], $preamble . $probe);
    }

    // The index row as PageCrudController has EasyAdmin render it, the controller mounted on the isPublished cell through setHtmlAttribute()
    private function page(bool $published): string
    {
        return sprintf(
            '<table><tbody><tr>
                <td data-column="isPublished" data-controller="publication-switch"><div class="ea-switch"><input type="checkbox"%s></div></td>
                <td data-column="isIndexable"><div class="ea-switch"><input type="checkbox" checked></div></td>
            </tr></tbody></table>',
            $published ? ' checked' : ''
        );
    }
}
