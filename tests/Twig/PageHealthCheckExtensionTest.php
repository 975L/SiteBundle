<?php
/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\SiteBundle\Tests\Twig;

use c975L\ConfigBundle\Entity\HealthCheckResult;
use c975L\ConfigBundle\Management\HealthCheckAdviceBuilder;
use c975L\ConfigBundle\Repository\HealthCheckResultRepository;
use c975L\ConfigBundle\Service\ConfigServiceInterface;
use c975L\SiteBundle\Entity\Page;
use c975L\SiteBundle\Management\PageHealthCheckAdviceBuilder;
use c975L\SiteBundle\Service\PagePublicUrlResolver;
use c975L\SiteBundle\Tests\PagePublicUrlGeneratorTestTrait;
use c975L\SiteBundle\Twig\PageHealthCheckExtension;
use PHPUnit\Framework\TestCase;
use Symfony\Contracts\Translation\TranslatorInterface;

class PageHealthCheckExtensionTest extends TestCase
{
    use PagePublicUrlGeneratorTestTrait;

    private function createUrlResolver(?string $siteUrl = 'https://example.com'): PagePublicUrlResolver
    {
        $configService = $this->createStub(ConfigServiceInterface::class);
        $configService->method('get')->willReturn($siteUrl);

        return new PagePublicUrlResolver($configService, $this->createUrlGenerator());
    }

    private function createAdviceBuilder(): HealthCheckAdviceBuilder
    {
        $translator = $this->createStub(TranslatorInterface::class);
        $translator->method('trans')->willReturnArgument(0);

        return new HealthCheckAdviceBuilder([new PageHealthCheckAdviceBuilder($translator)]);
    }

    private function createPage(string $slug): Page
    {
        $page = new Page();
        $page->setSlug($slug);
        $page->setTitle($slug);

        return $page;
    }

    public function testGetPanelReturnsEmptyArraysWithoutASiteUrl(): void
    {
        $extension = new PageHealthCheckExtension(
            $this->createUrlResolver(null),
            $this->createStub(HealthCheckResultRepository::class),
            $this->createAdviceBuilder(),
        );

        $this->assertSame(['results' => [], 'advice' => []], $extension->getPanel($this->createPage('home')));
    }

    public function testGetPanelFetchesResultsForThePageOwnUrlAndBuildsAdvice(): void
    {
        $result = (new HealthCheckResult())
            ->setKind('content-quality')
            ->setUrl('https://example.com/')
            ->setStatus(HealthCheckResult::STATUS_WARNING)
            ->setSummary('summary')
            ->setDetails(['hasDescription' => false, 'hasH1' => true, 'imagesWithoutAlt' => 0, 'brokenLinks' => []]);

        $repository = $this->createMock(HealthCheckResultRepository::class);
        $repository->expects($this->once())->method('findLatestByUrl')->with('https://example.com/')->willReturn([$result]);

        $extension = new PageHealthCheckExtension($this->createUrlResolver(), $repository, $this->createAdviceBuilder());

        $panel = $extension->getPanel($this->createPage('home'));

        $this->assertSame([$result], $panel['results']);
        $this->assertSame(['content-quality' => [['text' => 'label.health_check_advice_no_description', 'url' => null]]], $panel['advice']);
    }

    public function testGetPanelDropsTheSiteWideSecurityHeadersResult(): void
    {
        // Always stored under the homepage's own url (see SecurityHeadersHealthCheckProvider) - already shown in ConfigBundle's dashboard "Site" section, so it'd be redundant here
        $securityHeaders = (new HealthCheckResult())
            ->setKind('security-headers')
            ->setUrl('https://example.com/')
            ->setStatus(HealthCheckResult::STATUS_OK)
            ->setSummary('summary')
            ->setDetails(['missing' => [], 'headers' => []]);
        $contentQuality = (new HealthCheckResult())
            ->setKind('content-quality')
            ->setUrl('https://example.com/')
            ->setStatus(HealthCheckResult::STATUS_OK)
            ->setSummary('summary')
            ->setDetails(['hasDescription' => true, 'hasH1' => true, 'imagesWithoutAlt' => 0, 'brokenLinks' => []]);

        $repository = $this->createStub(HealthCheckResultRepository::class);
        $repository->method('findLatestByUrl')->willReturn([$securityHeaders, $contentQuality]);

        $extension = new PageHealthCheckExtension($this->createUrlResolver(), $repository, $this->createAdviceBuilder());

        $panel = $extension->getPanel($this->createPage('home'));

        $this->assertSame([$contentQuality], $panel['results']);
    }
}
