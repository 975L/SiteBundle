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
use c975L\SiteBundle\Entity\Page;
use c975L\SiteBundle\Repository\PageRepository;
use c975L\SiteBundle\Tests\Repository\Entity\PageAuthorFixture;
use c975L\UiBundle\Entity\Block;
use c975L\UiBundle\Entity\Media;
use c975L\UiBundle\Repository\BlockRepository;
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

// The pages a social series may announce, read through a join on the page's blocks: run on SQLite in memory rather than on a stub that would only echo what it was told
#[RequiresPhpExtension('pdo_sqlite')]
class PageRepositoryTest extends TestCase
{
    private EntityManagerInterface $entityManager;

    private PageRepository $repository;

    protected function setUp(): void
    {
        $uiEntities = \dirname(new \ReflectionClass(Block::class)->getFileName());
        $config = ORMSetup::createAttributeMetadataConfig([\dirname(__DIR__, 2) . '/src/Entity', $uiEntities, __DIR__ . '/Entity'], true);
        $config->enableNativeLazyObjects(true);
        $config->setNamingStrategy(new UnderscoreNamingStrategy(\CASE_LOWER, true));
        // The author a Page names by its interface, the application's User standing behind it
        $resolver = new ResolveTargetEntityListener();
        $resolver->addResolveTargetEntity(UserInterface::class, PageAuthorFixture::class, []);
        $eventManager = new EventManager();
        $eventManager->addEventSubscriber($resolver);
        $this->entityManager = new EntityManager(DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true], $config), $config, $eventManager);
        new SchemaTool($this->entityManager)->createSchema(array_map($this->entityManager->getClassMetadata(...), [Page::class, Block::class, Media::class, PageAuthorFixture::class]));

        $registry = $this->createStub(ManagerRegistry::class);
        $registry->method('getManagerForClass')->willReturn($this->entityManager);
        $this->repository = new PageRepository($registry, $this->createStub(BlockRepository::class));
    }

    // A published, indexable page holding a block of each kind given
    private function page(string $slug, string ...$kinds): void
    {
        $page = new Page()->setTitle($slug)->setSlug($slug)->setCreation(new \DateTime())->setModification(new \DateTime())->setIsPublished(true)->setIsIndexable(true);
        foreach ($kinds as $position => $kind) {
            $page->addBlock(new Block()->setKind($kind)->setPosition($position));
        }
        $this->entityManager->persist($page);
        $this->entityManager->flush();
    }

    // A legal notice stays out even with a text of its own beside its legal block, as the terms of use often have
    public function testAPageHoldingALegalBlockIsNeverACandidate(): void
    {
        $this->page('astuce', 'hero', 'text_section');
        $this->page('cgu', 'legal_model', 'text_section');
        $this->page('cookies', 'legal_model');

        $slugs = array_map(static fn (Page $page): ?string => $page->getSlug(), $this->repository->findSocialCandidates([]));

        $this->assertSame(['astuce'], $slugs);
    }
}
