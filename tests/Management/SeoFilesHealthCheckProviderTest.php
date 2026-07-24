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
use c975L\SiteBundle\Management\SeoFilesHealthCheckProvider;
use c975L\SiteBundle\Service\SeoFilesClient;
use PHPUnit\Framework\TestCase;
use Symfony\Contracts\Translation\TranslatorInterface;

class SeoFilesHealthCheckProviderTest extends TestCase
{
    private const VALID_SITEMAP = '<?xml version="1.0"?><urlset><url><loc>https://example.com/</loc></url></urlset>';
    private const VALID_SITEMAP_INDEX = '<?xml version="1.0"?><sitemapindex><sitemap><loc>https://example.com/sitemap-page.xml</loc></sitemap><sitemap><loc>https://example.com/sitemap-book.xml</loc></sitemap></sitemapindex>';
    private const EMPTY_SITEMAP_INDEX = '<?xml version="1.0"?><sitemapindex></sitemapindex>';
    private const OPEN_ROBOTS = "User-agent: *\nDisallow:\n";
    private const BLOCKING_ROBOTS = "User-agent: *\nDisallow: /\n";
    private const PARTIAL_DISALLOW_ROBOTS = "User-agent: *\nDisallow: /admin/\n";
    private const SCOPED_DISALLOW_ROBOTS = "User-agent: SomeBot\nDisallow: /\n\nUser-agent: *\nDisallow:\n";

    private function createConfigService(?string $siteUrl): ConfigServiceInterface
    {
        $configService = $this->createStub(ConfigServiceInterface::class);
        $configService->method('get')->willReturn($siteUrl);

        return $configService;
    }

    private function createClient(array $responses): SeoFilesClient
    {
        $client = $this->createStub(SeoFilesClient::class);
        $client->method('fetch')->willReturnMap($responses);

        return $client;
    }

    private function createTranslator(): TranslatorInterface
    {
        $translator = $this->createStub(TranslatorInterface::class);
        $translator->method('trans')->willReturnCallback(
            fn (string $id, array $parameters = []) => strtr($id, $parameters)
        );

        return $translator;
    }

    public function testGetKindReturnsSeoFiles(): void
    {
        $provider = new SeoFilesHealthCheckProvider($this->createConfigService(null), $this->createClient([]), $this->createTranslator());

        $this->assertSame('seo-files', $provider->getKind());
    }

    public function testRunChecksReturnsEmptyArrayWithoutASiteUrl(): void
    {
        $provider = new SeoFilesHealthCheckProvider($this->createConfigService(null), $this->createClient([]), $this->createTranslator());

        $this->assertSame([], $provider->runChecks());
    }

    public function testRunChecksReturnsOneRowEachForRobotsAndSitemapWhenBothAreFine(): void
    {
        $client = $this->createClient([
            ['https://example.com/robots.txt', ['statusCode' => 200, 'content' => self::OPEN_ROBOTS]],
            ['https://example.com/sitemap-site.xml', ['statusCode' => 200, 'content' => self::VALID_SITEMAP]],
            ['https://example.com/sitemap-index.xml', ['statusCode' => 404, 'content' => '']],
        ]);

        $provider = new SeoFilesHealthCheckProvider($this->createConfigService('https://example.com'), $client, $this->createTranslator());
        $results = $provider->runChecks();

        $this->assertCount(2, $results);
        $this->assertSame(HealthCheckResult::STATUS_OK, $results[0]['status']);
        $this->assertSame('https://example.com/robots.txt', $results[0]['url']);
        $this->assertSame(HealthCheckResult::STATUS_OK, $results[1]['status']);
        $this->assertSame('https://example.com/sitemap-site.xml', $results[1]['url']);
    }

    public function testRunChecksStatusIsErrorWhenRobotsIsMissing(): void
    {
        $client = $this->createClient([
            ['https://example.com/robots.txt', ['statusCode' => 404, 'content' => '']],
            ['https://example.com/sitemap-site.xml', ['statusCode' => 200, 'content' => self::VALID_SITEMAP]],
            ['https://example.com/sitemap-index.xml', ['statusCode' => 404, 'content' => '']],
        ]);

        $provider = new SeoFilesHealthCheckProvider($this->createConfigService('https://example.com'), $client, $this->createTranslator());

        $this->assertSame(HealthCheckResult::STATUS_ERROR, $provider->runChecks()[0]['status']);
    }

    public function testRunChecksStatusIsErrorWhenRobotsIsEmpty(): void
    {
        $client = $this->createClient([
            ['https://example.com/robots.txt', ['statusCode' => 200, 'content' => '   ']],
            ['https://example.com/sitemap-site.xml', ['statusCode' => 200, 'content' => self::VALID_SITEMAP]],
            ['https://example.com/sitemap-index.xml', ['statusCode' => 404, 'content' => '']],
        ]);

        $provider = new SeoFilesHealthCheckProvider($this->createConfigService('https://example.com'), $client, $this->createTranslator());

        $this->assertSame(HealthCheckResult::STATUS_ERROR, $provider->runChecks()[0]['status']);
    }

    public function testRunChecksStatusIsWarningWhenRobotsBlocksEverything(): void
    {
        $client = $this->createClient([
            ['https://example.com/robots.txt', ['statusCode' => 200, 'content' => self::BLOCKING_ROBOTS]],
            ['https://example.com/sitemap-site.xml', ['statusCode' => 200, 'content' => self::VALID_SITEMAP]],
            ['https://example.com/sitemap-index.xml', ['statusCode' => 404, 'content' => '']],
        ]);

        $provider = new SeoFilesHealthCheckProvider($this->createConfigService('https://example.com'), $client, $this->createTranslator());

        $this->assertSame(HealthCheckResult::STATUS_WARNING, $provider->runChecks()[0]['status']);
    }

    public function testRunChecksDoesNotFlagAPartialDisallow(): void
    {
        $client = $this->createClient([
            ['https://example.com/robots.txt', ['statusCode' => 200, 'content' => self::PARTIAL_DISALLOW_ROBOTS]],
            ['https://example.com/sitemap-site.xml', ['statusCode' => 200, 'content' => self::VALID_SITEMAP]],
            ['https://example.com/sitemap-index.xml', ['statusCode' => 404, 'content' => '']],
        ]);

        $provider = new SeoFilesHealthCheckProvider($this->createConfigService('https://example.com'), $client, $this->createTranslator());

        $this->assertSame(HealthCheckResult::STATUS_OK, $provider->runChecks()[0]['status']);
    }

    public function testRunChecksDoesNotFlagADisallowScopedToAnotherAgent(): void
    {
        $client = $this->createClient([
            ['https://example.com/robots.txt', ['statusCode' => 200, 'content' => self::SCOPED_DISALLOW_ROBOTS]],
            ['https://example.com/sitemap-site.xml', ['statusCode' => 200, 'content' => self::VALID_SITEMAP]],
            ['https://example.com/sitemap-index.xml', ['statusCode' => 404, 'content' => '']],
        ]);

        $provider = new SeoFilesHealthCheckProvider($this->createConfigService('https://example.com'), $client, $this->createTranslator());

        $this->assertSame(HealthCheckResult::STATUS_OK, $provider->runChecks()[0]['status']);
    }

    public function testRunChecksStatusIsErrorWhenSitemapIsMissing(): void
    {
        $client = $this->createClient([
            ['https://example.com/robots.txt', ['statusCode' => 200, 'content' => self::OPEN_ROBOTS]],
            ['https://example.com/sitemap-site.xml', ['statusCode' => 404, 'content' => '']],
            ['https://example.com/sitemap-index.xml', ['statusCode' => 404, 'content' => '']],
        ]);

        $provider = new SeoFilesHealthCheckProvider($this->createConfigService('https://example.com'), $client, $this->createTranslator());

        $this->assertSame(HealthCheckResult::STATUS_ERROR, $provider->runChecks()[1]['status']);
    }

    public function testRunChecksStatusIsErrorWhenSitemapIsNotValidXml(): void
    {
        $client = $this->createClient([
            ['https://example.com/robots.txt', ['statusCode' => 200, 'content' => self::OPEN_ROBOTS]],
            ['https://example.com/sitemap-site.xml', ['statusCode' => 200, 'content' => '<html>Not Found</html>']],
            ['https://example.com/sitemap-index.xml', ['statusCode' => 404, 'content' => '']],
        ]);

        $provider = new SeoFilesHealthCheckProvider($this->createConfigService('https://example.com'), $client, $this->createTranslator());

        $this->assertSame(HealthCheckResult::STATUS_ERROR, $provider->runChecks()[1]['status']);
    }

    public function testRunChecksReturnsAnErrorRowWhenTheCallFails(): void
    {
        $client = $this->createStub(SeoFilesClient::class);
        $client->method('fetch')->willThrowException(new \RuntimeException('Connection refused'));

        $provider = new SeoFilesHealthCheckProvider($this->createConfigService('https://example.com'), $client, $this->createTranslator());

        $results = $provider->runChecks();
        $this->assertSame(HealthCheckResult::STATUS_ERROR, $results[0]['status']);
        $this->assertSame(HealthCheckResult::STATUS_ERROR, $results[1]['status']);
    }

    public function testRunChecksAddsAnIndexRowPlusOneRowPerChildSitemapWhenAllAreFine(): void
    {
        $client = $this->createClient([
            ['https://example.com/robots.txt', ['statusCode' => 200, 'content' => self::OPEN_ROBOTS]],
            ['https://example.com/sitemap-site.xml', ['statusCode' => 200, 'content' => self::VALID_SITEMAP]],
            ['https://example.com/sitemap-index.xml', ['statusCode' => 200, 'content' => self::VALID_SITEMAP_INDEX]],
            ['https://example.com/sitemap-page.xml', ['statusCode' => 200, 'content' => self::VALID_SITEMAP]],
            ['https://example.com/sitemap-book.xml', ['statusCode' => 200, 'content' => self::VALID_SITEMAP]],
        ]);

        $provider = new SeoFilesHealthCheckProvider($this->createConfigService('https://example.com'), $client, $this->createTranslator());
        $results = $provider->runChecks();

        $this->assertCount(5, $results);
        $this->assertSame(HealthCheckResult::STATUS_OK, $results[2]['status']);
        $this->assertSame('https://example.com/sitemap-index.xml', $results[2]['url']);
        $this->assertSame(HealthCheckResult::STATUS_OK, $results[3]['status']);
        $this->assertSame('https://example.com/sitemap-page.xml', $results[3]['url']);
        $this->assertSame('sitemap-page.xml', $results[3]['label']);
        $this->assertSame(HealthCheckResult::STATUS_OK, $results[4]['status']);
        $this->assertSame('https://example.com/sitemap-book.xml', $results[4]['url']);
        $this->assertSame('sitemap-book.xml', $results[4]['label']);
    }

    public function testRunChecksDoesNotAddAnyRowWhenSitemapIndexIsMissing(): void
    {
        $client = $this->createClient([
            ['https://example.com/robots.txt', ['statusCode' => 200, 'content' => self::OPEN_ROBOTS]],
            ['https://example.com/sitemap-site.xml', ['statusCode' => 200, 'content' => self::VALID_SITEMAP]],
            ['https://example.com/sitemap-index.xml', ['statusCode' => 404, 'content' => '']],
        ]);

        $provider = new SeoFilesHealthCheckProvider($this->createConfigService('https://example.com'), $client, $this->createTranslator());

        $this->assertCount(2, $provider->runChecks());
    }

    public function testRunChecksStatusIsErrorWhenSitemapIndexIsNotValidXml(): void
    {
        $client = $this->createClient([
            ['https://example.com/robots.txt', ['statusCode' => 200, 'content' => self::OPEN_ROBOTS]],
            ['https://example.com/sitemap-site.xml', ['statusCode' => 200, 'content' => self::VALID_SITEMAP]],
            ['https://example.com/sitemap-index.xml', ['statusCode' => 200, 'content' => '<html>Not Found</html>']],
        ]);

        $provider = new SeoFilesHealthCheckProvider($this->createConfigService('https://example.com'), $client, $this->createTranslator());
        $results = $provider->runChecks();

        $this->assertCount(3, $results);
        $this->assertSame(HealthCheckResult::STATUS_ERROR, $results[2]['status']);
    }

    public function testRunChecksStatusIsWarningWhenSitemapIndexHasNoEntries(): void
    {
        $client = $this->createClient([
            ['https://example.com/robots.txt', ['statusCode' => 200, 'content' => self::OPEN_ROBOTS]],
            ['https://example.com/sitemap-site.xml', ['statusCode' => 200, 'content' => self::VALID_SITEMAP]],
            ['https://example.com/sitemap-index.xml', ['statusCode' => 200, 'content' => self::EMPTY_SITEMAP_INDEX]],
        ]);

        $provider = new SeoFilesHealthCheckProvider($this->createConfigService('https://example.com'), $client, $this->createTranslator());
        $results = $provider->runChecks();

        $this->assertCount(3, $results);
        $this->assertSame(HealthCheckResult::STATUS_WARNING, $results[2]['status']);
    }

    public function testRunChecksOnlyFlagsTheOneChildSitemapThatIsUnreachable(): void
    {
        $client = $this->createClient([
            ['https://example.com/robots.txt', ['statusCode' => 200, 'content' => self::OPEN_ROBOTS]],
            ['https://example.com/sitemap-site.xml', ['statusCode' => 200, 'content' => self::VALID_SITEMAP]],
            ['https://example.com/sitemap-index.xml', ['statusCode' => 200, 'content' => self::VALID_SITEMAP_INDEX]],
            ['https://example.com/sitemap-page.xml', ['statusCode' => 404, 'content' => '']],
            ['https://example.com/sitemap-book.xml', ['statusCode' => 200, 'content' => self::VALID_SITEMAP]],
        ]);

        $provider = new SeoFilesHealthCheckProvider($this->createConfigService('https://example.com'), $client, $this->createTranslator());
        $results = $provider->runChecks();

        $this->assertCount(5, $results);
        $this->assertSame(HealthCheckResult::STATUS_OK, $results[2]['status']);
        $this->assertSame(HealthCheckResult::STATUS_ERROR, $results[3]['status']);
        $this->assertSame('https://example.com/sitemap-page.xml', $results[3]['url']);
        $this->assertSame(HealthCheckResult::STATUS_OK, $results[4]['status']);
    }

    public function testRunChecksStatusIsErrorWhenAChildSitemapIsNotValidXml(): void
    {
        $client = $this->createClient([
            ['https://example.com/robots.txt', ['statusCode' => 200, 'content' => self::OPEN_ROBOTS]],
            ['https://example.com/sitemap-site.xml', ['statusCode' => 200, 'content' => self::VALID_SITEMAP]],
            ['https://example.com/sitemap-index.xml', ['statusCode' => 200, 'content' => self::VALID_SITEMAP_INDEX]],
            ['https://example.com/sitemap-page.xml', ['statusCode' => 200, 'content' => '<html>Not Found</html>']],
            ['https://example.com/sitemap-book.xml', ['statusCode' => 200, 'content' => self::VALID_SITEMAP]],
        ]);

        $provider = new SeoFilesHealthCheckProvider($this->createConfigService('https://example.com'), $client, $this->createTranslator());
        $results = $provider->runChecks();

        $this->assertSame(HealthCheckResult::STATUS_ERROR, $results[3]['status']);
    }
}
