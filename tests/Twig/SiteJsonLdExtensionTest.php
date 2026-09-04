<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\SiteBundle\Tests\Twig;

use c975L\SiteBundle\Service\SiteSnippetBuilder;
use c975L\SiteBundle\Twig\SiteJsonLdExtension;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

class SiteJsonLdExtensionTest extends TestCase
{
    // The route attribute and not getLocale(), which PageController switches back to the writing language for the duration of the render - the graph's "inLanguage" has to name the language its description is read in
    public function testTheGraphIsBuiltInTheLanguageTheRouteIsAnsweringIn(): void
    {
        $request = new Request();
        $request->attributes->set('_locale', 'en');
        $request->setLocale('fr');

        $builder = $this->createMock(SiteSnippetBuilder::class);
        $builder->expects($this->once())
            ->method('buildJson')
            ->with(null, 'A test site', 'en')
            ->willReturn('{}');

        $extension = new SiteJsonLdExtension($builder, new RequestStack([$request]));

        $this->assertSame('{}', $extension->jsonLd(null, 'A test site'));
    }

    // The writing language carries no "_locale" attribute of its own, and the builder falls back to it
    public function testAnUnprefixedUrlNamesNoLanguageOfItsOwn(): void
    {
        $builder = $this->createMock(SiteSnippetBuilder::class);
        $builder->expects($this->once())
            ->method('buildJson')
            ->with(null, null, null)
            ->willReturn('');

        $extension = new SiteJsonLdExtension($builder, new RequestStack([new Request()]));

        $this->assertSame('', $extension->jsonLd());
    }
}
