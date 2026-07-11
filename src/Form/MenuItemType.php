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
use c975L\SiteBundle\Repository\PageRepository;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Event\PreSetDataEvent;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Contracts\Translation\TranslatorInterface;

// Each entry of Menu::$items - check Readme for usage. A single flat, alphabetically-sorted, filterable
// "target" select (pages and routes mixed, EasyAdmin's TomSelect widget via data-ea-widget) is shown to
// avoid the "two non-exclusive selects" trap - it's decoded server-side into the entity's page/route
// (exactly one must be picked, see MenuItem's class-level Assert\Expression)
class MenuItemType extends AbstractType
{
    public function __construct(
        private readonly LinkableRouteRegistry $linkableRouteRegistry,
        private readonly PageRepository $pageRepository,
        private readonly TranslatorInterface $translator,
    ) {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $targetChoices = [];
        $pages = $this->pageRepository->createQueryBuilder('p')
            ->andWhere('p.isPublished = :published')
            ->andWhere('p.isDeleted = :deleted')
            ->setParameter('published', true)
            ->setParameter('deleted', false)
            ->getQuery()
            ->getResult();
        foreach ($pages as $page) {
            $targetChoices[$page->getTitle()] = 'page:' . $page->getId();
        }

        foreach ($this->linkableRouteRegistry->all() as $name => $route) {
            $targetChoices[$this->translator->trans($route['label'], [], $route['translation_domain'])] = 'route:' . $name;
        }

        ksort($targetChoices, SORT_NATURAL | SORT_FLAG_CASE);

        $builder
            ->add('target', ChoiceType::class, [
                'label' => 'label.menu_item_target',
                'mapped' => false,
                'required' => true,
                'placeholder' => 'label.choose_target',
                'choices' => $targetChoices,
                'choice_translation_domain' => false,
                'attr' => ['data-ea-widget' => 'ea-autocomplete'],
            ])
            ->add('position', HiddenType::class, [
                'attr' => ['class' => 'ui-sort-position'],
            ]);

        // Unmapped, only used server-side to reconcile submitted entries against existing rows by ID
        // (see MenuCrudController::createEditFormBuilder) - same trick as BlockType. Also seeds "target"
        // from the entity's current page/route since that field itself is unmapped
        $builder->addEventListener(
            FormEvents::PRE_SET_DATA,
            function (PreSetDataEvent $event): void {
                $item = $event->getData();
                $event->getForm()->add('id', HiddenType::class, [
                    'mapped' => false,
                    'required' => false,
                    'data' => $item instanceof MenuItem ? $item->getId() : null,
                ]);

                if ($item instanceof MenuItem) {
                    $target = $item->getPage() !== null
                        ? 'page:' . $item->getPage()->getId()
                        : ($item->getRoute() !== null ? 'route:' . $item->getRoute() : null);
                    $event->getForm()->get('target')->setData($target);
                }
            }
        );

        // Decodes the unmapped "target" choice back into page/route on submit
        $builder->addEventListener(
            FormEvents::SUBMIT,
            function (FormEvent $event): void {
                $item = $event->getData();
                if (!$item instanceof MenuItem) {
                    return;
                }

                [$type, $value] = array_pad(explode(':', $event->getForm()->get('target')->getData() ?? '', 2), 2, null);

                $item->setPage('page' === $type ? $this->pageRepository->find($value) : null);
                $item->setRoute('route' === $type ? $value : null);
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
