<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\SiteBundle\Mcp;

use c975L\ConfigBundle\Contract\UserInterface;
use c975L\ConfigBundle\Mcp\McpTool;
use c975L\ConfigBundle\Mcp\McpToolProviderInterface;
use c975L\ConfigBundle\Service\ConfigServiceInterface;
use c975L\SiteBundle\Entity\Page;
use c975L\SiteBundle\Repository\PageRepository;
use c975L\SiteBundle\Service\PagePublicUrlResolver;
use c975L\SiteBundle\Service\PageSlugs;
use c975L\UiBundle\Service\BlockTextCollector;
use Doctrine\ORM\EntityManagerInterface;
use Mcp\Server\Exception\ToolCallException;
use Psr\Clock\ClockInterface;
use Symfony\Bundle\SecurityBundle\Security;

// The site's pages on the site's MCP endpoint, for an account that edits them on the back office: listed, read with the texts of their blocks, a draft added and its title and description written with the slugs and redirects the back office writes (see PageSlugs), publishing left to a human
class SiteMcpProvider implements McpToolProviderInterface
{
    // Pages a list gives at most
    public const int LIMIT = 50;

    // Page::$title is VARCHAR(100)
    private const int TITLE_LENGTH = 100;

    public function __construct(
        private readonly PageRepository $pageRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly ConfigServiceInterface $configService,
        private readonly Security $security,
        private readonly ClockInterface $clock,
        private readonly PageSlugs $pageSlugs,
        private readonly PagePublicUrlResolver $pagePublicUrlResolver,
        private readonly BlockTextCollector $blockTextCollector,
    ) {
    }

    public function getMcpKey(): string
    {
        return 'site';
    }

    public function getMcpInstructions(): string
    {
        return 'Pages are the content of the site, made of blocks, read here in the language the site is written in. A page added by an agent is a draft, unpublished: a human composes its blocks and publishes it from the back office.';
    }

    public function getMcpTools(): iterable
    {
        yield new McpTool('site_page_list', 'Pages', 'The pages whose title holds the search words, every page without them, drafts included, the latest modified first, with their public url once published.', $this->list(...));
        yield new McpTool('site_page_get', 'Page', 'A page: title, SEO description, status, public url once published, and the texts of its blocks.', $this->get(...));
        yield new McpTool('site_page_create', 'Add a draft page', 'Adds an unpublished page with its title and an optional SEO description, without blocks.', $this->create(...), true);
        yield new McpTool('site_page_edit', 'Write a page', 'Changes only the title or SEO description given of a page; a new title renames its slug as the back office does, a published page redirecting its old url to the new one.', $this->edit(...), true);
    }

    // The pages whose title holds the words, every one without them, the trash left aside
    /**
     * @param ?string $search words looked for in the titles
     *
     * @return list<array{id: ?int, title: ?string, slug: ?string, published: bool, membersOnly: bool, url: ?string}>
     */
    public function list(?string $search = null): array
    {
        $this->guard();
        $builder = $this->pageRepository->createQueryBuilder('p')
            ->andWhere('p.isDeleted = false')
            ->orderBy('p.modification', \SortDirection::Descending)
            ->setMaxResults(self::LIMIT);
        if ('' !== trim((string) $search)) {
            $builder->andWhere('p.title LIKE :words')->setParameter('words', '%' . addcslashes(trim((string) $search), '%_') . '%');
        }

        return array_map($this->summary(...), $builder->getQuery()->getResult());
    }

    // A page with its description and the texts of its blocks, slots included
    /**
     * @param int $page id of the page
     *
     * @return array{id: ?int, title: ?string, slug: ?string, published: bool, membersOnly: bool, url: ?string, description: ?string, blocks: list<array{block: string, field: string, text: string}>}
     */
    public function get(int $page): array
    {
        $this->guard();
        $found = $this->pageRepository->findOneByIdWithBlocks($page);
        if (!$found instanceof Page || $found->isDeleted()) {
            throw new ToolCallException(sprintf('No page %d.', $page));
        }

        $blocks = array_map(
            static fn (array $row): array => ['block' => $row['label'], 'field' => $row['field'], 'text' => $row['source']],
            $this->blockTextCollector->collect($found->getBlocks(), (string) $found->getTitle()),
        );

        return [...$this->summary($found), 'description' => $found->getSummarySocialNetwork(), 'blocks' => $blocks];
    }

    // A draft page added as the back office adds one, unpublished and members only when the site says so, its slug made from its title, kept unique and taken back from any redirect left on it
    /**
     * @param string  $title       title of the page
     * @param ?string $description its SEO description, also shown when the page is shared
     *
     * @return array{id: ?int, slug: ?string}
     */
    public function create(string $title, ?string $description = null): array
    {
        $this->guard();
        $title = mb_substr(trim($title), 0, self::TITLE_LENGTH);
        if ('' === $title) {
            throw new ToolCallException('The title is empty.');
        }
        $now = $this->clock->now();
        $user = $this->security->getUser();
        $page = new Page()
            ->setTitle($title)
            ->setSlug($title)
            ->setSummarySocialNetwork('' === trim((string) $description) ? null : trim((string) $description))
            ->setIsMembersOnly($this->configService->getBool($this->configService->get('site-pages-members-only')))
            ->setCreation($now)
            ->setModification($now)
            ->setUser($user instanceof UserInterface ? $user : null);
        $this->pageSlugs->prepareNew($this->entityManager, $page);
        if ('' === $page->getSlug()) {
            throw new ToolCallException('The title gives no slug.');
        }
        $this->entityManager->persist($page);
        $this->entityManager->flush();

        return ['id' => $page->getId(), 'slug' => $page->getSlug()];
    }

    // A page's title or description changed, only those given, an empty description clearing it - a new title moving the slug and redirecting a published page's old urls, as the back office does
    /**
     * @param int     $page        id of the page
     * @param ?string $title       new title
     * @param ?string $description new SEO description, empty to clear it
     *
     * @return array{id: ?int, slug: ?string}
     */
    public function edit(int $page, ?string $title = null, ?string $description = null): array
    {
        $this->guard();
        $found = $this->pageRepository->find($page);
        if (!$found instanceof Page || $found->isDeleted()) {
            throw new ToolCallException(sprintf('No page %d.', $page));
        }
        if ('' !== trim((string) $title)) {
            $found->setTitle(mb_substr(trim((string) $title), 0, self::TITLE_LENGTH));
        }
        if (null !== $description) {
            $found->setSummarySocialNetwork('' === trim($description) ? null : trim($description));
        }
        $user = $this->security->getUser();
        if ($user instanceof UserInterface) {
            $found->setUser($user);
        }
        $this->pageSlugs->prepareUpdate($this->entityManager, $found);
        if ('' === $found->getSlug()) {
            throw new ToolCallException('The title gives no slug.');
        }
        $found->setModification($this->clock->now());
        $this->entityManager->flush();

        return ['id' => $found->getId(), 'slug' => $found->getSlug()];
    }

    // The pages are the editor's business, as on the back office
    private function guard(): void
    {
        if (!$this->security->isGranted((string) $this->configService->get('site-role-editor'))) {
            throw new ToolCallException('This account may not work on the pages.');
        }
    }

    // A page in a list, its public url only once it is published
    /** @return array{id: ?int, title: ?string, slug: ?string, published: bool, membersOnly: bool, url: ?string} */
    private function summary(Page $page): array
    {
        return [
            'id' => $page->getId(),
            'title' => $page->getTitle(),
            'slug' => $page->getSlug(),
            'published' => $page->isPublished(),
            'membersOnly' => $page->isMembersOnly(),
            'url' => $page->isPublished() ? $this->pagePublicUrlResolver->resolve($page) : null,
        ];
    }
}
