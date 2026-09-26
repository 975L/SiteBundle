<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\SiteBundle\Service;

use c975L\SiteBundle\Repository\PageRepository;
use c975L\UiBundle\Contract\CollectionSourceProviderInterface;
use c975L\UiBundle\Model\CollectionItem;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\RequestStack;

// The guided projects' films as a source of the "collection" block: a page holding that block is the site's tutorials page, at whatever address its editor gives it. Each film is drawn by TutorialItem.html.twig, its card opening the film in a dialog. No cache tag: a film published is a file dropped in public/medias/films, which no Doctrine listener sees, and reading its small manifest at every render costs nothing
class TutorialCollectionSourceProvider implements CollectionSourceProviderInterface
{
    public const string SOURCE = 'site.collection.tutorials';

    public const string ITEM_TEMPLATE = '@c975LSite/collection/TutorialItem.html.twig';

    // The form a report is written in, the one every c975L site seeds (see TutorialController::report())
    public const string CONTACT_FORM = 'contact';

    public function __construct(
        private readonly TutorialCatalog $catalog,
        private readonly PageRepository $pageRepository,
        private readonly RequestStack $requestStack,
        #[Autowire(param: 'kernel.default_locale')]
        private readonly string $defaultLocale,
    ) {
    }

    public function getSources(): array
    {
        return [
            self::SOURCE => [
                // Translated in the ui domain, where CollectionType's source choices are
                'label' => 'label.tutorials_collection_source',
                'items' => fn (?int $limit): array => $this->items($this->catalog->all($this->locale()), $limit),
                'itemTemplate' => self::ITEM_TEMPLATE,
            ],
        ];
    }

    // The films as items, numbered in parcours order, each knowing the one before and after for its dialog's own navigation. Public so an app grouping its films differently (by bundle, by theme) draws them with the very same card
    /**
     * @param list<array<string, mixed>> $tutorials
     *
     * @return list<CollectionItem>
     */
    public function items(array $tutorials, ?int $limit = null): array
    {
        $tutorials = null === $limit ? $tutorials : \array_slice($tutorials, 0, $limit);

        // A report link leads to the contact form: none is drawn on a site that has no page holding it
        $reportable = null !== $this->pageRepository->findOneByFormBlockName(self::CONTACT_FORM);

        $items = [];
        foreach ($tutorials as $index => $tutorial) {
            $items[] = new CollectionItem(
                title: $tutorial['label'],
                description: $tutorial['description'],
                imageUrl: $tutorial['poster'],
                data: [
                    'tutorial' => $tutorial,
                    'number' => $index + 1,
                    'previous' => $tutorials[$index - 1] ?? null,
                    'next' => $tutorials[$index + 1] ?? null,
                    'reportable' => $reportable,
                ],
            );
        }

        return $items;
    }

    private function locale(): string
    {
        return $this->requestStack->getCurrentRequest()?->getLocale() ?? $this->defaultLocale;
    }
}
