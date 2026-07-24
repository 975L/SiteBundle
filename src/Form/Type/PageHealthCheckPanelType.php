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
use Symfony\Component\OptionsResolver\OptionsResolver;

// Not bound to any real Page property - renders entirely through its own form theme block (c975l_page_health_check_panel_widget in page_health_check_panel_form_theme.html.twig), which computes everything itself from the entity via the page_health_check() Twig function. Same "mapped: false" pattern EasyAdmin's own FormField::addTab()/addPanel() use for their own non-property fields
class PageHealthCheckPanelType extends AbstractType
{
    public function getBlockPrefix(): string
    {
        return 'c975l_page_health_check_panel';
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'mapped' => false,
            'required' => false,
        ]);
    }
}
