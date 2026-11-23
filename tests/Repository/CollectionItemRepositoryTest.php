<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\SiteBundle\Tests\Repository;

use c975L\ConfigBundle\Contract\UserInterface;
use c975L\SiteBundle\Entity\CollectionGroup;
use c975L\SiteBundle\Entity\CollectionItem;
use c975L\SiteBundle\Repository\CollectionItemRepository;
use c975L\SiteBundle\Tests\Repository\Entity\PageAuthorFixture;
use Doctrine\Common\EventManager;
use Doctrine\DBAL\DriverManager;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\UnderscoreNamingStrategy;
use Doctrine\ORM\ORMSetup;
use Doctrine\ORM\Tools\ResolveTargetEntityListener;
use Doctrine\ORM\Tools\SchemaTool;
use Doctrine\Persistence\ManagerRegistry;
use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use PHPUnit\Framework\TestCase;

// The item counts of the collections, read through a grouped query: run on SQLite in memory rather than on a stub that would only echo what it was told
#[RequiresPhpExtension('pdo_sqlite')]
class CollectionItemRepositoryTest extends TestCase
{
    private EntityManagerInterface $entityManager;

    private CollectionItemRepository $repository;

    protected function setUp(): void
    {
        $config = ORMSetup::createAttributeMetadataConfig([\dirname(__DIR__, 2) . '/src/Entity', __DIR__ . '/Entity'], true);
        $config->enableNativeLazyObjects(true);
        $config->setUseSchemaEditorApi(true);
        $config->setNamingStrategy(new UnderscoreNamingStrategy(\CASE_LOWER));
        // The author an item names by its interface, the application's User standing behind it
        $resolver = new ResolveTargetEntityListener();
        $resolver->addResolveTargetEntity(UserInterface::class, PageAuthorFixture::class, []);
        $eventManager = new EventManager();
        $eventManager->addEventSubscriber($resolver);
        $this->entityManager = new EntityManager(DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true], $config), $config, $eventManager);
        new SchemaTool($this->entityManager)->createSchema(array_map($this->entityManager->getClassMetadata(...), [CollectionGroup::class, CollectionItem::class, PageAuthorFixture::class]));

        $registry = $this->createStub(ManagerRegistry::class);
        $registry->method('getManagerForClass')->willReturn($this->entityManager);
        $this->repository = new CollectionItemRepository($registry);
    }

    // A collection holding as many items as given
    private function group(string $slug, int $items): CollectionGroup
    {
        $group = new CollectionGroup()->setName($slug)->setSlug($slug);
        $this->entityManager->persist($group);
        for ($position = 0; $position < $items; ++$position) {
            $this->entityManager->persist(new CollectionItem()->setCollectionGroup($group)->setTitle('item-' . $position)->setSlug('item-' . $position)->setPosition($position));
        }
        $this->entityManager->flush();

        return $group;
    }

    // Every collection counted by its id, one without any item left out
    public function testCountByCollectionGroups(): void
    {
        $catalog = $this->group('catalog', 3);
        $projects = $this->group('projects', 1);
        $this->group('empty', 0);

        $this->assertSame([$catalog->getId() => 3, $projects->getId() => 1], $this->repository->countByCollectionGroups());
    }
}
