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
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class TwigContentType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            // A Twig template path/name (e.g. "block_showcase/bundle.html.twig"), always included as-is
            // - not limited to a collection's detail Page: any Page can use this block to include an
            // arbitrary template. When it does sit on a detail Page, "collectionItem" (see
            // TwigContent.html.twig, PageController::resolveCollectionDetail()) is passed to it - see
            // SiteBundle's README ("Item detail pages", under "Collection entries") for that recipe.
            ->add('templatePath', TextType::class, [
                'label' => 'label.template_path',
                'help'  => 'label.template_path_help',
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
