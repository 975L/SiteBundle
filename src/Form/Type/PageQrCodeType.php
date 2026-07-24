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

// Not bound to any real Page property - renders entirely through its own form theme block (c975l_page_qrcode_widget in page_crud_form_theme.html.twig), which reads the entity via the form's own data. Same "mapped: false" pattern as PageHealthCheckPanelType - keeps this inside the "Data" tab instead of appended below every tab regardless of which one is active
class PageQrCodeType extends AbstractType
{
    public function getBlockPrefix(): string
    {
        return 'c975l_page_qrcode';
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'mapped' => false,
            'required' => false,
        ]);
    }
}
