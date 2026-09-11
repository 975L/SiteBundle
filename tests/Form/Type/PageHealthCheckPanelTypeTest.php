<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\SiteBundle\Tests\Form\Type;

use c975L\SiteBundle\Form\Type\PageHealthCheckPanelType;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\OptionsResolver\Exception\InvalidOptionsException;
use Symfony\Component\OptionsResolver\OptionsResolver;

class PageHealthCheckPanelTypeTest extends TestCase
{
    public function testGetBlockPrefixMatchesTheFormThemeBlockName(): void
    {
        $this->assertSame('c975l_page_health_check_panel', new PageHealthCheckPanelType()->getBlockPrefix());
    }

    public function testConfigureOptionsMarksTheFieldUnmappedAndNotRequired(): void
    {
        $resolver = new OptionsResolver();
        new PageHealthCheckPanelType()->configureOptions($resolver);

        $options = $resolver->resolve();

        $this->assertFalse($options['mapped']);
        $this->assertFalse($options['required']);
        // Null on the screen the page is written on, which is the url with no language in it
        $this->assertNull($options['content_locale']);
    }

    public function testConfigureOptionsRefusesAContentLocaleThatIsNotALanguage(): void
    {
        $resolver = new OptionsResolver();
        new PageHealthCheckPanelType()->configureOptions($resolver);

        $this->expectException(InvalidOptionsException::class);

        $resolver->resolve(['content_locale' => 5]);
    }

    public function testBuildViewHandsTheLanguageToTheFormTheme(): void
    {
        // The theme is given the form and nothing else, so the language the screen was opened on travels as a view variable (see page_crud_form_theme.html.twig)
        $view = new FormView();

        new PageHealthCheckPanelType()->buildView($view, $this->createStub(FormInterface::class), ['content_locale' => 'en']);

        $this->assertSame('en', $view->vars['content_locale']);
    }
}
