<?php
/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\SiteBundle\Tests\Service;

use c975L\SiteBundle\Entity\Page;
use c975L\SiteBundle\Repository\PageRepository;
use c975L\SiteBundle\Service\DefaultPagesImporter;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\Security;

// Lives under src/Tests (not a sibling tests/ dir) so it stays autoloadable by consuming apps,
// whose attribute route loader recursively reflects every class under the bundle root
class DefaultPagesImporterTest extends TestCase
{
    // Builds an EntityManager double that records every persisted entity into $persisted
    private function createEntityManager(array &$persisted): EntityManagerInterface
    {
        $em = $this->createStub(EntityManagerInterface::class);
        $em->method('persist')->willReturnCallback(static function (object $entity) use (&$persisted): void {
            $persisted[] = $entity;
        });

        return $em;
    }

    // Builds a PageRepository double whose findOneBy() answers according to $existingSlugs
    private function createPageRepository(array $existingSlugs = []): PageRepository
    {
        $repository = $this->createStub(PageRepository::class);
        $repository->method('findOneBy')->willReturnCallback(
            static function (array $criteria) use ($existingSlugs): ?Page {
                return \in_array($criteria['slug'], $existingSlugs, true) ? new Page() : null;
            }
        );

        return $repository;
    }

    // Builds the importer with a repository/entity-manager pair and no logged-in user
    private function createImporter(
        PageRepository $pageRepository,
        EntityManagerInterface $em,
        string $defaultLocale = 'fr',
        array $enabledLocales = ['fr'],
    ): DefaultPagesImporter {
        $security = $this->createStub(Security::class);
        $security->method('getUser')->willReturn(null);

        return new DefaultPagesImporter($em, $pageRepository, $security, $defaultLocale, $enabledLocales);
    }

    // A brand-new fr install has no pages yet: every definition but the ShopBundle-gated one is created
    public function testImportCreatesAllDefinitionsForDefaultLocaleWhenNoneExist(): void
    {
        $persisted = [];
        $repository = $this->createPageRepository();
        $importer = $this->createImporter($repository, $this->createEntityManager($persisted));

        $result = $importer->import();

        // terms-of-sales is gated behind c975L\ShopBundle, not installed here, so it's neither created nor skipped
        $this->assertSame(['created' => 6, 'skipped' => 0], $result);
        // Each of the 5 legal pages also persists its own legal_model Block, hence 11 persisted entities in total
        $pages = array_values(array_filter($persisted, static fn ($entity) => $entity instanceof Page));
        $this->assertCount(6, $pages);
        $this->assertSame('home', $pages[0]->getSlug());
    }

    // Re-running the import on a site that already has every page must not duplicate anything
    public function testImportSkipsPagesAlreadyPresentInDatabase(): void
    {
        $existingSlugs = ['home', 'mentions-legales', 'regles-de-confidentialite', 'conditions-generales-d-utilisation', 'cookies', 'copyright'];
        $persisted = [];
        $repository = $this->createPageRepository($existingSlugs);
        $importer = $this->createImporter($repository, $this->createEntityManager($persisted));

        $result = $importer->import();

        $this->assertSame(['created' => 0, 'skipped' => 6], $result);
        $this->assertSame([], $persisted);
    }

    // Definitions carrying a legal 'model' get an attached legal_model block pre-filled with today's date
    public function testImportAttachesLegalModelBlockToLegalPages(): void
    {
        $persisted = [];
        $repository = $this->createPageRepository();
        $importer = $this->createImporter($repository, $this->createEntityManager($persisted));

        $importer->import();

        $legalNotice = current(array_filter($persisted, static fn ($page) => $page instanceof Page && 'mentions-legales' === $page->getSlug()));
        $this->assertNotFalse($legalNotice);
        $this->assertCount(1, $legalNotice->getBlocks());
        $this->assertSame('legal_model', $legalNotice->getBlocks()->first()->getKind());
        $this->assertSame('france/legal-notice', $legalNotice->getBlocks()->first()->getData()['model']);
    }

    // The interactive command path (SiteCreateCommand) lets a callback veto a page or override its published state
    public function testImportHonoursOnPageCallbackDecisionAndOverride(): void
    {
        $persisted = [];
        $repository = $this->createPageRepository();
        $importer = $this->createImporter($repository, $this->createEntityManager($persisted));

        // Imports only the homepage, forcing it to be unpublished regardless of the built-in default
        $onPage = static fn (array $def): array => [
            'import' => 'home' === $def['slug'],
            'isPublished' => false,
        ];

        $result = $importer->import($onPage);

        // Pages declined by the callback are neither created nor counted as skipped
        $this->assertSame(['created' => 1, 'skipped' => 0], $result);
        $this->assertCount(1, $persisted);
        $this->assertFalse($persisted[0]->isPublished());
    }

    // A locale absent from getDefinitions() (no translation yet) must be silently ignored, not error out
    public function testImportIgnoresEnabledLocaleWithoutDefinitions(): void
    {
        $persisted = [];
        $repository = $this->createPageRepository();
        $importer = $this->createImporter($repository, $this->createEntityManager($persisted), 'fr', ['fr', 'de']);

        $result = $importer->import();

        $this->assertSame(['created' => 6, 'skipped' => 0], $result);
    }

    // SiteCreateCommand offers legal pages as footer menu items, in a fixed reading order rather than definition order
    public function testGetLegalPageSlugsByModelReturnsSlugsInFixedOrder(): void
    {
        $repository = $this->createPageRepository();
        $persisted = [];
        $importer = $this->createImporter($repository, $this->createEntityManager($persisted));

        $slugsByModel = $importer->getLegalPageSlugsByModel();

        $this->assertSame(
            [
                'france/legal-notice' => 'mentions-legales',
                'france/privacy-policy' => 'regles-de-confidentialite',
                'france/terms-of-use' => 'conditions-generales-d-utilisation',
                'france/cookies' => 'cookies',
                'france/copyright' => 'copyright',
            ],
            $slugsByModel
        );
    }
}
