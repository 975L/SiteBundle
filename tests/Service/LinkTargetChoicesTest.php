<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\SiteBundle\Tests\Service;

use c975L\ConfigBundle\Management\LinkableRouteRegistry;
use c975L\SiteBundle\Entity\Page;
use c975L\SiteBundle\Repository\PageRepository;
use c975L\SiteBundle\Service\LinkTargetChoices;
use c975L\UiBundle\Entity\Block;
use c975L\UiBundle\Service\BlockAnchorCollector;
use Doctrine\ORM\Query;
use Doctrine\ORM\QueryBuilder;
use PHPUnit\Framework\TestCase;
use Symfony\Contracts\Translation\TranslatorInterface;

class LinkTargetChoicesTest extends TestCase
{
    // Pages, their sections and contributed routes share one list, sorted by label whatever their kind
    public function testPagesSectionsAndRoutesAreListedTogetherSortedByLabel(): void
    {
        $page = $this->withId(new Page()->setTitle('Home')->setIsPublished(true), 1);
        $section = $this->withId(new Block(), 7);
        $section->setData(['anchor' => 'services', 'title' => 'Services']);
        $page->addBlock($section);

        $this->assertSame(
            ['Boutique' => 'route:shop_index', 'Home' => 'page:1', 'Home → Services' => 'page:1#services-7'],
            $this->choices([$page], ['shop_index' => 'Boutique'])->linkTargets(),
        );
    }

    // Nothing to link to gives an empty list rather than an error
    public function testNoPageAndNoRouteGivesAnEmptyList(): void
    {
        $this->assertSame([], $this->choices([], [])->linkTargets());
    }

    private function choices(array $pages, array $routes): LinkTargetChoices
    {
        $query = $this->createStub(Query::class);
        $query->method('getResult')->willReturn($pages);
        $queryBuilder = $this->createStub(QueryBuilder::class);
        $queryBuilder->method('leftJoin')->willReturnSelf();
        $queryBuilder->method('addSelect')->willReturnSelf();
        $queryBuilder->method('andWhere')->willReturnSelf();
        $queryBuilder->method('setParameter')->willReturnSelf();
        $queryBuilder->method('getQuery')->willReturn($query);
        $repository = $this->createStub(PageRepository::class);
        $repository->method('createQueryBuilder')->willReturn($queryBuilder);

        $registry = $this->createStub(LinkableRouteRegistry::class);
        $registry->method('all')->willReturn($routes);
        $registry->method('pickerLabel')->willReturnCallback(static fn (string $name): string => $routes[$name]);

        $translator = $this->createStub(TranslatorInterface::class);
        $translator->method('trans')->willReturnArgument(0);

        return new LinkTargetChoices($registry, $repository, $translator, new BlockAnchorCollector());
    }

    private function withId(object $entity, int $id): object
    {
        new \ReflectionProperty($entity::class, 'id')->setValue($entity, $id);

        return $entity;
    }
}
