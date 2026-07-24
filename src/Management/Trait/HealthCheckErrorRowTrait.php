<?php
/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */
namespace c975L\SiteBundle\Management\Trait;

use c975L\ConfigBundle\Entity\HealthCheckResult;

// Builds the "the check itself blew up" row (network/API failure, not a check result) shared by every HealthCheckProviderInterface implementation that wraps a client call in a try/catch - relies on the using class's own $this->translator, same as AbstractW3cValidationHealthCheckProvider's equivalent inline block
trait HealthCheckErrorRowTrait
{
    // Takes the exception message rather than the \Throwable itself - ContentQualityHealthCheckProvider defers row-building past its catch block (analyzePages() only keeps the message, buildRow() turns it into a row later), unlike the other providers which build the row right in the catch block
    private function errorRow(string $url, ?string $label, string $translationId, string $message, ?string $editUrl = null): array
    {
        return [
            'url' => $url,
            'label' => $label,
            'status' => HealthCheckResult::STATUS_ERROR,
            'summary' => $this->translator->trans($translationId, ['%message%' => $message], 'site'),
            'details' => ['error' => $message],
            'editUrl' => $editUrl,
        ];
    }
}
