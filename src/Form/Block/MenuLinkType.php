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
use c975L\SiteBundle\Entity\Page;
use c975L\SiteBundle\Repository\PageRepository;
use c975L\UiBundle\Service\BlockAnchorCollector;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Contracts\Translation\TranslatorInterface;

// A single flat, alphabetically-sorted "target" select (pages and routes mixed) - decoded at render time by MenuExtension::getMenuLinkUrl()/ getMenuLinkLabel(), using the "page:ID" / "route:NAME" convention
class MenuLinkType extends AbstractType
{
    public function __construct(
        private readonly LinkableRouteRegistry $linkableRouteRegistry,
        private readonly PageRepository $pageRepository,
        private readonly TranslatorInterface $translator,
        private readonly BlockAnchorCollector $anchorCollector,
    ) {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $targetChoices = $this->targetChoices();

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
            ]);
    }

    // Every "page:ID", "page:ID#anchor-blockId" and "route:NAME" a menu link can point at, as one flat list (see MenuExtension::getMenuLinkUrl() for how each is decoded back)
    private function targetChoices(): array
    {
        // Eager-joins blocks (and their nested slots, walked too - see BlockAnchorCollector) so building each page's anchor choices doesn't trigger one extra query per page/container (getBlocks()/getSlots() would otherwise lazy-load their collection on each access)
        $pages = $this->pageRepository->createQueryBuilder('p')
            ->leftJoin('p.blocks', 'b')
            ->addSelect('b')
            ->leftJoin('b.slots', 's')
            ->addSelect('s')
            ->andWhere('p.isDeleted = :deleted')
            ->setParameter('deleted', false)
            ->getQuery()
            ->getResult();

        $choices = [];
        foreach ($pages as $page) {
            // Unpublished pages stay pickable (editors need to wire menu links while still drafting a page) but are flagged: MenuExtension::getMenuLinkUrl() already resolves them to an empty URL until the page is published, so the entry just stays inert rather than ever breaking
            $pageLabel = $page->getTitle() . ($page->isPublished() ? '' : ' (' . $this->translator->trans('label.draft', [], 'site') . ')');
            $this->addChoice($choices, $pageLabel, 'page:' . $page->getId());
            $this->addAnchorChoices($choices, $page, $pageLabel);
        }

        // A contributed target is labelled by the registry itself - and by its picker label where it has one, an entry standing for one of a bundle's own rows saying what it is here ("Galerie - Paysages") among every page of the site, where the rendered menu item keeps that row's bare title
        foreach (array_keys($this->linkableRouteRegistry->all()) as $name) {
            $this->addChoice($choices, $this->linkableRouteRegistry->pickerLabel($name), 'route:' . $name);
        }

        ksort($choices, SORT_NATURAL | SORT_FLAG_CASE);

        return $choices;
    }

    // One flat entry per in-page anchor the page's blocks declare, those of a container's nested slots included (see UiBundle's BlockAnchorCollector) - no cascading/JS select needed, they sit in the same list as the pages themselves
    private function addAnchorChoices(array &$choices, Page $page, string $pageLabel): void
    {
        foreach ($this->anchorCollector->collect($page->getBlocks()) as $fragment => $sectionLabel) {
            $this->addChoice($choices, $pageLabel . ' → ' . $sectionLabel, 'page:' . $page->getId() . '#' . $fragment);
        }
    }

    // Choices are keyed by label - a second entry carrying the same one (two homonymous pages, a contributed route sharing a page's title...) would take the first's place and make it unpickable, so it gets numbered instead
    private function addChoice(array &$choices, string $label, string $value): void
    {
        $key = $label;
        for ($i = 2; isset($choices[$key]); ++$i) {
            $key = $label . ' (' . $i . ')';
        }

        $choices[$key] = $value;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => null,
            'translation_domain' => 'site',
        ]);
    }
}
