<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\SiteBundle\Form;

use c975L\UiBundle\Form\TrixEditorType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

// A section the client adds to a legal model: their own wording, at the level they choose, appended at the
// end of it. Never overwritten by a bundle update, since the bundle knows nothing about it
class LegalModelExtraSectionType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('id', HiddenType::class)
            ->add('parent', ChoiceType::class, [
                'label' => 'label.legal_extra_parent',
                'choices' => ['label.legal_extra_parent_top' => ''] + $options['sections'],
                'required' => false,
            ])
            ->add('title', TextType::class, [
                'label' => 'label.legal_unit_title',
                'required' => false,
            ])
            ->add('content', TrixEditorType::class, [
                'label' => 'label.legal_unit_content',
                'required' => false,
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
            // label => section id, the top-level units an addition can be nested under
            ->setAllowedTypes('sections', 'array')
        ;
    }
}
