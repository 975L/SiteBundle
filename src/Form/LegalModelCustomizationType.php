<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\SiteBundle\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

// The whole "customize this legal model" screen: one fixed row per unit the bundle ships, plus as many
// client-authored sections as they want to add
class LegalModelCustomizationType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            // Fixed-length: the rows mirror the model's own units, which only a bundle update can change
            ->add('units', CollectionType::class, [
                'label' => false,
                'entry_type' => LegalModelUnitType::class,
                'allow_add' => false,
                'allow_delete' => false,
            ])
            ->add('extra', CollectionType::class, [
                'label' => 'label.legal_extra_sections',
                'entry_type' => LegalModelExtraSectionType::class,
                'entry_options' => ['sections' => $options['sections']],
                'allow_add' => true,
                'allow_delete' => true,
                'by_reference' => false,
                'prototype' => true,
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver
            ->setDefaults([
                'data_class' => null,
                'translation_domain' => 'site',
                'sections' => [],
            ])
            ->setAllowedTypes('sections', 'array')
        ;
    }
}
