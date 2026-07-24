<?php
/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */
namespace c975L\SiteBundle\Management;

use Symfony\Contracts\HttpClient\ResponseInterface;

// Validates each published page's HTML markup (W3C Nu Html Checker) - unrelated to Lighthouse's own "best-practices" category, which never checks actual spec conformance. See AbstractW3cValidationHealthCheckProvider
class W3cHtmlHealthCheckProvider extends AbstractW3cValidationHealthCheckProvider
{
    public function getKind(): string
    {
        return 'w3c-html';
    }

    protected function request(string $url): ResponseInterface
    {
        return $this->w3cValidatorClient->requestHtml($url);
    }

    protected function read(ResponseInterface $response): array
    {
        return $this->w3cValidatorClient->readHtml($response);
    }

    protected function summaryTranslationId(): string
    {
        return 'label.health_check_summary_w3c_html';
    }

    protected function callFailedTranslationId(): string
    {
        return 'label.health_check_w3c_html_call_failed';
    }
}
