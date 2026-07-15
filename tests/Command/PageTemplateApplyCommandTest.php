<?php
/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\SiteBundle\Tests\Command;

use c975L\SiteBundle\Command\PageTemplateApplyCommand;
use c975L\SiteBundle\Entity\Page;
use c975L\SiteBundle\Management\PageTemplateApplier;
use c975L\SiteBundle\Management\SitePageTemplateProvider;
use c975L\SiteBundle\Repository\PageRepository;
use c975L\UiBundle\Entity\Block;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

class PageTemplateApplyCommandTest extends TestCase
{
    private function template(): array
    {
        return [
            'label' => 'label.test',
            'blocks' => [
                ['kind' => 'hero', 'data' => ['title' => 'Hello']],
            ],
        ];
    }

    private function createTester(
        ?Page $existingPage = null,
        ?SitePageTemplateProvider $templateProvider = null,
        ?EntityManagerInterface $entityManager = null,
    ): CommandTester {
        $templateProvider ??= $this->createConfiguredStub(SitePageTemplateProvider::class, [
            'getTemplate' => null,
        ]);

        $pageRepository = $this->createStub(PageRepository::class);
        $pageRepository->method('findOneBySlugForDisplay')->willReturn($existingPage);

        $entityManager ??= $this->createStub(EntityManagerInterface::class);

        $security = $this->createStub(Security::class);
        $security->method('getUser')->willReturn(null);

        return new CommandTester(new PageTemplateApplyCommand(
            $entityManager,
            $pageRepository,
            $templateProvider,
            new PageTemplateApplier(),
            $security,
        ));
    }

    public function testExecuteFailsWhenTemplateIsNeitherAKnownSlugNorAValidFile(): void
    {
        $tester = $this->createTester();

        $statusCode = $tester->execute(['template' => 'does-not-exist', 'page' => 'home']);

        $this->assertSame(Command::FAILURE, $statusCode);
        $this->assertStringContainsString('No page template found', $tester->getDisplay());
    }

    public function testExecuteFailsWhenPageDoesNotExistAndNoTitleGiven(): void
    {
        $templateProvider = $this->createConfiguredStub(SitePageTemplateProvider::class, [
            'getTemplate' => $this->template(),
        ]);
        $tester = $this->createTester(templateProvider: $templateProvider);

        $statusCode = $tester->execute(['template' => 'agency-home-warm', 'page' => 'home-copy']);

        $this->assertSame(Command::FAILURE, $statusCode);
        $this->assertStringContainsString('pass --title to create it', $tester->getDisplay());
    }

    public function testExecuteCreatesAnUnpublishedPageWithTheTemplatesBlocks(): void
    {
        $templateProvider = $this->createConfiguredStub(SitePageTemplateProvider::class, [
            'getTemplate' => $this->template(),
        ]);
        $entityManager = $this->createStub(EntityManagerInterface::class);
        $persisted = null;
        $entityManager->method('persist')->willReturnCallback(function ($entity) use (&$persisted): void {
            $persisted = $entity;
        });
        $tester = $this->createTester(templateProvider: $templateProvider, entityManager: $entityManager);

        $statusCode = $tester->execute([
            'template' => 'agency-home-warm',
            'page' => 'home-copy',
            '--title' => 'Accueil (aperçu)',
        ]);

        $this->assertSame(Command::SUCCESS, $statusCode);
        $this->assertInstanceOf(Page::class, $persisted);
        $this->assertSame('home-copy', $persisted->getSlug());
        $this->assertFalse($persisted->isPublished());
        $this->assertCount(1, $persisted->getBlocks());
        $this->assertStringContainsString('Created page "home-copy": 1 block(s)', $tester->getDisplay());
    }

    public function testExecutePublishesTheNewPageWhenPublishOptionIsSet(): void
    {
        $templateProvider = $this->createConfiguredStub(SitePageTemplateProvider::class, [
            'getTemplate' => $this->template(),
        ]);
        $entityManager = $this->createStub(EntityManagerInterface::class);
        $persisted = null;
        $entityManager->method('persist')->willReturnCallback(function ($entity) use (&$persisted): void {
            $persisted = $entity;
        });
        $tester = $this->createTester(templateProvider: $templateProvider, entityManager: $entityManager);

        $tester->execute([
            'template' => 'agency-home-warm',
            'page' => 'home-copy',
            '--title' => 'Accueil',
            '--publish' => true,
        ]);

        $this->assertTrue($persisted->isPublished());
    }

    public function testExecuteAppendsToAnExistingPageWithoutReplace(): void
    {
        $page = (new Page())->setTitle('Home')->setSlug('home');
        $page->addBlock((new Block())->setKind('legacy_content')->setPosition(0));
        $templateProvider = $this->createConfiguredStub(SitePageTemplateProvider::class, [
            'getTemplate' => $this->template(),
        ]);
        $tester = $this->createTester(existingPage: $page, templateProvider: $templateProvider);

        $statusCode = $tester->execute(['template' => 'agency-home-warm', 'page' => 'home']);

        $this->assertSame(Command::SUCCESS, $statusCode);
        $this->assertCount(2, $page->getBlocks());
        $this->assertStringContainsString('Updated page "home"', $tester->getDisplay());
    }

    public function testExecuteRemovesExistingBlocksFirstWhenReplaceIsSet(): void
    {
        $page = (new Page())->setTitle('Home')->setSlug('home');
        $page->addBlock((new Block())->setKind('legacy_content')->setPosition(0));
        $templateProvider = $this->createConfiguredStub(SitePageTemplateProvider::class, [
            'getTemplate' => $this->template(),
        ]);
        $entityManager = $this->createStub(EntityManagerInterface::class);
        $removed = [];
        $entityManager->method('remove')->willReturnCallback(function ($entity) use (&$removed): void {
            $removed[] = $entity;
        });
        $tester = $this->createTester(existingPage: $page, templateProvider: $templateProvider, entityManager: $entityManager);

        $tester->execute(['template' => 'agency-home-warm', 'page' => 'home', '--replace' => true]);

        $this->assertCount(1, $removed);
        $this->assertSame('legacy_content', $removed[0]->getKind());
        $this->assertCount(1, $page->getBlocks());
        $this->assertSame('hero', $page->getBlocks()->first()->getKind());
    }

    // Not a known config/page-templates/ slug, but a real JSON file with the same shape
    public function testExecuteLoadsTheTemplateFromAFilePathWhenNotAKnownSlug(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'page-template-');
        file_put_contents($path, json_encode($this->template()));

        try {
            $entityManager = $this->createStub(EntityManagerInterface::class);
            $persisted = null;
            $entityManager->method('persist')->willReturnCallback(function ($entity) use (&$persisted): void {
                $persisted = $entity;
            });
            $tester = $this->createTester(entityManager: $entityManager);

            $statusCode = $tester->execute(['template' => $path, 'page' => 'home-copy', '--title' => 'Accueil']);

            $this->assertSame(Command::SUCCESS, $statusCode);
            $this->assertCount(1, $persisted->getBlocks());
        } finally {
            unlink($path);
        }
    }
}
