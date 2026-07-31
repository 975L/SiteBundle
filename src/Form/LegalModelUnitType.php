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
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

// One row of the customization screen: what the client may do to a single unit of a legal model. The title
// and the text arrive pre-filled with the bundle's own wording, so they are edited in place rather than
// overridden blind - a row left as it came produces no delta at all and keeps arriving from the bundle.
class LegalModelUnitType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            // Carried in the form rather than used as the field name: identifiers are dotted ("regulations.dpo")
            ->add('id', HiddenType::class)
            ->add('hidden', CheckboxType::class, [
                'label' => 'label.legal_unit_hidden',
                'required' => false,
            ])
            // Written by the drag handle, never typed (see ea-sortable.js, reused from the block collections)
            ->add('position', HiddenType::class)
            // Both come pre-filled with the text as it currently reads; leaving them alone stores nothing
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
        $resolver->setDefaults([
            'data_class' => null,
            'translation_domain' => 'site',
        ]);
    }
}
