<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\SiteBundle\Form\Type;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\OptionsResolver\OptionsResolver;

// Not bound to any real Page property - renders entirely through its own form theme block (c975l_page_qrcode_widget in page_crud_form_theme.html.twig), which reads the entity via the form's own data. Same "mapped: false" pattern as PageHealthCheckPanelType - keeps this inside the "Data" tab instead of appended below every tab regardless of which one is active
class PageQrCodeType extends AbstractType
{
    #[\Override]
    public function getBlockPrefix(): string
    {
        return 'c975l_page_qrcode';
    }

    /**
     * The language the code is asked for, handed to the theme rather than read from the request there: a form theme
     * block is given the form and its variables and nothing else, and a language screen has to point its code at that
     * language's own url - the whole point of scanning it being to open the page on a phone.
     */
    #[\Override]
    public function buildView(FormView $view, FormInterface $form, array $options): void
    {
        $view->vars['content_locale'] = $options['content_locale'];
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver
            ->setDefaults([
                'mapped' => false,
                'required' => false,
                // Null on the screen the page is written on, which is the url with no language in it
                'content_locale' => null,
            ])
            ->setAllowedTypes('content_locale', ['null', 'string']);
    }
}
