<?php
/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\SiteBundle\Form\Block;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class LegalModelType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('model', ChoiceType::class, [
                'label' => 'label.model',
                'choices' => [
                    'France' => [
                        'label.cookies_policy'  => 'france/cookies',
                        'label.copyright'        => 'france/copyright',
                        'label.legal_notice'     => 'france/legal-notice',
                        'label.privacy_policy'   => 'france/privacy-policy',
                        'label.terms_of_sales'   => 'france/terms-of-sales',
                        'label.terms_of_use'     => 'france/terms-of-use',
                    ],
                ],
            ])
            ->add('latestUpdate', DateType::class, [
                'label'        => 'label.latest_update',
                'widget'       => 'single_text',
                'input'        => 'string',
                'input_format' => 'Y-m-d',
                'required'     => false,
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class'         => null,
            'translation_domain' => 'site',
        ]);
    }
}
