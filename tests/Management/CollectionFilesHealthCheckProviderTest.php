<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\SiteBundle\Tests\Management;

use c975L\ConfigBundle\Entity\HealthCheckResult;
use c975L\ConfigBundle\Service\ConfigServiceInterface;
use c975L\SiteBundle\Entity\CollectionGroup;
use c975L\SiteBundle\Entity\CollectionItem;
use c975L\SiteBundle\Management\CollectionFilesHealthCheckProvider;
use c975L\SiteBundle\Repository\CollectionItemRepository;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGeneratorInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Contracts\Translation\TranslatorInterface;

class CollectionFilesHealthCheckProviderTest extends TestCase
{
    private string $projectDir;

    protected function setUp(): void
    {
        $this->projectDir = sys_get_temp_dir() . '/collection-files-health-check-test-' . uniqid();
        new Filesystem()->mkdir($this->projectDir . '/public');
    }

    protected function tearDown(): void
    {
        new Filesystem()->remove($this->projectDir);
    }

    /**
     * @param array<int, array{0: CollectionItem, 1: bool}> $rows the item and whether its file sits on disk
     */
    private function createProvider(array $rows): CollectionFilesHealthCheckProvider
    {
        $items = [];
        foreach ($rows as [$item, $onDisk]) {
            if ($onDisk) {
                $path = $this->projectDir . '/public/' . $item->getFilename();
                new Filesystem()->mkdir(\dirname($path));
                file_put_contents($path, 'file');
            }

            $items[] = $item;
        }

        $collectionItemRepository = $this->createStub(CollectionItemRepository::class);
        $collectionItemRepository->method('findWithFilename')->willReturn($items);

        $configService = $this->createStub(ConfigServiceInterface::class);
        $configService->method('get')->willReturn('https://example.com');

        $adminUrlGenerator = $this->createStub(AdminUrlGeneratorInterface::class);
        $adminUrlGenerator->method('unsetAll')->willReturnSelf();
        $adminUrlGenerator->method('setController')->willReturnSelf();
        $adminUrlGenerator->method('setAction')->willReturnSelf();
        $adminUrlGenerator->method('setEntityId')->willReturnSelf();
        $adminUrlGenerator->method('generateUrl')->willReturn('/management/collection-item/edit');

        $translator = $this->createStub(TranslatorInterface::class);
        $translator->method('trans')->willReturnCallback(
            static fn (string $id, array $params = []) => $id . '|' . implode('', $params)
        );

        return new CollectionFilesHealthCheckProvider(
            $collectionItemRepository,
            $adminUrlGenerator,
            $configService,
            $translator,
            $this->projectDir,
        );
    }

    private function createItem(string $filename, string $title = 'Un client', int $id = 1): CollectionItem
    {
        $item = new CollectionItem()->setTitle($title)->setFilename($filename);
        new \ReflectionProperty(CollectionItem::class, 'id')->setValue($item, $id);

        return $item;
    }

    public function testGetKind(): void
    {
        $this->assertSame('files-site', $this->createProvider([])->getKind());
    }

    public function testACollectionDeclaringNoFileReportsNothing(): void
    {
        $this->assertSame([], $this->createProvider([])->runChecks());
    }

    public function testADeclaredFileMissingFromTheServerIsAnError(): void
    {
        $rows = $this->createProvider([[$this->createItem('medias/collection/logo-client.webp'), false]])->runChecks();

        $this->assertCount(1, $rows);
        $this->assertSame(HealthCheckResult::STATUS_ERROR, $rows[0]['status']);
        $this->assertSame('https://example.com/medias/collection/logo-client.webp', $rows[0]['url']);
        $this->assertSame('Un client', $rows[0]['label']);
        $this->assertStringContainsString('label.health_check_declared_file_missing', $rows[0]['summary']);
        $this->assertSame('/management/collection-item/edit', $rows[0]['editUrl']);
    }

    // The OK row is what lets a re-uploaded file go back to green: results are kept per url and kind
    public function testAFileInPlaceStillGetsItsRow(): void
    {
        $rows = $this->createProvider([[$this->createItem('medias/collection/logo-client.webp'), true]])->runChecks();

        $this->assertCount(1, $rows);
        $this->assertSame(HealthCheckResult::STATUS_OK, $rows[0]['status']);
    }

    // Two items can share a title, only ['collectionGroup', 'slug'] is unique, so the group is what tells their rows apart
    public function testTwoItemsSharingATitleAreNamedByTheirGroup(): void
    {
        $first = $this->createItem('medias/collection/logo-partners.webp', 'Logo', 1)
            ->setCollectionGroup(new CollectionGroup()->setName('Partners'));
        $second = $this->createItem('medias/collection/logo-clients.webp', 'Logo', 2)
            ->setCollectionGroup(new CollectionGroup()->setName('Clients'));

        $rows = $this->createProvider([[$first, false], [$second, false]])->runChecks();

        $this->assertCount(2, $rows);
        $this->assertSame('Partners / Logo', $rows[0]['label']);
        $this->assertSame('Clients / Logo', $rows[1]['label']);
    }

    // An item outside any group keeps its bare title, no dangling separator
    public function testAnItemWithoutGroupKeepsItsTitle(): void
    {
        $rows = $this->createProvider([[$this->createItem('medias/collection/logo-client.webp'), false]])->runChecks();

        $this->assertSame('Un client', $rows[0]['label']);
    }
}
