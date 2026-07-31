<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\SiteBundle\Service;

use Twig\Environment;

// Renders a legal model, then applies the block's customization on top of it.
//
// The bundle's template stays the source of truth: what the client never touched keeps arriving with every
// composer update, and keeps its live %config% markers. Only what they explicitly hid, rewrote, moved or
// added is stored - a sparse delta, never a copy of the whole document. A block with no delta at all skips
// the DOM pass entirely and renders byte-for-byte what it rendered before this existed.
//
// Units are identified by the "data-legal-id" attribute the models carry, slugified from their English
// headings so an identifier means the same thing in all three locales. Two levels: a <section> (and any
// tagged top-level <div>/<p>), and, inside it, an <h3> with everything up to the next one.
class LegalModelRenderer
{
    public function __construct(
        private readonly Environment $twig,
        private readonly LegalModelPlaceholders $placeholders,
        private readonly LegalModelCatalog $catalog,
    ) {
    }

    // Final HTML for a block: the model for this locale, the delta applied, the %config% markers resolved
    public function render(string $model, ?string $latestUpdate, array $customization, string $locale): string
    {
        $html = $this->renderModel($model, $latestUpdate, $locale);

        if ('' !== $html && $this->isCustomized($customization)) {
            $html = $this->customize($html, $customization);
        }

        return $this->placeholders->substitute($html);
    }

    // The model's units as the bundle currently ships them, in document order - what the customization screen
    // lists and what the drift check hashes. Markers left unresolved on purpose: a hash must not move just
    // because the site was renamed.
    public function units(string $model, string $locale): array
    {
        // Markers, not values: the screen shows them to the client and the hashes must not move with the config
        $root = $this->parse($this->placeholders->withMarkers(fn (): string => $this->renderModel($model, null, $locale)));

        if (null === $root) {
            return [];
        }

        $units = [];
        foreach ($this->taggedChildren($root) as $element) {
            $isSection = 'SECTION' === $element->tagName;
            $units[] = $this->describe($element, $isSection ? 2 : 1);

            if (!$isSection) {
                continue;
            }

            foreach ($this->subUnits($element) as $subUnit) {
                $units[] = $this->describe($subUnit['heading'], 3, $subUnit['nodes']);
            }
        }

        return $units;
    }

    // Fingerprint of a unit's body as the bundle ships it, stored next to an override so the drift check can
    // tell whether the text that override replaced has since moved. Whitespace-insensitive: re-indenting a
    // template is not a change of wording.
    public static function hash(string $html): string
    {
        return hash('xxh128', trim((string) preg_replace('/\s+/u', ' ', $html)));
    }

    // What "did the client actually change this?" is decided on. The customization screen hands Trix the
    // bundle's own text, and Trix re-serializes whatever it is given - it drops the class attributes the
    // models carry, among others. Comparing raw HTML would therefore mark every single section as rewritten
    // the first time the screen is saved, freezing the whole document, which is the exact opposite of the
    // point. Only class/style are stripped, deliberately: an href or an alt the client edited is a real
    // change and must still register.
    public static function comparable(string $html): string
    {
        $html = (string) preg_replace('/\s+(class|style)="[^"]*"/i', '', $html);

        return trim((string) preg_replace('/\s+/u', ' ', $html));
    }

    // True as soon as the block carries any delta - the cheap guard keeping untouched blocks off the DOM path
    private function isCustomized(array $customization): bool
    {
        foreach (['hidden', 'positions', 'overrides', 'extra'] as $key) {
            if (!empty($customization[$key])) {
                return true;
            }
        }

        return false;
    }

    private function renderModel(string $model, ?string $latestUpdate, string $locale): string
    {
        if (!$this->catalog->has($model)) {
            return '';
        }

        return $this->twig->render($this->templatePath($model, $locale), ['latestUpdate' => $latestUpdate]);
    }

    // A locale with no template of its own falls back on the one the models are authored in, rather than
    // throwing: a site adding a language shows its legal pages in French until someone translates them
    private function templatePath(string $model, string $locale): string
    {
        $path = sprintf('@c975LSite/models/%s.%s.html.twig', $model, $locale);

        return $this->twig->getLoader()->exists($path)
            ? $path
            : sprintf('@c975LSite/models/%s.%s.html.twig', $model, LegalModelCatalog::FALLBACK_LOCALE);
    }

    private function parse(string $html): ?\Dom\Element
    {
        if ('' === trim($html)) {
            return null;
        }

        return \Dom\HTMLDocument::createFromString($html, \LIBXML_NOERROR)->querySelector('.legal');
    }

    private function customize(string $html, array $customization): string
    {
        $root = $this->parse($html);

        if (null === $root) {
            return $html;
        }

        $hidden = array_flip(array_filter((array) ($customization['hidden'] ?? []), is_string(...)));
        $overrides = (array) ($customization['overrides'] ?? []);
        $positions = (array) ($customization['positions'] ?? []);
        $extra = (array) ($customization['extra'] ?? []);

        // Hidden top-level units go first: no point customizing the inside of a section about to be removed
        foreach ($this->taggedChildren($root) as $element) {
            if (isset($hidden[$this->identifier($element)])) {
                $element->remove();
            }
        }

        foreach ($this->taggedChildren($root) as $element) {
            $id = $this->identifier($element);

            if ('SECTION' === $element->tagName) {
                $this->customizeSection($element, $id, $hidden, $overrides, $positions, $extra);
                continue;
            }

            // A tagged <div>/<p> ships with no heading of its own, so it gets the one the client typed
            $this->overrideNodes($this->bodyNodes($element), $overrides[$id] ?? null, $this->openHeading($element, $overrides[$id] ?? null));
        }

        $this->addExtra($root, '', $extra);
        $this->reorder($root, $this->asUnits($this->taggedChildren($root)), $positions);

        return $root->outerHTML;
    }

    // A section's own lead (what sits between its <h2> and its first <h3>) plus everything that happens
    // inside it: hidden, rewritten, moved and added sub-sections
    private function customizeSection(\Dom\Element $section, string $id, array $hidden, array $overrides, array $positions, array $extra): void
    {
        $this->overrideNodes($this->sectionLead($section), $overrides[$id] ?? null, $section->querySelector('h2'));

        foreach ($this->subUnits($section) as $subUnit) {
            $subId = $this->identifier($subUnit['heading']);

            if (isset($hidden[$subId])) {
                foreach ([$subUnit['heading'], ...$subUnit['nodes']] as $node) {
                    $node->remove();
                }
                continue;
            }

            $this->overrideNodes($subUnit['nodes'], $overrides[$subId] ?? null, $subUnit['heading']);
        }

        $this->addExtra($section, $id, $extra);
        $this->reorder($section, $this->subUnitGroups($section), $positions);
    }

    // A headingless unit given a title gets an <h2> created for it, the way an added top-level section does.
    // The customization screen counts that title as a real edit, so dropping it would freeze the unit against
    // an override that renders nothing
    private function openHeading(\Dom\Element $element, ?array $override): ?\Dom\Element
    {
        if ('' === trim((string) ($override['title'] ?? ''))) {
            return null;
        }

        $heading = $element->ownerDocument->createElement('h2');
        $element->insertBefore($heading, $element->firstChild);

        return $heading;
    }

    // Replaces a run of nodes by the client's own HTML, and the heading's wording when they retitled it. A
    // null override, or one with neither a title nor content, leaves the bundle's text alone
    private function overrideNodes(array $nodes, ?array $override, ?\Dom\Element $heading): void
    {
        $title = trim((string) ($override['title'] ?? ''));
        if ('' !== $title && null !== $heading) {
            $heading->textContent = $title;
        }

        $content = trim((string) ($override['content'] ?? ''));
        if ('' === $content || [] === $nodes) {
            return;
        }

        $anchor = $nodes[0];
        $parent = $anchor->parentNode;
        if (null === $parent) {
            return;
        }

        foreach ($this->parseFragment($parent->ownerDocument, $content) as $node) {
            $parent->insertBefore($node, $anchor);
        }
        foreach ($nodes as $node) {
            $node->remove();
        }
    }

    // Everything a section holds between its heading and its first tagged sub-section
    private function sectionLead(\Dom\Element $section): array
    {
        $lead = [];
        foreach (iterator_to_array($section->childNodes) as $node) {
            if ($this->isSubHeading($node)) {
                break;
            }
            if ($node instanceof \Dom\Element && 'H2' === $node->tagName) {
                continue;
            }
            $lead[] = $node;
        }

        return $lead;
    }

    // An <h3> and the nodes it owns, up to the next one - the unit a client hides or rewrites at sub-level
    private function subUnits(\Dom\Element $section): array
    {
        $units = [];
        foreach (iterator_to_array($section->childNodes) as $node) {
            if ($this->isSubHeading($node)) {
                $units[] = ['heading' => $node, 'nodes' => []];
                continue;
            }
            if ([] !== $units) {
                $units[array_key_last($units)]['nodes'][] = $node;
            }
        }

        return $units;
    }

    // The same runs in the {id, nodes} shape reorder() works on, walked here rather than derived from
    // subUnits(): a section the client added is a tagged wrapper of its own, not an <h3> opening a run, and
    // folding it into the preceding sub-unit would make it travel with that one instead of taking its position
    private function subUnitGroups(\Dom\Element $section): array
    {
        $groups = [];
        foreach (iterator_to_array($section->childNodes) as $node) {
            if ($node instanceof \Dom\Element && $node->hasAttribute('data-legal-id')) {
                $groups[] = ['id' => $this->identifier($node), 'nodes' => [$node]];
                continue;
            }
            if ([] !== $groups) {
                $groups[array_key_last($groups)]['nodes'][] = $node;
            }
        }

        return $groups;
    }

    // Top-level elements in that same shape, one node each
    private function asUnits(array $elements): array
    {
        return array_map(
            fn (\Dom\Element $element): array => [
                'id' => $this->identifier($element),
                'nodes' => [$element],
            ],
            $elements
        );
    }

    // Dom\Element::getAttribute() answers null, not '', for an attribute that isn't there - and a null
    // array key is both a deprecation and a silent match against every untagged node
    private function identifier(\Dom\Element $element): string
    {
        return (string) $element->getAttribute('data-legal-id');
    }

    private function isSubHeading(mixed $node): bool
    {
        return $node instanceof \Dom\Element
            && 'H3' === $node->tagName
            && $node->hasAttribute('data-legal-id');
    }

    // The client's own sections, appended to the scope they belong to - reorder() then slots them in at the
    // position they were given
    private function addExtra(\Dom\Element $container, string $parentId, array $extra): void
    {
        foreach ($extra as $index => $section) {
            if (!\is_array($section) || $parentId !== (string) ($section['parent'] ?? '')) {
                continue;
            }

            $title = trim((string) ($section['title'] ?? ''));
            $content = trim((string) ($section['content'] ?? ''));
            if ('' === $title && '' === $content) {
                continue;
            }

            $container->append($container->ownerDocument->createTextNode("\n\t"));
            $container->append($this->buildExtra(
                $container->ownerDocument,
                (string) ($section['id'] ?? ('extra-' . $index)),
                $title,
                $content,
                '' !== $parentId,
            ));
        }
    }

    // A sub-level addition is an <h3> and its content wrapped together, a top-level one a <section> of its
    // own - or a plain <div> when the client gave it no heading, a headingless <section> being invalid HTML
    private function buildExtra(\Dom\Document $doc, string $id, string $title, string $content, bool $isSubLevel): \Dom\Element
    {
        $wrapper = $doc->createElement($isSubLevel || '' === $title ? 'div' : 'section');
        $wrapper->setAttribute('data-legal-id', $id);

        if ('' !== $title) {
            $heading = $doc->createElement($isSubLevel ? 'h3' : 'h2');
            $heading->textContent = $title;
            $wrapper->append($heading);
        }

        foreach ($this->parseFragment($doc, $content) as $node) {
            $wrapper->append($node);
        }

        return $wrapper;
    }

    // Re-appends a scope's units in the client's order. Units keep their natural rank unless one was given an
    // explicit position, and anything untagged (the "latest update" paragraph) never moves: it stays where the
    // template put it, ahead of the first unit. No position set anywhere in the scope, nothing is touched.
    private function reorder(\Dom\Element $container, array $units, array $positions): void
    {
        if ([] === $units || [] === array_intersect_key($positions, array_flip(array_column($units, 'id')))) {
            return;
        }

        $ranked = [];
        foreach ($units as $rank => $unit) {
            $ranked[] = [
                'position' => isset($positions[$unit['id']]) ? (float) $positions[$unit['id']] : ($rank + 1) * 10.0,
                'rank' => $rank,
                'nodes' => $unit['nodes'],
            ];
        }

        usort($ranked, static fn (array $a, array $b): int => [$a['position'], $a['rank']] <=> [$b['position'], $b['rank']]);

        foreach ($ranked as $unit) {
            $container->append($container->ownerDocument->createTextNode("\n\t"));
            foreach ($unit['nodes'] as $node) {
                $container->append($node);
            }
        }
    }

    // Element children carrying an identifier, as a plain array: the caller detaches and moves them, which a
    // live HTMLCollection would not survive
    private function taggedChildren(\Dom\Element $root): array
    {
        $tagged = [];
        foreach (iterator_to_array($root->childNodes) as $node) {
            if ($node instanceof \Dom\Element && $node->hasAttribute('data-legal-id')) {
                $tagged[] = $node;
            }
        }

        return $tagged;
    }

    // An element's content, heading excluded
    private function bodyNodes(\Dom\Element $element): array
    {
        return array_values(array_filter(
            iterator_to_array($element->childNodes),
            static fn (mixed $node): bool => !($node instanceof \Dom\Element && \in_array($node->tagName, ['H2', 'H3'], true)),
        ));
    }

    private function parseFragment(\Dom\Document $doc, string $html): array
    {
        $wrapper = $doc->createElement('div');
        $wrapper->innerHTML = $html;

        return iterator_to_array($wrapper->childNodes);
    }

    // One unit as the customization screen and the drift check see it
    private function describe(\Dom\Element $element, int $level, ?array $bodyNodes = null): array
    {
        $heading = match ($level) {
            2 => $element->querySelector('h2'),
            3 => $element,
            default => null,
        };
        $body = match ($level) {
            2 => $this->sectionLead($element),
            3 => $bodyNodes ?? [],
            default => $this->bodyNodes($element),
        };

        $html = '';
        foreach ($body as $node) {
            $html .= $node instanceof \Dom\Element ? $node->outerHTML : $node->textContent;
        }

        return [
            'id' => $this->identifier($element),
            'level' => $level,
            'title' => null !== $heading ? trim($heading->textContent) : '',
            'html' => trim($html),
            'hash' => self::hash($html),
        ];
    }
}
