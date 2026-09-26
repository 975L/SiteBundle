<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\SiteBundle\Tests;

use c975L\SiteBundle\Entity\Page;
use PHPUnit\Framework\TestCase;
use Twig\Environment;
use Twig\Loader\ArrayLoader;
use Twig\TwigFilter;
use Twig\TwigFunction;

// A content zone is the blocks of a Page on a screen of the app's own: the page's run when it exists, published or not, nothing for a visitor when it doesn't, and the link creating it for an editor
class ContentZoneComponentTest extends TestCase
{
    // The component as it ships, rendered against stubs of what it reads outside itself
    private function renderZone(?Page $page, bool $isEditor): string
    {
        $template = (string) file_get_contents(\dirname(__DIR__) . '/templates/components/Page/Blocks.html.twig');
        $twig = new Environment(new ArrayLoader(['zone' => $template]));
        $twig->addFunction(new TwigFunction('site_content_page', static fn (string $slug): ?Page => $page));
        $twig->addFunction(new TwigFunction('render_owned_blocks', static fn (Page $owner): string => '<div class="blocks">' . $owner->getSlug() . '</div>', ['is_safe' => ['html']]));
        $twig->addFunction(new TwigFunction('config', static fn (string $slug): string => 'site-role-editor' === $slug ? 'ROLE_EDITOR' : ''));
        $twig->addFunction(new TwigFunction('is_granted', static fn (string $role): bool => $isEditor && 'ROLE_EDITOR' === $role));
        $twig->addFunction(new TwigFunction('site_page_new_url', static fn (string $slug): string => '/management/page/new?slug=' . $slug));
        $twig->addFilter(new TwigFilter('trans', static fn (string $id, array $parameters = []): string => $id . ' ' . ($parameters['%slug%'] ?? '')));

        return trim($twig->render('zone', ['slug' => 'shortcut-preview']));
    }

    private function createPage(bool $isPublished): Page
    {
        return new Page()->setSlug('shortcut-preview')->setIsPublished($isPublished);
    }

    // A content holder stays unpublished, its own url answering 404: the zone shows its blocks all the same
    public function testAnUnpublishedPageRendersItsBlocks(): void
    {
        $this->assertSame('<div class="blocks">shortcut-preview</div>', $this->renderZone($this->createPage(false), false));
    }

    public function testAPublishedPageRendersItsBlocksToo(): void
    {
        $this->assertSame('<div class="blocks">shortcut-preview</div>', $this->renderZone($this->createPage(true), false));
    }

    // The editor sees the same run, the overlay coming from render_owned_blocks() rather than from a link of the zone's own
    public function testAnEditorIsNotOfferedToCreateAPageThatExists(): void
    {
        $this->assertStringNotContainsString('/management/page/new', $this->renderZone($this->createPage(false), true));
    }

    // Trashed: nothing for anyone, the editor included - the page still holds the slug, a new one would be saved under another the zone never reads
    public function testATrashedPageRendersNothingEvenForAnEditor(): void
    {
        $page = $this->createPage(false)->setIsDeleted(true);

        $this->assertSame('', $this->renderZone($page, false));
        $this->assertSame('', $this->renderZone($page, true));
    }

    public function testAMissingPageRendersNothingForAVisitor(): void
    {
        $this->assertSame('', $this->renderZone(null, false));
    }

    // Otherwise nothing tells an editor the zone is there to be written
    public function testAMissingPageOffersAnEditorTheScreenCreatingIt(): void
    {
        $rendered = $this->renderZone(null, true);

        $this->assertStringContainsString('href="/management/page/new?slug=shortcut-preview"', $rendered);
        $this->assertStringContainsString('label.content_zone_create shortcut-preview', $rendered);
    }
}
