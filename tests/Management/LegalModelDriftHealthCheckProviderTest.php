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
use c975L\SiteBundle\Entity\Page;
use c975L\SiteBundle\Management\LegalModelDriftHealthCheckProvider;
use c975L\SiteBundle\Repository\PageRepository;
use c975L\SiteBundle\Service\LegalModelCatalog;
use c975L\SiteBundle\Service\LegalModelCustomizer;
use c975L\SiteBundle\Service\PagePublicUrlResolver;
use c975L\SiteBundle\Tests\PagePublicUrlGeneratorTestTrait;
use c975L\UiBundle\Entity\Block;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class LegalModelDriftHealthCheckProviderTest extends TestCase
{
    use PagePublicUrlGeneratorTestTrait;

    private function createPage(string $slug): Page
    {
        $page = new Page();
        $page->setSlug($slug);
        $page->setTitle('Legal notice');

        return $page;
    }

    private function createBlock(array $data): Block
    {
        $block = new Block();
        $block->setKind('legal_model');
        $block->setData($data);

        return $block;
    }

    private function createPageRepository(array $pairs): PageRepository
    {
        $repository = $this->createStub(PageRepository::class);
        $repository->method('findWithLegalModelBlocks')->willReturn($pairs);

        return $repository;
    }

    private function createCustomizer(array $drifted): LegalModelCustomizer
    {
        $customizer = $this->createStub(LegalModelCustomizer::class);
        $customizer->method('drifted')->willReturn($drifted);

        return $customizer;
    }

    private function createUrlResolver(?string $siteUrl = 'https://example.com'): PagePublicUrlResolver
    {
        $configService = $this->createStub(ConfigServiceInterface::class);
        $configService->method('get')->willReturn($siteUrl);

        return new PagePublicUrlResolver($configService, $this->createUrlGenerator());
    }

    // Appends the parameters rather than substituting them like the other provider tests do: the summary is
    // built from a key whose own text lives in the XLF files, so a strtr() stub would swallow what it carries
    private function createTranslator(): TranslatorInterface
    {
        $translator = $this->createStub(TranslatorInterface::class);
        $translator->method('trans')->willReturnCallback(
            fn (string $id, array $parameters = []) => trim($id . ' ' . implode(' ', $parameters))
        );

        return $translator;
    }

    private function createProvider(array $pairs, array $drifted, ?string $siteUrl = 'https://example.com'): LegalModelDriftHealthCheckProvider
    {
        $urlGenerator = $this->createStub(UrlGeneratorInterface::class);
        $urlGenerator->method('generate')->willReturn('/management/legal-model/1/customize');

        return new LegalModelDriftHealthCheckProvider(
            $this->createPageRepository($pairs),
            $this->createCustomizer($drifted),
            new LegalModelCatalog(),
            $this->createUrlResolver($siteUrl),
            $urlGenerator,
            $this->createTranslator(),
        );
    }

    public function testGetKindReturnsLegalModel(): void
    {
        $this->assertSame('legal_model', $this->createProvider([], [])->getKind());
    }

    public function testRunChecksReturnsNothingWithoutAnyLegalModelBlock(): void
    {
        $this->assertSame([], $this->createProvider([], [])->runChecks());
    }

    // The common case: a site that customized nothing must not clutter the health check table
    public function testRunChecksReturnsNothingWhenNoSectionDrifted(): void
    {
        $pairs = [['page' => $this->createPage('legal-notice'), 'block' => $this->createBlock(['model' => 'france/legal-notice'])]];

        $this->assertSame([], $this->createProvider($pairs, [])->runChecks());
    }

    // A block whose model is not one the bundle ships is skipped rather than reported against a template path
    public function testRunChecksSkipsAnUnknownModel(): void
    {
        $pairs = [['page' => $this->createPage('legal-notice'), 'block' => $this->createBlock(['model' => 'elsewhere/invented'])]];
        $drifted = ['one' => ['id' => 'one', 'title' => 'One', 'html' => '', 'hash' => 'abc', 'level' => 2]];

        $this->assertSame([], $this->createProvider($pairs, $drifted)->runChecks());
    }

    public function testRunChecksSkipsAPageWithNoPublicUrl(): void
    {
        $pairs = [['page' => $this->createPage('legal-notice'), 'block' => $this->createBlock(['model' => 'france/legal-notice'])]];
        $drifted = ['one' => ['id' => 'one', 'title' => 'One', 'html' => '', 'hash' => 'abc', 'level' => 2]];

        $this->assertSame([], $this->createProvider($pairs, $drifted, null)->runChecks());
    }

    // Reported as ok on purpose: it feeds neither the dashboard alerts nor the digest email
    public function testRunChecksReportsDriftAsOkAndNamesTheSections(): void
    {
        $pairs = [['page' => $this->createPage('legal-notice'), 'block' => $this->createBlock(['model' => 'france/legal-notice'])]];
        $drifted = [
            'publisher' => ['id' => 'publisher', 'title' => 'Publisher', 'html' => '', 'hash' => 'abc', 'level' => 2],
            'host' => ['id' => 'host', 'title' => 'Host', 'html' => '', 'hash' => 'def', 'level' => 2],
        ];

        $result = $this->createProvider($pairs, $drifted)->runChecks()[0];

        $this->assertSame(HealthCheckResult::STATUS_OK, $result['status']);
        $this->assertSame('Legal notice', $result['label']);
        $this->assertSame('https://example.com/pages/legal-notice', $result['url']);
        $this->assertStringContainsString('Publisher · Host', $result['summary']);
        $this->assertStringContainsString('label.legal_notice', $result['summary']);
        $this->assertSame('/management/legal-model/1/customize', $result['editUrl']);
    }

    // A drifted unit with no heading of its own is named by its identifier rather than by an empty string
    public function testRunChecksNamesAHeadinglessSectionByItsIdentifier(): void
    {
        $pairs = [['page' => $this->createPage('legal-notice'), 'block' => $this->createBlock(['model' => 'france/legal-notice'])]];
        $drifted = ['intro' => ['id' => 'intro', 'title' => '', 'html' => '', 'hash' => 'abc', 'level' => 1]];

        $result = $this->createProvider($pairs, $drifted)->runChecks()[0];

        $this->assertStringContainsString('intro', $result['summary']);
    }
}
