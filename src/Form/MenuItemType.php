<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\SiteBundle\Form;

use c975L\ConfigBundle\Management\LinkableRouteRegistry;
use c975L\SiteBundle\Entity\MenuItem;
use c975L\SiteBundle\Entity\Page;
use c975L\SiteBundle\Repository\PageRepository;
use Doctrine\ORM\QueryBuilder;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Event\PreSetDataEvent;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Contracts\Translation\TranslatorInterface;

// Each entry of Menu::$items - check Readme for usage. Exactly one of "page"/"route" must be picked
// (see MenuItem's class-level Assert\Expression)
class MenuItemType extends AbstractType
{
    public function __construct(
        private readonly LinkableRouteRegistry $linkableRouteRegistry,
        private readonly TranslatorInterface $translator,
    ) {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $routeChoices = [];
        foreach ($this->linkableRouteRegistry->all() as $name => $route) {
            $routeChoices[$this->translator->trans($route['label'], [], $route['translation_domain'])] = $name;
        }

        $builder
            ->add('page', EntityType::class, [
                'label' => 'label.page',
                'required' => false,
                'placeholder' => 'label.choose_page',
                'class' => Page::class,
                'choice_label' => 'title',
                'query_builder' => static fn (PageRepository $repository): QueryBuilder => $repository
                    ->createQueryBuilder('p')
                    ->andWhere('p.isPublished = :published')
                    ->andWhere('p.isDeleted = :deleted')
                    ->setParameter('published', true)
                    ->setParameter('deleted', false)
                    ->orderBy('p.title', 'ASC'),
            ])
            ->add('route', ChoiceType::class, [
                'label' => 'label.route',
                'required' => false,
                'placeholder' => 'label.choose_route',
                'choices' => $routeChoices,
            ])
            ->add('position', HiddenType::class, [
                'attr' => ['class' => 'ui-sort-position'],
            ]);

        // Unmapped, only used server-side to reconcile submitted entries against existing rows by ID
        // (see MenuCrudController::createEditFormBuilder) - same trick as BlockType
        $builder->addEventListener(
            FormEvents::PRE_SET_DATA,
            function (PreSetDataEvent $event): void {
                $item = $event->getData();
                $event->getForm()->add('id', HiddenType::class, [
                    'mapped' => false,
                    'required' => false,
                    'data' => $item instanceof MenuItem ? $item->getId() : null,
                ]);
            }
        );
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => MenuItem::class,
            'label' => false,
            'translation_domain' => 'site',
        ]);
    }
}
