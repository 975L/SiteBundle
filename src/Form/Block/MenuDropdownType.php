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
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\NotBlank;

// The "menu_dropdown" block: the title the navbar shows, its links being the block's slots, and who sees the whole of it - the same choice as a link's (see MenuLinkType::VISIBILITIES), so an account menu is hidden from guests in one go
class MenuDropdownType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('label', TextType::class, [
                'label' => 'label.menu_dropdown_label',
                'required' => true,
                'constraints' => [new NotBlank()],
                'help' => 'help.menu_dropdown_label',
            ])
            ->add('visibility', ChoiceType::class, [
                'label' => 'label.menu_link_visibility',
                'required' => true,
                'choices' => array_combine(
                    array_map(static fn (string $visibility): string => 'label.menu_link_visibility_' . $visibility, MenuLinkType::VISIBILITIES),
                    MenuLinkType::VISIBILITIES
                ),
                'empty_data' => 'all',
                'help' => 'help.menu_dropdown_visibility',
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => null,
            'translation_domain' => 'site',
        ]);
    }
}
