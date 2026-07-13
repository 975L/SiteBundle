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
use c975L\UiBundle\Form\Block\SliderType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Event\PreSetDataEvent;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvents;
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
            ])
            ->add('ratio', ChoiceType::class, [
                'label'              => 'label.ratio',
                'help'               => 'label.ratio_help',
                'choices'            => SliderType::RATIO_CHOICES,
                'translation_domain' => 'ui',
            ])
        ;

        // Defaults only applied to a genuinely new/empty block - using the "data" option instead
        // would lock the field (Symfony's setDataLocked()), silently discarding the stored value
        // on edit and resetting it to the default on every save
        $builder->addEventListener(
            FormEvents::PRE_SET_DATA,
            function (PreSetDataEvent $event): void {
                $data = $event->getData();
                if (!is_array($data)) {
                    $data = [];
                }

                $data['duration'] ??= 3500;
                $data['ratio'] ??= 'free';

                $event->setData($data);
            }
        );
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class'         => null,
            'translation_domain' => 'site',
        ]);
    }
}
