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
use c975L\ConfigBundle\Service\SiteUrlResolver;
use c975L\SiteBundle\Entity\Menu;
use c975L\SiteBundle\Entity\Page;
use c975L\SiteBundle\Management\TranslationHealthCheckProvider;
use c975L\SiteBundle\Repository\MenuRepository;
use c975L\SiteBundle\Repository\PageRepository;
use c975L\SiteBundle\Service\PageEditUrlResolver;
use c975L\SiteBundle\Service\PagePublicUrlResolver;
use c975L\SiteBundle\Service\PageTranslator;
use c975L\SiteBundle\Tests\PagePublicUrlGeneratorTestTrait;
use c975L\UiBundle\Entity\Block;
use c975L\UiBundle\Registry\BlockRegistry;
use c975L\UiBundle\Service\ContentTranslator;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGeneratorInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Contracts\Translation\TranslatorInterface;

class TranslationHealthCheckProviderTest extends TestCase
{
    use PagePublicUrlGeneratorTestTrait;

    private function createPage(): Page
    {
        $page = new Page();
        $page->setSlug('ateliers')->setTitle('Nos ateliers')->setIsPublished(true);
        new \ReflectionProperty(Page::class, 'id')->setValue($page, 12);

        $block = new Block();
        $block->setKind('text_section')->setData(['title' => 'Ce que nous faisons', 'content' => 'Un atelier par mois']);
        new \ReflectionProperty(Block::class, 'id')->setValue($block, 34);
        $page->addBlock($block);

        return $page;
    }

    /**
     * @param list<string>                              $locales
     * @param array<string, array<string, string|null>> $pageValues  locale => field => value
     * @param array<string, array<string, string|null>> $blockValues locale => field => value
     */
    private function createProvider(array $locales, array $pageValues = [], array $blockValues = [], array $menus = [], ?string $siteUrl = 'https://exemple.com'): TranslationHealthCheckProvider
    {
        $pageRepository = $this->createStub(PageRepository::class);
        $pageRepository->method('findAllOrdered')->willReturn([$this->createPage()]);

        $menuRepository = $this->createStub(MenuRepository::class);
        $menuRepository->method('findAll')->willReturn($menus);

        $contentTranslator = $this->createStub(ContentTranslator::class);
        $contentTranslator->method('getTranslatableLocales')->willReturn($locales);
        $contentTranslator->method('all')->willReturn($blockValues);

        $pageTranslator = $this->createStub(PageTranslator::class);
        $pageTranslator->method('all')->willReturn($pageValues);

        $blockRegistry = $this->createStub(BlockRegistry::class);
        $blockRegistry->method('getTranslatable')->willReturnCallback(
            static fn (string $kind): array => 'menu_link' === $kind ? ['label'] : ['title', 'content']
        );

        $configService = $this->createStub(ConfigServiceInterface::class);
        $configService->method('get')->willReturn($siteUrl);

        $translator = $this->createStub(TranslatorInterface::class);
        $translator->method('trans')->willReturnArgument(0);

        $siteUrlResolver = $this->createStub(SiteUrlResolver::class);
        $siteUrlResolver->method('siteRoot')->willReturn('https://exemple.com');

        return new TranslationHealthCheckProvider(
            $pageRepository,
            $menuRepository,
            $contentTranslator,
            $pageTranslator,
            $blockRegistry,
            new PagePublicUrlResolver($configService, $this->createUrlGenerator(), $this->createSiteLocales(['fr', 'en'], 'fr'), $pageTranslator),
            $this->createStub(PageEditUrlResolver::class),
            $siteUrlResolver,
            $this->createStub(AdminUrlGeneratorInterface::class),
            $translator,
        );
    }

    // A site declaring a single language has nothing to report: the dashboard shows no such row at all
    // Throws rather than skipping every page: the kind is exhaustive, so the empty list that would leave would tell HealthCheckRunner to clear every stored row of it
    public function testRunChecksThrowsWithoutASiteUrl(): void
    {
        $provider = $this->createProvider(['en'], siteUrl: null);

        $this->expectException(\RuntimeException::class);

        $provider->runChecks();
    }

    public function testASiteWithOneLanguageReportsNothing(): void
    {
        $this->assertSame([], $this->createProvider([])->runChecks());
    }

    // None of the declared languages was ever started: that is an error, not a warning
    public function testAPageTranslatedInNoLanguageIsAnError(): void
    {
        $results = $this->createProvider(['en'])->runChecks();

        $this->assertCount(1, $results);
        $this->assertSame(HealthCheckResult::STATUS_ERROR, $results[0]['status']);
        // The page's own title, plus the two texts of its block
        $this->assertSame(3, $results[0]['details']['texts']);
        $this->assertSame(['en' => 3], $results[0]['details']['missing']);
    }

    public function testAFullyTranslatedPageIsGreen(): void
    {
        $results = $this->createProvider(
            ['en'],
            ['en' => ['title' => 'Our workshops']],
            ['en' => ['title' => 'What we do', 'content' => 'One a month']],
        )->runChecks();

        $this->assertSame(HealthCheckResult::STATUS_OK, $results[0]['status']);
        $this->assertSame([], $results[0]['details']['missing']);
    }

    // A language started then left halfway shows up, without passing for a page never translated at all
    public function testAHalfTranslatedPageIsAWarningWhenAnotherLanguageIsDone(): void
    {
        $results = $this->createProvider(
            ['en', 'es'],
            ['en' => ['title' => 'Our workshops'], 'es' => ['title' => 'Nuestros talleres']],
            ['en' => ['title' => 'What we do', 'content' => 'One a month']],
        )->runChecks();

        // English is complete, Spanish only has the page's title: a warning, not a page never translated
        $this->assertSame(HealthCheckResult::STATUS_WARNING, $results[0]['status']);
        $this->assertSame(['es' => 2], $results[0]['details']['missing']);
    }

    // A menu gets a row of its own, named after its location and pointed at the site root: it is read on every page and has no url of its own
    public function testAMenuIsCheckedOnItsOwnRow(): void
    {
        $menu = new Menu();
        $menu->setLocation(Menu::LOCATION_NAVBAR);
        new \ReflectionProperty(Menu::class, 'id')->setValue($menu, 7);

        $link = new Block();
        $link->setKind('menu_link')->setData(['target' => 'page:12', 'label' => 'Nos ateliers']);
        new \ReflectionProperty(Block::class, 'id')->setValue($link, 56);
        $menu->addBlock($link);

        $results = $this->createProvider(['en'], menus: [$menu])->runChecks();

        $this->assertCount(2, $results);
        $this->assertSame('https://exemple.com#navbar', $results[1]['url']);
        $this->assertSame(HealthCheckResult::STATUS_ERROR, $results[1]['status']);
        $this->assertSame(1, $results[1]['details']['texts']);
    }
}
