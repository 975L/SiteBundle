<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\SiteBundle\Service;

use c975L\ConfigBundle\Service\ConfigServiceInterface;

// The site's identity inside a legal model. The models read it through the legal_var() Twig function, which
// normally resolves it on the spot - so a model rendered any way at all, a block or a plain {% include %} in
// an app's own template, reads correctly with no post-processing.
//
// Under withMarkers() it writes %site-name% instead. Only unit extraction needs that (see
// LegalModelRenderer::units()): the customization screen has to show the marker for the client to keep using
// it in their own wording, and a unit's fingerprint must not move just because the site was renamed.
// LegalModelRenderer::render() then substitutes what is left over the finished document, which is what keeps
// a section the client rewrote resolving its config values instead of freezing them.
//
// Deliberately NOT a general "any config slug" mechanism: only the keys below are substitutable, so a stored
// %some-other-key% stays inert text and no back-office text can read a config value the models never showed.
class LegalModelPlaceholders
{
    // Value printed as-is, HTML-escaped
    private const ESCAPE = 'escape';
    // Value is rich text authored in ConfigBundle, printed unescaped
    private const RAW = 'raw';
    // Same, with newlines turned into <br> (multi-line postal addresses)
    private const RAW_NL2BR = 'raw_nl2br';

    private const VARS = [
        'site-name' => self::ESCAPE,
        'site-owner' => self::RAW,
        'site-director' => self::ESCAPE,
        'site-director-location' => self::ESCAPE,
        'site-contact-email' => self::ESCAPE,
        'site-contact-phone' => self::ESCAPE,
        'site-producer' => self::RAW,
        'site-hosting-provider' => self::RAW_NL2BR,
        'site-dpo' => self::ESCAPE,
    ];

    // Set only for the duration of one withMarkers() call, which never spans a request boundary
    private bool $markerMode = false;

    public function __construct(
        private readonly ConfigServiceInterface $configService,
    ) {
    }

    // What legal_var() writes for a given slug - an unknown one yields nothing rather than leaking its name
    public function value(string $slug): string
    {
        if (!isset(self::VARS[$slug])) {
            return '';
        }

        return $this->markerMode
            ? '%' . $slug . '%'
            : $this->format((string) $this->configService->get($slug), self::VARS[$slug]);
    }

    // Runs $render with the models writing markers rather than values
    public function withMarkers(callable $render): string
    {
        $this->markerMode = true;

        try {
            return $render();
        } finally {
            $this->markerMode = false;
        }
    }

    // Every substitutable slug, listed to the client on the customization screen
    public function slugs(): array
    {
        return array_keys(self::VARS);
    }

    // Replaces every known marker by its current config value. Runs on the finished document, bundle text and
    // client-authored overrides alike
    public function substitute(string $html): string
    {
        $map = [];
        foreach (self::VARS as $slug => $format) {
            $map['%' . $slug . '%'] = $this->format((string) $this->configService->get($slug), $format);
        }

        return strtr($html, $map);
    }

    private function format(string $value, string $format): string
    {
        return match ($format) {
            self::RAW => $value,
            self::RAW_NL2BR => nl2br($value),
            default => htmlspecialchars($value, \ENT_QUOTES | \ENT_SUBSTITUTE, 'UTF-8'),
        };
    }
}
