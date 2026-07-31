<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\SiteBundle\Service;

// Translates between what the customization screen shows and the sparse delta stored in the block's data,
// and answers the one question the drift check asks: has the bundle rewritten a passage this client had
// already replaced?
//
// The screen shows the bundle's own wording, editable in place, rather than empty "override me" fields. What
// comes back is therefore compared against that wording (see LegalModelRenderer::comparable()) and only a
// real difference is stored - a client who opens the screen and saves it untouched stores nothing at all and
// keeps receiving the bundle's updates.
class LegalModelCustomizer
{
    // Units of a model per locale, resolved once - the screen needs them, and so does every override it stamps
    private array $units = [];

    public function __construct(
        private readonly LegalModelRenderer $renderer,
    ) {
    }

    public function units(string $model, string $locale): array
    {
        return $this->units[$model . '.' . $locale] ??= $this->renderer->units($model, $locale);
    }

    // One form row per unit the bundle ships, pre-filled with the text as it currently stands: the client's
    // own version where they wrote one, ours everywhere else
    public function toFormData(array $units, array $customization): array
    {
        $hidden = array_flip((array) ($customization['hidden'] ?? []));
        $positions = (array) ($customization['positions'] ?? []);
        $overrides = (array) ($customization['overrides'] ?? []);

        $rows = [];
        foreach ($this->ordered($units, $positions) as $rank => $unit) {
            $rows[] = [
                'id' => $unit['id'],
                'hidden' => isset($hidden[$unit['id']]),
                'position' => $rank,
                'title' => (string) ($overrides[$unit['id']]['title'] ?? $unit['title']),
                'content' => (string) ($overrides[$unit['id']]['content'] ?? $unit['html']),
            ];
        }

        return [
            'units' => $rows,
            'extra' => array_values(array_map(
                static fn (array $section): array => [
                    'id' => (string) ($section['id'] ?? ''),
                    'parent' => (string) ($section['parent'] ?? ''),
                    'title' => (string) ($section['title'] ?? ''),
                    'content' => (string) ($section['content'] ?? ''),
                ],
                (array) ($customization['extra'] ?? []),
            )),
        ];
    }

    // The submitted screen boiled back down to a delta: only what actually differs from the bundle is kept
    public function toCustomization(array $formData, array $units, string $locale): array
    {
        $byId = array_combine(array_column($units, 'id'), $units);

        $hidden = [];
        $overrides = [];
        $submitted = [];

        foreach ((array) ($formData['units'] ?? []) as $row) {
            $id = (string) ($row['id'] ?? '');
            // A row whose unit vanished from the model between display and submit is dropped rather than
            // stored against an identifier nothing renders any more
            if ('' === $id || !isset($byId[$id])) {
                continue;
            }

            $submitted[$id] = (int) ($row['position'] ?? 0);

            if ($row['hidden'] ?? false) {
                $hidden[] = $id;
            }

            $title = trim((string) ($row['title'] ?? ''));
            $content = trim((string) ($row['content'] ?? ''));
            $unit = $byId[$id];

            if ($this->matchesBundle($title, $content, $unit)) {
                continue;
            }

            $overrides[$id] = [
                'title' => $title,
                'content' => $content,
                // Fingerprint of the bundle text this override replaces, and the locale it was taken from -
                // the drift check compares against exactly that, see LegalModelRenderer::hash()
                'baseHash' => (string) $unit['hash'],
                'baseLocale' => $locale,
            ];
        }

        $extra = [];
        foreach ((array) ($formData['extra'] ?? []) as $section) {
            $title = trim((string) ($section['title'] ?? ''));
            $content = trim((string) ($section['content'] ?? ''));
            if ('' === $title && '' === $content) {
                continue;
            }

            $extra[] = [
                'id' => '' !== (string) ($section['id'] ?? '') ? (string) $section['id'] : uniqid('extra-'),
                'parent' => (string) ($section['parent'] ?? ''),
                'title' => $title,
                'content' => $content,
            ];
        }

        return array_filter([
            'hidden' => $hidden,
            'positions' => $this->reorderedScopes($units, $submitted),
            'overrides' => $overrides,
            'extra' => $extra,
        ]);
    }

    // The block's data with a freshly computed delta merged in. An empty delta drops the key entirely
    // rather than storing "customization": [] - saving the screen without changing anything has to leave the
    // block byte-for-byte as it was, or "untouched" would not be a no-op
    public function apply(array $data, array $customization): array
    {
        if ([] === $customization) {
            unset($data['customization']);

            return $data;
        }

        return [...$data, 'customization' => $customization];
    }

    // Overrides whose bundle text has changed since they were written, as [id => the model's current unit].
    // Nothing is applied automatically: only the client can decide whether a reworded clause of ours belongs
    // in a text they took responsibility for
    public function drifted(string $model, array $customization): array
    {
        $drifted = [];

        foreach ((array) ($customization['overrides'] ?? []) as $id => $override) {
            $units = $this->units($model, (string) ($override['baseLocale'] ?? LegalModelCatalog::FALLBACK_LOCALE));
            $current = array_combine(array_column($units, 'id'), $units)[$id] ?? null;

            // A unit that disappeared from the model is not drift - the override simply has nothing left to
            // apply to, and the renderer ignores it
            if (null === $current || ($override['baseHash'] ?? '') === $current['hash']) {
                continue;
            }

            $drifted[$id] = $current;
        }

        return $drifted;
    }

    // True when what came back is, to the character that matters, still the bundle's own text
    private function matchesBundle(string $title, string $content, array $unit): bool
    {
        return $title === trim((string) $unit['title'])
            && LegalModelRenderer::comparable($content) === LegalModelRenderer::comparable((string) $unit['html']);
    }

    // The units as the screen lists them, and as toFormData() indexes its rows: document order, unless the
    // client already dragged some around. The screen nests them back into a tree from this same list, so both
    // must walk it identically
    public function ordered(array $units, array $positions): array
    {
        if ([] === $positions) {
            return $units;
        }

        $ranked = [];
        foreach ($units as $rank => $unit) {
            $ranked[] = [
                'scope' => $this->scopeOf($unit['id']),
                'position' => $positions[$unit['id']] ?? $rank,
                'rank' => $rank,
                'unit' => $unit,
            ];
        }

        // Sorted within a scope only: a sub-section never climbs out of the section it documents
        usort($ranked, static fn (array $a, array $b): int => [$a['scope'], $a['position'], $a['rank']] <=> [$b['scope'], $b['position'], $b['rank']]);

        return $this->interleave(array_column($ranked, 'unit'));
    }

    // Sub-units follow the section they belong to, whatever the sort above did to the flat list
    private function interleave(array $units): array
    {
        $ordered = [];
        foreach ($units as $unit) {
            if ('' !== $this->scopeOf($unit['id'])) {
                continue;
            }

            $ordered[] = $unit;
            foreach ($units as $candidate) {
                if ($unit['id'] === $this->scopeOf($candidate['id'])) {
                    $ordered[] = $candidate;
                }
            }
        }

        return $ordered;
    }

    // Positions are stored per scope, and only for a scope the client actually reordered - dragging nothing
    // must leave the delta empty. When one did move, every unit of that scope is pinned, so the stored order
    // is total rather than half-natural
    private function reorderedScopes(array $units, array $submitted): array
    {
        $scopes = [];
        foreach ($units as $rank => $unit) {
            if (isset($submitted[$unit['id']])) {
                $scopes[$this->scopeOf($unit['id'])][] = ['id' => $unit['id'], 'rank' => $rank, 'position' => $submitted[$unit['id']]];
            }
        }

        $positions = [];
        foreach ($scopes as $rows) {
            $byPosition = $rows;
            usort($byPosition, static fn (array $a, array $b): int => [$a['position'], $a['rank']] <=> [$b['position'], $b['rank']]);

            if (array_column($byPosition, 'id') === array_column($rows, 'id')) {
                continue;
            }

            foreach ($byPosition as $index => $row) {
                $positions[$row['id']] = $index;
            }
        }

        return $positions;
    }

    // A sub-section's identifier is dotted under its section's ("regulations.dpo"); everything else is top level
    public function scopeOf(string $id): string
    {
        $dot = strpos($id, '.');

        return false === $dot ? '' : substr($id, 0, $dot);
    }
}
