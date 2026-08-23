<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\SiteBundle\Tests;

use PHPUnit\Framework\TestCase;
use Twig\Environment;
use Twig\Loader\ArrayLoader;

// A database page renders through the site's own layout, so the blocks a site adds around it apply to its pages too. The order of the list is the whole behaviour: the app's file first, the bundle's as a fallback for an app that hasn't run the scaffold
class PageLayoutInheritanceTest extends TestCase
{
    // The list as pages/page.html.twig writes it, read out of the template that ships rather than restated here
    private function extendsList(): array
    {
        $template = (string) file_get_contents(dirname(__DIR__) . '/templates/pages/page.html.twig');

        $this->assertSame(
            1,
            preg_match('/\{% extends \[(.*?)\] %\}/', $template, $matches),
            'pages/page.html.twig no longer extends a list of templates, this test can no longer read it.'
        );

        return array_map(
            static fn (string $name): string => trim($name, " '\""),
            explode(',', $matches[1])
        );
    }

    public function testAPageGoesThroughTheAppsOwnLayoutFirst(): void
    {
        $this->assertSame('layout.html.twig', $this->extendsList()[0]);
    }

    // Without it, an app that only installed the bundle has no layout.html.twig at all and every database page fails to render
    public function testTheBundlesLayoutStaysAsAFallback(): void
    {
        $this->assertSame(
            ['layout.html.twig', '@c975LSite/layout.html.twig'],
            $this->extendsList()
        );
    }

    // Twig's own resolution of that list, checked on the expression itself rather than assumed
    public function testTheAppsLayoutWinsWhenItExists(): void
    {
        $this->assertSame('app', $this->renderThroughExtendsList([
            'layout.html.twig' => 'app',
            '@c975LSite/layout.html.twig' => 'bundle',
        ]));
    }

    public function testTheBundlesLayoutIsUsedWhenTheAppHasNone(): void
    {
        $this->assertSame('bundle', $this->renderThroughExtendsList([
            '@c975LSite/layout.html.twig' => 'bundle',
        ]));
    }

    // The contract the page relies on: it overrides "container", so a layout that defines none renders it empty
    public function testTheBundlesLayoutDefinesTheContainerBlock(): void
    {
        $this->assertStringContainsString(
            '{% block container %}',
            (string) file_get_contents(dirname(__DIR__) . '/templates/layout.html.twig'),
            'The layout lost its "container" block, which pages/page.html.twig overrides to render its blocks.'
        );
    }

    // Core-bundle's layout is the single source for the whole document; this one only adds what having Pages, menus and a navbar brings, so no tag can drift between the two shells
    public function testTheBundlesLayoutExtendsCoreBundlesOwn(): void
    {
        $this->assertStringContainsString(
            "{% extends '@c975LUi/layout.html.twig' %}",
            $this->siteLayout(),
            'The layout no longer extends core-bundle\'s, so it writes a document of its own again.'
        );
    }

    // A child layout adds blocks and variables, never a document: the head tag in particular is written once, where the minimal shell a site without this bundle is served already writes it
    public function testTheBundlesLayoutWritesNoDocumentOfItsOwn(): void
    {
        // Comments stripped first, this file's own naming the tags it must not write
        $markup = (string) preg_replace('/\{#.*?#\}/s', '', $this->siteLayout());

        foreach (['<!DOCTYPE', '<html', '<head>', '<body'] as $tag) {
            $this->assertStringNotContainsString(
                $tag,
                $markup,
                sprintf('The layout writes "%s" itself, which core-bundle\'s parent already does.', $tag)
            );
        }
    }

    private function siteLayout(): string
    {
        return (string) file_get_contents(dirname(__DIR__) . '/templates/layout.html.twig');
    }

    public function testThePageOverridesTheContainerBlock(): void
    {
        $this->assertStringContainsString(
            '{% block container %}',
            (string) file_get_contents(dirname(__DIR__) . '/templates/pages/page.html.twig')
        );
    }

    /**
     * Renders the page's extends list against stand-in layouts, the real ones pulling in the whole application.
     *
     * @param array<string, string> $layouts
     */
    private function renderThroughExtendsList(array $layouts): string
    {
        $list = implode(', ', array_map(static fn (string $name): string => sprintf("'%s'", $name), $this->extendsList()));

        $templates = ['page' => sprintf('{%% extends [%s] %%}', $list)];
        foreach ($layouts as $name => $content) {
            $templates[$name] = $content;
        }

        return new Environment(new ArrayLoader($templates))->render('page');
    }
}
