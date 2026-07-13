<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\SiteBundle\Form\Block;

use c975L\ConfigBundle\Management\LinkableRouteRegistry;
use c975L\SiteBundle\Repository\PageRepository;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Contracts\Translation\TranslatorInterface;

// A single flat, alphabetically-sorted, filterable "target" select (pages and routes mixed, EasyAdmin's
// TomSelect widget via data-ea-widget) - decoded at render time by MenuExtension::getMenuLinkUrl()/
// getMenuLinkLabel(), same "page:ID" / "route:NAME" convention the former MenuItemType used
class MenuLinkType extends AbstractType
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

        $builder->add('target', ChoiceType::class, [
            'label' => 'label.menu_item_target',
            'required' => true,
            'placeholder' => 'label.choose_target',
            'choices' => $targetChoices,
            'choice_translation_domain' => false,
            'attr' => ['data-ea-widget' => 'ea-autocomplete'],
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
