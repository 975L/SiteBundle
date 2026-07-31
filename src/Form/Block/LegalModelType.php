<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\SiteBundle\Form\Block;

use c975L\SiteBundle\Service\LegalModelCatalog;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class LegalModelType extends AbstractType
{
    public function __construct(
        private readonly LegalModelCatalog $catalog,
    ) {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('model', ChoiceType::class, [
                'label' => 'label.model',
                'choices' => $this->catalog->choices(),
                // Section by section customization lives on its own screen (see LegalModelController): the
                // rows depend on which model is picked here, which the block's ajax sub-form never knows
                'help' => 'label.legal_customize_help',
            ])
            ->add('latestUpdate', DateType::class, [
                'label' => 'label.latest_update',
                'widget' => 'single_text',
                'input' => 'string',
                'input_format' => 'Y-m-d',
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
