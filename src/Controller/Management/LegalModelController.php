<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\SiteBundle\Controller\Management;

use c975L\ConfigBundle\Service\ConfigServiceInterface;
use c975L\SiteBundle\Form\LegalModelCustomizationType;
use c975L\SiteBundle\Repository\PageRepository;
use c975L\SiteBundle\Service\LegalModelCatalog;
use c975L\SiteBundle\Service\LegalModelCustomizer;
use c975L\SiteBundle\Service\LegalModelPlaceholders;
use c975L\UiBundle\Entity\Block;
use c975L\UiBundle\Service\BlockCacheInvalidator;
use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminRoute;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Contracts\Translation\TranslatorInterface;

// Customizing a legal model is deliberately its own screen rather than a few more fields on the block's form:
// the rows depend on which model the block points at, and BlockFormController's ajax sub-form only ever knows
// a block's kind, never its data
class LegalModelController extends AbstractController
{
    // EasyAdmin prefixes this with the Dashboard's own route name, giving management_XXX
    public const INDEX_ROUTE = 'management_site_legal_models';
    public const CUSTOMIZE_ROUTE = 'management_site_legal_model_customize';

    public function __construct(
        private readonly PageRepository $pageRepository,
        private readonly LegalModelCustomizer $customizer,
        private readonly LegalModelCatalog $catalog,
        private readonly LegalModelPlaceholders $placeholders,
        private readonly BlockCacheInvalidator $blockCacheInvalidator,
        private readonly EntityManagerInterface $manager,
        private readonly ConfigServiceInterface $configService,
        private readonly TranslatorInterface $translator,
    ) {
    }

    // Every legal page of the site, with how far each one has drifted from the bundle's own wording
    #[AdminRoute(
        path: '/site/legal-models',
        name: 'site_legal_models',
    )]
    public function index(Request $request): Response
    {
        $this->denyAccessUnlessGranted($this->configService->get('site-role-editor'));

        $rows = [];
        foreach ($this->pageRepository->findWithLegalModelBlocks() as $pair) {
            $data = $pair['block']->getData();
            $model = (string) ($data['model'] ?? '');
            $customization = (array) ($data['customization'] ?? []);

            $rows[] = [
                'page' => $pair['page'],
                'block' => $pair['block'],
                'model' => $model,
                'label' => $this->catalog->label($model),
                'hidden' => \count((array) ($customization['hidden'] ?? [])),
                'overridden' => \count((array) ($customization['overrides'] ?? [])),
                'extra' => \count((array) ($customization['extra'] ?? [])),
                'drifted' => \count($this->customizer->drifted($model, $customization)),
            ];
        }

        return $this->render('@c975LSite/management/legal_model_index.html.twig', [
            'rows' => $rows,
            'locale' => $request->getLocale(),
        ]);
    }

    // The unit-by-unit screen for one block: hide, retitle, rewrite, move, and add sections of your own
    #[AdminRoute(
        path: '/site/legal-models/{block}',
        name: 'site_legal_model_customize',
        options: ['requirements' => ['block' => '\d+']],
    )]
    public function customize(Block $block, Request $request): Response
    {
        $this->denyAccessUnlessGranted($this->configService->get('site-role-editor'));

        $data = $block->getData();
        $model = (string) ($data['model'] ?? '');

        if ('legal_model' !== $block->getKind() || !$this->catalog->has($model)) {
            throw $this->createNotFoundException();
        }

        $locale = $request->getLocale();
        $units = $this->customizer->units($model, $locale);
        $customization = (array) ($data['customization'] ?? []);

        $form = $this->createForm(
            LegalModelCustomizationType::class,
            $this->customizer->toFormData($units, $customization),
            ['sections' => $this->sectionChoices($units)],
        );
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $block->setData($this->customizer->apply(
                $data,
                $this->customizer->toCustomization($form->getData(), $units, $locale),
            ));
            $this->manager->flush();
            // The rendered block is cached per (block, locale) and the delta changes what it renders
            $this->blockCacheInvalidator->invalidateAll();

            $this->addFlash('success', $this->translator->trans('flash.legal_model_customized', [], 'site'));

            return $this->redirectToCustomize($block);
        }

        return $this->render('@c975LSite/management/legal_model_customize.html.twig', [
            'form' => $form->createView(),
            'block' => $block,
            'label' => $this->catalog->label($model),
            'tree' => $this->tree($this->customizer->ordered($units, (array) ($customization['positions'] ?? []))),
            'drifted' => $this->customizer->drifted($model, $customization),
            'placeholders' => $this->placeholders->slugs(),
        ]);
    }

    private function redirectToCustomize(Block $block): RedirectResponse
    {
        return $this->redirectToRoute(self::CUSTOMIZE_ROUTE, ['block' => $block->getId()]);
    }

    // The flat unit list nested back into the two levels the screen renders, each carrying the index of its
    // own row in the form's "units" collection. Sub-sections are drawn inside their section's card, which is
    // also what scopes the drag and drop: ea-sortable only ever reorders within one collection container
    private function tree(array $ordered): array
    {
        $tree = [];
        $parents = [];

        foreach ($ordered as $index => $unit) {
            $node = ['index' => $index, 'unit' => $unit, 'children' => []];

            if (3 === $unit['level'] && isset($parents[$this->customizer->scopeOf($unit['id'])])) {
                $tree[$parents[$this->customizer->scopeOf($unit['id'])]]['children'][] = $node;
                continue;
            }

            $parents[$unit['id']] = \count($tree);
            $tree[] = $node;
        }

        return $tree;
    }

    // Top-level sections an added one can be nested under, as label => id
    private function sectionChoices(array $units): array
    {
        $choices = [];
        foreach ($units as $unit) {
            if (2 === $unit['level'] && '' !== $unit['title']) {
                $choices[$unit['title']] = $unit['id'];
            }
        }

        return $choices;
    }
}
