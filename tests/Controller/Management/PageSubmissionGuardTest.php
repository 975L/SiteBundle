<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\SiteBundle\Tests\Controller\Management;

use c975L\SiteBundle\Controller\Management\PageCrudController;
use PHPUnit\Framework\TestCase;

// A whole page is one form - every block, every nested slot, every media in a single POST - so it is the form that reaches PHP's max_input_vars first. Past it PHP drops the rest of the body silently, and the blocks that fell past the cut arrive as an absent key, which the normalization below reads as "the editor removed them" and deletes for good. Nothing can recover what PHP never parsed, so the submission has to be refused whole. Read off the source: reaching the listener means building EasyAdmin's own form builder, and what matters here is the order of three statements, not the framework around them.
class PageSubmissionGuardTest extends TestCase
{
    public function testTheTruncationGuardComesBeforeAnythingIsRemoved(): void
    {
        $listener = $this->preSubmitListener();

        $guard = strpos($listener, 'SubmissionIntegrity::isTruncated');
        $prune = strpos($listener, 'CollectionReconciler::pruneRemoved');
        $normalize = strpos($listener, "\$data['blocks'] = []");

        $this->assertNotFalse($guard, 'PageCrudController no longer checks the submission against max_input_vars: a truncated body deletes every block that fell past the cut.');
        $this->assertNotFalse($prune);
        $this->assertNotFalse($normalize);

        $this->assertLessThan($prune, $guard, 'The truncation guard runs after the blocks have already been pruned.');
        $this->assertLessThan($normalize, $guard, 'The truncation guard runs after the absent key has already been normalized to an empty collection.');
    }

    // A guard that only skipped the pruning would save the page half-written; the error is what refuses it whole
    public function testTheGuardReportsAndStops(): void
    {
        $listener = $this->preSubmitListener();
        $guard = $this->truncationGuard($listener);

        $this->assertStringContainsString('addError', $guard, 'A truncated submission is dropped silently instead of telling the editor why nothing was saved.');
        $this->assertStringContainsString('text.page_submission_truncated', $guard);
        $this->assertStringContainsString('return;', $guard, 'The guard does not stop the listener, so what follows still runs on a truncated body.');
    }

    // Every locale carries the message, an untranslated key being what the editor would otherwise read
    public function testTheMessageIsTranslatedInEveryLocale(): void
    {
        foreach (['en', 'es', 'fr'] as $locale) {
            $path = \dirname(__DIR__, 3) . '/translations/site.' . $locale . '.xlf';
            $this->assertFileExists($path);

            $xliff = (string) file_get_contents($path);
            $this->assertStringContainsString('<source>text.page_submission_truncated</source>', $xliff, sprintf('"%s" carries no translation for the truncated-submission message.', basename($path)));
            $this->assertStringContainsString('%limit%', substr($xliff, (int) strpos($xliff, 'text.page_submission_truncated'), 600), sprintf('"%s" drops the "%%limit%%" placeholder, so the message never says which limit was reached.', basename($path)));
        }
    }

    // The guard's own block, cut at the statement that follows it, rather than everything the listener still has to say: read to the end, a "return;" belonging to any later branch would answer for the guard's own
    private function truncationGuard(string $listener): string
    {
        $start = strpos($listener, 'SubmissionIntegrity::isTruncated');
        $this->assertNotFalse($start);

        $end = strpos($listener, '$event->getForm()->getData()', $start);
        $this->assertNotFalse($end, 'The guard is no longer followed by the pruning it protects, so its own block cannot be told apart.');

        return substr($listener, $start, $end - $start);
    }

    // The PRE_SUBMIT closure of createEditFormBuilder(), read off the file itself
    private function preSubmitListener(): string
    {
        $path = (string) (new \ReflectionClass(PageCrudController::class))->getFileName();
        $source = (string) file_get_contents($path);

        $start = strpos($source, 'public function createEditFormBuilder');
        $this->assertNotFalse($start, 'PageCrudController no longer overrides createEditFormBuilder, which is where the guard lives.');

        $end = strpos($source, 'public function configureFields', $start);
        $this->assertNotFalse($end);

        return substr($source, $start, $end - $start);
    }
}
