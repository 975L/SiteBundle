<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\SiteBundle\Tests\Entity;

use c975L\SiteBundle\Entity\Page;
use Doctrine\ORM\Mapping\PreFlush;
use PHPUnit\Framework\TestCase;
use Symfony\Component\PropertyAccess\PropertyAccess;

// Page::$options is one JSON payload read through named accessors holding each option's default
class PageTest extends TestCase
{
    public function testOptionsAreEmptyUntilOneIsSet(): void
    {
        $page = new Page();

        $this->assertSame([], $page->getOptions());
        $this->assertNull($page->getOption('titleDisplayed'));
        $this->assertSame('fallback', $page->getOption('unknown', 'fallback'));
    }

    public function testSetOptionKeepsTheOnesAlreadyThere(): void
    {
        $page = (new Page())->setOptions(['kept' => 'yes']);

        $page->setOption('added', 'too');

        $this->assertSame(['kept' => 'yes', 'added' => 'too'], $page->getOptions());
    }

    // Every page predating the option, and every one whose editor left it alone, keeps printing its title
    public function testTitleIsDisplayedByDefault(): void
    {
        $this->assertTrue((new Page())->isTitleDisplayed());
    }

    public function testTitleDisplayedIsStoredAsAnOption(): void
    {
        $page = (new Page())->setIsTitleDisplayed(false);

        $this->assertFalse($page->isTitleDisplayed());
        $this->assertSame(['titleDisplayed' => false], $page->getOptions());
    }

    // No mapped property behind them, so this locks the pair PropertyAccessor resolves them through
    public function testTitleDisplayedIsReadableAndWritableAsAProperty(): void
    {
        $accessor = PropertyAccess::createPropertyAccessor();
        $page = new Page();

        $accessor->setValue($page, 'isTitleDisplayed', false);

        $this->assertFalse($accessor->getValue($page, 'isTitleDisplayed'));
        $this->assertSame(['titleDisplayed' => false], $page->getOptions());
    }

    // An unpublished page must never stay referenced, whatever order its setters were called in - the edit form submits isPublished before isIndexable
    public function testUnpublishedPageIsNotIndexable(): void
    {
        $page = (new Page())
            ->setIsPublished(false)
            ->setIsIndexable(true)
        ;

        $page->unreferenceWhenUnpublished();

        $this->assertFalse($page->isIndexable());
    }

    public function testPublishedPageKeepsItsIndexableValue(): void
    {
        $indexable = (new Page())->setIsPublished(true);
        $notIndexable = (new Page())->setIsPublished(true)->setIsIndexable(false);

        $indexable->unreferenceWhenUnpublished();
        $notIndexable->unreferenceWhenUnpublished();

        $this->assertTrue($indexable->isIndexable());
        $this->assertFalse($notIndexable->isIndexable());
    }

    // The rule only holds if Doctrine actually runs it, and only PreFlush runs early enough for its write to be persisted (a PreUpdate callback's own writes come after the changeset is computed)
    public function testUnreferenceWhenUnpublishedIsAPreFlushCallback(): void
    {
        $attributes = (new \ReflectionMethod(Page::class, 'unreferenceWhenUnpublished'))->getAttributes(PreFlush::class);

        $this->assertCount(1, $attributes);
    }
}
