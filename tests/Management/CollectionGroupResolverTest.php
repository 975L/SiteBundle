<?php
/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\SiteBundle\Tests\Management;

use c975L\SiteBundle\Entity\CollectionGroup;
use c975L\SiteBundle\Management\CollectionGroupResolver;
use c975L\SiteBundle\Repository\CollectionGroupRepository;
use PHPUnit\Framework\TestCase;
use Symfony\Component\String\Slugger\AsciiSlugger;

class CollectionGroupResolverTest extends TestCase
{
    private function createRepository(?CollectionGroup $existing = null): CollectionGroupRepository
    {
        $repository = $this->createStub(CollectionGroupRepository::class);
        $repository->method('findOneBySlug')->willReturn($existing);

        return $repository;
    }

    public function testResolveReturnsTheExistingGroupMatchedBySlugAsNotNew(): void
    {
        $projects = (new CollectionGroup())->setName('Projects')->setSlug('projects');
        $resolver = new CollectionGroupResolver($this->createRepository($projects), new AsciiSlugger());

        $usedSlugs = [];
        [$collectionGroup, $isNew] = $resolver->resolve('Projects', $usedSlugs);

        $this->assertSame($projects, $collectionGroup);
        $this->assertFalse($isNew);
    }

    public function testResolveCreatesAnUnpersistedNewGroupAsNew(): void
    {
        $resolver = new CollectionGroupResolver($this->createRepository(null), new AsciiSlugger());

        $usedSlugs = [];
        [$collectionGroup, $isNew] = $resolver->resolve('New Collection', $usedSlugs);

        $this->assertSame('New Collection', $collectionGroup->getName());
        $this->assertSame('new-collection', $collectionGroup->getSlug());
        $this->assertTrue($isNew);
    }

    // "projects" is already claimed in-batch (an earlier resolve() call this same run) and "projects-2" is already taken by an unrelated group from a previous import - the new group must skip both, not collide with either on flush()
    public function testResolveSkipsAnUnrelatedExistingGroupAlreadySittingOnTheFirstSuffixedCandidate(): void
    {
        $repository = $this->createStub(CollectionGroupRepository::class);
        $repository->method('findOneBySlug')->willReturnCallback(
            static fn (string $slug): ?CollectionGroup => 'projects-2' === $slug ? (new CollectionGroup())->setName('Projects Legacy')->setSlug('projects-2') : null
        );
        $resolver = new CollectionGroupResolver($repository, new AsciiSlugger());

        $usedSlugs = ['projects' => true];
        [$collectionGroup, $isNew] = $resolver->resolve('Projects!', $usedSlugs);

        $this->assertSame('projects-3', $collectionGroup->getSlug());
        $this->assertTrue($isNew);
    }

    // Two different names slugifying to the same value within one run must not both get the plain slug - findOneBySlug() can't see the first one until flush(), only the $usedSlugs guard catches this
    public function testResolveTracksSlugsAcrossCallsViaUsedSlugs(): void
    {
        $resolver = new CollectionGroupResolver($this->createRepository(null), new AsciiSlugger());

        $usedSlugs = [];
        [$first] = $resolver->resolve('New York!', $usedSlugs);
        [$second] = $resolver->resolve('New York?', $usedSlugs);

        $this->assertSame('new-york', $first->getSlug());
        $this->assertSame('new-york-2', $second->getSlug());
    }
}
