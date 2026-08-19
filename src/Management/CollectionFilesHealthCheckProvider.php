<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\SiteBundle\Management;

use c975L\ConfigBundle\Service\ConfigServiceInterface;
use c975L\SiteBundle\Controller\Management\CollectionItemCrudController;
use c975L\SiteBundle\Entity\CollectionItem;
use c975L\SiteBundle\Repository\CollectionItemRepository;
use c975L\UiBundle\Management\AbstractDeclaredFilesHealthCheckProvider;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGeneratorInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\Translation\TranslatorInterface;

// The files this bundle's own rows declare: the image of each collection item. Everything the check does is in the parent (see UiBundle's AbstractDeclaredFilesHealthCheckProvider), this only names what to look for
class CollectionFilesHealthCheckProvider extends AbstractDeclaredFilesHealthCheckProvider
{
    // Named here rather than restated as a literal wherever a row of this kind is picked out
    public const string KIND = 'files-site';

    public function __construct(
        private readonly CollectionItemRepository $collectionItemRepository,
        private readonly AdminUrlGeneratorInterface $adminUrlGenerator,
        ConfigServiceInterface $configService,
        TranslatorInterface $translator,
        #[Autowire(param: 'kernel.project_dir')]
        string $projectDir,
    ) {
        parent::__construct($configService, $translator, $projectDir);
    }

    public function getKind(): string
    {
        return self::KIND;
    }

    protected function declaredFiles(): iterable
    {
        foreach ($this->collectionItemRepository->findWithFilename() as $item) {
            yield [
                'filename' => (string) $item->getFilename(),
                'label' => $this->label($item),
                'editUrl' => null === $item->getId() ? null : $this->adminUrlGenerator
                    ->unsetAll()
                    ->setController(CollectionItemCrudController::class)
                    ->setAction(Action::EDIT)
                    ->setEntityId($item->getId())
                    ->generateUrl(),
            ];
        }
    }

    // The group the item belongs to is part of what names it: only ['collectionGroup', 'slug'] is unique, so two items titled "Logo" in two groups would otherwise give two identical rows
    private function label(CollectionItem $item): string
    {
        $group = $item->getCollectionGroup()?->getName();
        $title = (string) ($item->getTitle() ?: $item->getSlug());

        return null === $group ? $title : $group . ' / ' . $title;
    }
}
