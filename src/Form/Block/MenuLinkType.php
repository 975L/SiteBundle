<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\SiteBundle\Form\Block;

use c975L\SiteBundle\Service\LinkTargetChoices;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

// A single flat, alphabetically-sorted "target" select (pages and routes mixed) - decoded at render time by MenuExtension::getMenuLinkUrl()/ getMenuLinkLabel(), using the "page:ID" / "route:NAME" convention
class MenuLinkType extends AbstractType
{
    // Who sees the link, read by MenuLink.html.twig as a marker class the stylesheet hides from the other half (see _menu.scss) - "all" first, so a link saved before the option existed lands on it
    public const array VISIBILITIES = ['all', 'guests', 'members'];

    public function __construct(private readonly LinkTargetChoices $linkTargetChoices)
    {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $targetChoices = $this->linkTargetChoices->linkTargets();

        $builder
            ->add('target', ChoiceType::class, [
                'label' => 'label.menu_item_target',
                'required' => true,
                'placeholder' => 'label.choose_target',
                'choices' => $targetChoices,
                'choice_translation_domain' => false,
            ])
            // Overrides the auto-derived label (page title, or the anchored block's own title/the live-computed copyright notice - see MenuExtension::getMenuLinkLabel()) - needed for an anchor target, whose full section title is rarely a good fit for a compact navbar item
            ->add('label', TextType::class, [
                'label' => 'label.menu_link_label',
                'required' => false,
                'help' => 'help.menu_link_label',
            ])
            // Renders as a filled "primary" button (var(--primary), see _menu.scss's .menu-item--primary) instead of a plain text link - meant for a single stand-out item (e.g. "Contact") among a Menu's otherwise plain links
            ->add('primary', CheckboxType::class, [
                'label' => 'label.menu_link_primary',
                'required' => false,
                'help' => 'help.menu_link_primary',
            ])
            // Bolds the label alone (see _menu.scss's .menu-item--strong) - the lighter emphasis, for an item that has to stand out without taking a button's weight, and combinable with "primary" for a bolder button
            ->add('strong', CheckboxType::class, [
                'label' => 'label.menu_link_strong',
                'required' => false,
                'help' => 'help.menu_link_strong',
            ])
            // A "Sign in" link for guests only, an account link for members only - hidden by a class on <body>, so the menu's cached html stays one and the same for everyone
            ->add('visibility', ChoiceType::class, [
                'label' => 'label.menu_link_visibility',
                'required' => true,
                'choices' => array_combine(
                    array_map(static fn (string $visibility): string => 'label.menu_link_visibility_' . $visibility, self::VISIBILITIES),
                    self::VISIBILITIES
                ),
                'empty_data' => 'all',
                'help' => 'help.menu_link_visibility',
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
