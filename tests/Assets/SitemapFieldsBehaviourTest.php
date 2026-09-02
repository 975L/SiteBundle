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

// assets/js/sitemap-fields.js run over the form EasyAdmin renders, rather than read line by line
// The one thing this controller must never do is the obvious way of writing it: a disabled control is not submitted, so locking the sitemap fields with "disabled" would wipe them on every save of a non-indexable page - renaming a title on the seeded "creer-un-compte" would silently drop its frequency and its priority. Nothing in the file proves the fields still leave with the form; a FormData built off it does
#[Group('browser')]
class SitemapFieldsBehaviourTest extends JsCase
{
    // A page published and referenced: nothing is locked, which is the state every assertion below is a departure from
    public function testAPageThatIsReferencedLeavesItsSitemapFieldsAlone(): void
    {
        $open = $this->form('return state();');

        $this->assertSame(['readOnly' => false, 'aria' => 'false', 'tab' => 0, 'dimmed' => false], $open['priority']);
        $this->assertSame(['readOnly' => false, 'aria' => 'false', 'tab' => 0, 'dimmed' => false], $open['frequency']);
        $this->assertFalse($open['indexable']['dimmed'], 'A referenced page has its own switch dimmed.');
    }

    // The whole point of the row: a page nobody may reference has no frequency and no priority to declare
    public function testUncheckingTheReferenceLocksTheFieldsItMakesMeaningless(): void
    {
        $locked = $this->form('indexable().checked = false; fire(indexable()); return state();');

        $this->assertTrue($locked['priority']['readOnly'], 'The priority stays editable on a page that is not referenced.');
        $this->assertSame('true', $locked['priority']['aria'], 'A locked field says nothing about being locked, so a screen reader offers it as editable.');
        $this->assertSame(-1, $locked['priority']['tab'], 'A locked field is still reached by the keyboard.');
        $this->assertTrue($locked['priority']['dimmed'], 'The lock was drawn on the input alone, leaving its label and its help text reading as editable.');
        $this->assertSame(-1, $locked['frequency']['tab'], 'The frequency select is still reached by the keyboard, and "readonly" does nothing at all to a select.');
        $this->assertTrue($locked['frequency']['dimmed'], 'The frequency row is not dimmed, so clicking its own label opens a locked select.');
    }

    // What a "disabled" would cost, and the reason the whole row is written the way it is
    public function testALockedFieldStillLeavesWithTheForm(): void
    {
        $sent = $this->form(
            'indexable().checked = false;
             fire(indexable());

             return { disabled: [priority().disabled, frequency().disabled], sent: [...new FormData(form()).keys()] };'
        );

        $this->assertSame([false, false], $sent['disabled'], 'A locked field was disabled, and a disabled control is not submitted.');
        $this->assertContains('Page[priority]', $sent['sent'], 'The priority no longer leaves with the form, so every save of a non-referenced page wipes it.');
        $this->assertContains('Page[changeFrequency]', $sent['sent'], 'The frequency no longer leaves with the form, so every save of a non-referenced page wipes it.');
    }

    // Page::unreferenceWhenUnpublished() applies whatever the entry point, and the form has to say so before the save rather than after it
    public function testUnpublishingUnreferencesThePageInFrontOfTheUser(): void
    {
        $unpublished = $this->form('published().checked = false; fire(published()); return { checked: indexable().checked, state: state() };');

        $this->assertFalse($unpublished['checked'], 'Unpublishing left the page reading as referenced, which the server undoes without saying so.');
        $this->assertSame('true', $unpublished['state']['indexable']['aria'], 'The reference switch can still be checked back on an unpublished page, which the server would undo at once.');
        $this->assertTrue($unpublished['state']['priority']['readOnly'], 'Unpublishing left the sitemap fields editable under a switch it has just turned off.');
    }

    // The switch sits in a row of its own, whose change never bubbles through this one
    public function testTheSwitchAboveIsListenedForOnTheFormRatherThanOnThisRow(): void
    {
        $this->assertTrue(
            (bool) $this->form('published().checked = false; fire(published()); return priority().readOnly;'),
            'A change on a row above this one never reaches the controller, so the lock only follows the switch it can see.'
        );
    }

    // A page unpublished before the rule existed still holds isIndexable = true until its next save, and the form would show it as referenced
    public function testAPageLeftReferencedByAnOlderSaveIsPutRightOnArrival(): void
    {
        $arrived = $this->form('return { checked: indexable().checked, locked: priority().readOnly };', false);

        $this->assertFalse($arrived['checked'], 'A page carrying a stale reference from before the server rule shows as referenced when it is not.');
        $this->assertTrue($arrived['locked'], 'The sitemap fields of a page that is not referenced open editable.');
    }

    // The lock has to come off again, or a page is referenced back on a form that goes on refusing its fields
    public function testReferencingThePageBackGivesItsFieldsBack(): void
    {
        $reopened = $this->form(
            'indexable().checked = false;
             fire(indexable());
             indexable().checked = true;
             fire(indexable());

             return state();'
        );

        $this->assertFalse($reopened['priority']['readOnly'], 'A page referenced back keeps its priority locked.');
        $this->assertSame(0, $reopened['frequency']['tab'], 'A page referenced back keeps its frequency out of reach of the keyboard.');
        $this->assertFalse($reopened['priority']['dimmed'], 'The dimming stayed on a row that is editable again.');
    }

    // The listener is on the form, which outlives the row: left behind, it locks the fields of whatever Turbo renders next
    public function testTheFormListenerDoesNotOutliveTheRow(): void
    {
        $this->assertFalse(
            (bool) $this->form(
                'const row = root.querySelector("[data-controller]");
                 document.createElement("div").appendChild(row);
                 await new Promise((r) => setTimeout(r, 0));
                 published().checked = false;
                 fire(published());

                 return priority().readOnly;'
            ),
            'The controller goes on locking the form after its own row was taken off it.'
        );
    }

    private function form(string $probe, bool $published = true): mixed
    {
        $preamble = 'const form = () => root.querySelector("form");
             const field = (suffix) => root.querySelector("[id$=" + suffix + "]");
             const published = () => field("_isPublished");
             const indexable = () => field("_isIndexable");
             const priority = () => field("_priority");
             const frequency = () => field("_changeFrequency");
             const fire = (el) => el.dispatchEvent(new Event("change", { bubbles: true }));
             const read = (el) => ({
                 readOnly: !!el.readOnly,
                 aria: el.getAttribute("aria-disabled"),
                 tab: el.tabIndex,
                 dimmed: (el.closest(".form-group") ?? el).classList.contains("ui-field-locked"),
             });
             const state = () => ({ indexable: read(indexable()), priority: read(priority()), frequency: read(frequency()) }); ';

        return $this->observe($this->page($published), ['sitemap-fields' => 'sitemap-fields'], $preamble . $probe);
    }

    // The edit form as PageCrudController has EasyAdmin render it: the controller on the isIndexable row, the switch it follows in a row of its own above
    private function page(bool $published): string
    {
        return sprintf(
            '<form>
                <div class="form-group"><div class="ea-switch"><input type="checkbox" id="Page_isPublished" name="Page[isPublished]"%s></div></div>
                <div class="form-group" data-controller="sitemap-fields"><div class="ea-switch"><input type="checkbox" id="Page_isIndexable" name="Page[isIndexable]" checked></div></div>
                <div class="form-group"><label for="Page_changeFrequency">Frequence</label><select id="Page_changeFrequency" name="Page[changeFrequency]"><option value="weekly" selected>weekly</option></select></div>
                <div class="form-group"><label for="Page_priority">Priorite</label><input type="text" id="Page_priority" name="Page[priority]" value="0.5"></div>
            </form>',
            $published ? ' checked' : ''
        );
    }
}
