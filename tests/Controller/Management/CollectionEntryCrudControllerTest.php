<?php
/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\SiteBundle\Tests\Controller\Management;

use c975L\ConfigBundle\Service\ConfigServiceInterface;
use c975L\SiteBundle\Controller\Management\CollectionEntryCrudController;
use c975L\SiteBundle\Entity\CollectionEntry;
use c975L\SiteBundle\Repository\CollectionEntryRepository;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\String\Slugger\AsciiSlugger;
use Symfony\Component\String\Slugger\SluggerInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class CollectionEntryCrudControllerTest extends TestCase
{
    use ControllerContainerTestTrait;

    private function createController(
        ?CollectionEntryRepository $collectionEntryRepository = null,
        ?SluggerInterface $slugger = null,
    ): CollectionEntryCrudController {
        $configService = $this->createStub(ConfigServiceInterface::class);
        $configService->method('get')->willReturn('ROLE_EDITOR');

        $translator = $this->createStub(TranslatorInterface::class);
        $translator->method('trans')->willReturnArgument(0);

        return new CollectionEntryCrudController(
            $configService,
            $translator,
            $slugger ?? new AsciiSlugger(),
            $collectionEntryRepository ?? $this->createStub(CollectionEntryRepository::class),
        );
    }

    private function invokePrivate(CollectionEntryCrudController $controller, string $method, array $args = []): mixed
    {
        return (new \ReflectionMethod($controller, $method))->invoke($controller, ...$args);
    }

    private function withId(CollectionEntry $entry, int $id): CollectionEntry
    {
        (new \ReflectionProperty(CollectionEntry::class, 'id'))->setValue($entry, $id);

        return $entry;
    }

    // --- persistEntity / updateEntity ----------------------------------------------------------------

    public function testPersistEntitySlugifiesAndDelegatesToParent(): void
    {
        $controller = $this->createController();
        $entry = (new CollectionEntry())->setGroup('projects')->setSlug('Néw Ïtem!');

        $manager = $this->createMock(EntityManagerInterface::class);
        $manager->expects($this->once())->method('persist')->with($entry);
        $manager->expects($this->once())->method('flush');

        $controller->persistEntity($manager, $entry);

        $this->assertSame('new-item', $entry->getSlug());
    }

    public function testUpdateEntitySlugifiesAndDelegatesToParent(): void
    {
        $controller = $this->createController();
        $entry = (new CollectionEntry())->setGroup('projects')->setSlug('Héllo Wörld!');

        $manager = $this->createMock(EntityManagerInterface::class);
        $manager->expects($this->once())->method('persist')->with($entry);
        $manager->expects($this->once())->method('flush');

        $controller->updateEntity($manager, $entry);

        $this->assertSame('hello-world', $entry->getSlug());
    }

    // --- slugifyEntry (private) ----------------------------------------------------------------------

    public function testSlugifyEntryNormalizesAccentsSpacesAndCase(): void
    {
        $controller = $this->createController();
        $entry = (new CollectionEntry())->setGroup('projects')->setSlug('Héllo Wörld!');

        $this->invokePrivate($controller, 'slugifyEntry', [$entry]);

        $this->assertSame('hello-world', $entry->getSlug());
    }

    public function testSlugifyEntryDoesNothingWhenSlugIsNull(): void
    {
        $controller = $this->createController();
        $entry = new CollectionEntry();

        $this->invokePrivate($controller, 'slugifyEntry', [$entry]);

        $this->assertNull($entry->getSlug());
    }

    // Collision check is scoped to the entry's own "group" - a same-named slug in another group never collides
    public function testSlugifyEntryAppendsASuffixOnCollisionWithinTheSameGroup(): void
    {
        $repository = $this->createStub(CollectionEntryRepository::class);
        $repository->method('findOneByGroupAndSlug')->willReturnMap([
            ['projects', 'my-item', $this->withId(new CollectionEntry(), 1)],
            ['projects', 'my-item-2', null],
        ]);

        $controller = $this->createController($repository);
        $entry = (new CollectionEntry())->setGroup('projects')->setSlug('My Item');

        $this->invokePrivate($controller, 'slugifyEntry', [$entry]);

        $this->assertSame('my-item-2', $entry->getSlug());
    }

    // Re-saving an entry with its own unchanged slug must not collide with itself
    public function testSlugifyEntryDoesNotCollideWithItself(): void
    {
        $repository = $this->createStub(CollectionEntryRepository::class);
        $repository->method('findOneByGroupAndSlug')->willReturn($this->withId(
            (new CollectionEntry())->setGroup('projects')->setSlug('my-item'),
            5
        ));

        $controller = $this->createController($repository);
        $entry = $this->withId((new CollectionEntry())->setGroup('projects')->setSlug('My Item'), 5);

        $this->invokePrivate($controller, 'slugifyEntry', [$entry]);

        $this->assertSame('my-item', $entry->getSlug());
    }

    // --- reorder --------------------------------------------------------------------------------------

    private function createReorderRequest(array $payload): Request
    {
        return new Request([], [], [], [], [], [], json_encode($payload));
    }

    public function testReorderPersistsPositionsInTheSubmittedOrder(): void
    {
        $entry1 = $this->withId((new CollectionEntry())->setGroup('sites'), 1);
        $entry2 = $this->withId((new CollectionEntry())->setGroup('sites'), 2);
        $entry3 = $this->withId((new CollectionEntry())->setGroup('sites'), 3);

        $repository = $this->createStub(CollectionEntryRepository::class);
        $repository->method('findBy')->willReturn([$entry1, $entry2, $entry3]);

        $controller = $this->createController($repository);
        $controller->setContainer($this->createContainer([
            'security.authorization_checker' => $this->createAuthorizationChecker(true),
            'security.csrf.token_manager' => $this->createCsrfTokenManager(true),
        ]));

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects($this->once())->method('flush');

        $response = $controller->reorder(
            $this->createReorderRequest(['group' => 'sites', 'ids' => [3, 1, 2], '_token' => 'valid-token']),
            $entityManager,
        );

        $this->assertSame(0, $entry3->getPosition());
        $this->assertSame(1, $entry1->getPosition());
        $this->assertSame(2, $entry2->getPosition());
        $this->assertSame(['success' => true], json_decode($response->getContent(), true));
    }

    public function testReorderDeniesAccessWhenNotGranted(): void
    {
        $this->expectException(AccessDeniedException::class);

        $controller = $this->createController();
        $controller->setContainer($this->createContainer([
            'security.authorization_checker' => $this->createAuthorizationChecker(false),
        ]));

        $controller->reorder($this->createReorderRequest([]), $this->createStub(EntityManagerInterface::class));
    }

    public function testReorderDeniesAccessWhenCsrfTokenIsInvalid(): void
    {
        $this->expectException(AccessDeniedException::class);

        $controller = $this->createController();
        $controller->setContainer($this->createContainer([
            'security.authorization_checker' => $this->createAuthorizationChecker(true),
            'security.csrf.token_manager' => $this->createCsrfTokenManager(false),
        ]));

        $controller->reorder(
            $this->createReorderRequest(['group' => 'sites', 'ids' => [1], '_token' => 'invalid-token']),
            $this->createStub(EntityManagerInterface::class),
        );
    }

    // An id whose entry doesn't belong to the submitted group never reaches setPosition() - the index
    // lists every group in one flat table, so a tampered payload could otherwise reorder another group
    public function testReorderDeniesAccessWhenAnEntryDoesNotBelongToTheSubmittedGroup(): void
    {
        $this->expectException(AccessDeniedException::class);

        $entry = $this->withId((new CollectionEntry())->setGroup('other-group'), 1);

        $repository = $this->createStub(CollectionEntryRepository::class);
        $repository->method('findBy')->willReturn([$entry]);

        $controller = $this->createController($repository);
        $controller->setContainer($this->createContainer([
            'security.authorization_checker' => $this->createAuthorizationChecker(true),
            'security.csrf.token_manager' => $this->createCsrfTokenManager(true),
        ]));

        $controller->reorder(
            $this->createReorderRequest(['group' => 'sites', 'ids' => [1], '_token' => 'valid-token']),
            $this->createStub(EntityManagerInterface::class),
        );
    }
}
