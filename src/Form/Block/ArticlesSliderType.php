<?php
/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\SiteBundle\Form\Block;

use c975L\SiteBundle\Repository\PageRepository;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ArticlesSliderType extends AbstractType
{
    public function __construct(
        private readonly PageRepository $pageRepository
    ) {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $choices = [];
        foreach ($this->pageRepository->findAllOrdered() as $page) {
            $choices[$page->getTitle()] = $page->getId();
        }

        $builder
            ->add('pageId', ChoiceType::class, [
                'label'   => 'label.page',
                'choices' => $choices,
            ])
            ->add('duration', IntegerType::class, [
                'label' => 'label.duration',
                'data'  => 3500,
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
