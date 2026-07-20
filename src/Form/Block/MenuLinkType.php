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
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Contracts\Translation\TranslatorInterface;

// A single flat, alphabetically-sorted, filterable "target" select (pages and routes mixed, EasyAdmin's TomSelect widget via data-ea-widget) - decoded at render time by MenuExtension::getMenuLinkUrl()/ getMenuLinkLabel(), using the "page:ID" / "route:NAME" convention
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
        // Eager-joins blocks so building each page's anchor choices below doesn't trigger one extra query per page (getBlocks() would otherwise lazy-load its ManyToMany collection on each access)
        $pages = $this->pageRepository->createQueryBuilder('p')
            ->leftJoin('p.blocks', 'b')
            ->addSelect('b')
            ->andWhere('p.isDeleted = :deleted')
            ->setParameter('deleted', false)
            ->getQuery()
            ->getResult();
        foreach ($pages as $page) {
            // Unpublished pages stay pickable (editors need to wire menu links while still drafting a page) but are flagged: MenuExtension::getMenuLinkUrl() already resolves them to an empty URL until the page is published, so the entry just stays inert rather than ever breaking
            $pageLabel = $page->getTitle() . ($page->isPublished() ? '' : ' (' . $this->translator->trans('label.draft', [], 'site') . ')');
            $targetChoices[$pageLabel] = 'page:' . $page->getId();

            // Flat entries for each of the page's own in-page anchors (see UiBundle's BlockAnchorSlugger/HasAnchorFieldTrait) - "page:ID#anchor-blockId", decoded by MenuExtension::getMenuLinkUrl(). No cascading/JS select needed: this stays a single filterable autocomplete list like the rest of $targetChoices.
            foreach ($page->getBlocks() as $block) {
                $anchor = $block->getData()['anchor'] ?? null;
                if (null === $anchor || '' === $anchor) {
                    continue;
                }

                // strip_tags: some kinds (hero, cta_band) use a TrixEditorType title, which may carry inline markup that must not leak into this plain-text select option label
                $sectionLabel = strip_tags((string) ($block->getData()['title'] ?? $anchor));
                $targetChoices[$pageLabel . ' → ' . $sectionLabel] = 'page:' . $page->getId() . '#' . $anchor . '-' . $block->getId();
            }
        }

        foreach ($this->linkableRouteRegistry->all() as $name => $route) {
            $targetChoices[$this->translator->trans($route['label'], [], $route['translation_domain'])] = 'route:' . $name;
        }

        ksort($targetChoices, SORT_NATURAL | SORT_FLAG_CASE);

        $builder
            ->add('target', ChoiceType::class, [
                'label' => 'label.menu_item_target',
                'required' => true,
                'placeholder' => 'label.choose_target',
                'choices' => $targetChoices,
                'choice_translation_domain' => false,
                'attr' => ['data-ea-widget' => 'ea-autocomplete'],
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
