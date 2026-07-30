<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\SiteBundle\Management;

use c975L\SiteBundle\Controller\Management\PageCrudController;
use c975L\SiteBundle\Entity\Page;
use c975L\UiBundle\Entity\Block;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGeneratorInterface;

// Traces something in a page's rendered HTML back to the Block that produced it, best-effort: rendered markup
// carries no block id for an anonymous visitor, so a miss returns null and the caller falls back to the page url
class PageBlockLocator
{
    use BlockFocusUrlTrait;

    // Below this, a needle is too generic to substring-match on and is only ever used as an exact match
    private const MIN_LOOSE_NEEDLE_LENGTH = 4;

    // Each block's own data as a searchable string, keyed by object id (see haystack())
    private array $haystacks = [];

    public function __construct(
        private readonly AdminUrlGeneratorInterface $adminUrlGenerator,
    ) {
    }

    // Returns ['label' => (string) $block, 'editUrl' => ...] for the block holding the image at $src, or null when no block claims it (a theme/template image, a logo, an image coming from somewhere other than this page's blocks)
    public function locateImage(Page $page, string $src): ?array
    {
        $needle = $this->basename($src);
        if ('' === $needle) {
            return null;
        }

        foreach ($this->blocksOf($page) as $block) {
            foreach ($block->getMedias() as $media) {
                if ($this->matchesFilename($needle, $media->getFilename()) || $this->matchesFilename($needle, $this->basename((string) $media->getUrl()))) {
                    return $this->describe($page, $block);
                }
            }

            // A media of this page's blocks is the common case, but an image can also be written straight into a block's own data (a rich-text body, an imported html snippet) - same length floor as locateLink(), a "cv.png" is too generic to claim a block on a substring alone
            if (\strlen($needle) >= self::MIN_LOOSE_NEEDLE_LENGTH && str_contains($this->haystack($block), $needle)) {
                return $this->describe($page, $block);
            }
        }

        return null;
    }

    // Returns ['label' => (string) $block, 'editUrl' => ...] for the block holding a link to $href, or null (a link coming from the menu, the footer, a template - none of them this page's blocks)
    public function locateLink(Page $page, string $href): ?array
    {
        $path = (string) (parse_url($href, \PHP_URL_PATH) ?: $href);
        // Both the path as written and its last segment: a block's data usually stores the full target ("/pages/contact/"), but a link field pointing at another page can hold just that page's slug
        $needles = array_filter([$path, trim($path, '/'), $this->basename($path)], fn (string $needle) => '' !== $needle);

        foreach ($this->blocksOf($page) as $block) {
            $haystack = $this->haystack($block);
            foreach ($needles as $needle) {
                if (\strlen($needle) >= self::MIN_LOOSE_NEEDLE_LENGTH && str_contains($haystack, $needle)) {
                    return $this->describe($page, $block);
                }
            }
        }

        return null;
    }

    // A block's slots are rendered inside their parent but edited as rows of their own, so a nested block is worth focusing directly rather than pointing at its parent
    private function blocksOf(Page $page): \Generator
    {
        foreach ($page->getBlocks() as $block) {
            yield from $this->withSlots($block);
        }
    }

    private function withSlots(Block $block): \Generator
    {
        yield $block;

        foreach ($block->getSlots() as $slot) {
            yield from $this->withSlots($slot);
        }
    }

    // The rendered src is rarely the stored filename verbatim - it carries a directory, a cache-busting query string, and for a resized/thumbnail variant a suffix appended to the name. Comparing the src's basename against the stored name, then against its extensionless stem as a prefix, covers all three
    private function matchesFilename(string $needle, ?string $filename): bool
    {
        if (null === $filename || '' === $filename) {
            return false;
        }

        if ($needle === $filename) {
            return true;
        }

        $stem = pathinfo($filename, \PATHINFO_FILENAME);
        if (\strlen($stem) < self::MIN_LOOSE_NEEDLE_LENGTH || !str_starts_with($needle, $stem)) {
            return false;
        }

        // A variant appends its suffix to the stored name ("photo-1_thumb.webp", "photo-1-300x200.webp"), so what follows the stem is always a separator - without that check, the stored "photo-1" would also claim a rendered "photo-11.webp", pointing at the wrong block
        $rest = substr($needle, \strlen($stem));

        return '' === $rest || 1 !== preg_match('/^[a-z0-9]/i', $rest);
    }

    // Everything the block holds as text, as one searchable string - JSON_UNESCAPED_SLASHES so an url in the data reads as "/pages/contact/" rather than "\/pages\/contact\/". Encoded once per block: a page is scanned again for every one of its images and broken links
    private function haystack(Block $block): string
    {
        return $this->haystacks[spl_object_id($block)] ??= (string) json_encode($block->getData(), \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE);
    }

    private function basename(string $url): string
    {
        $path = (string) (parse_url($url, \PHP_URL_PATH) ?: $url);

        return rawurldecode(basename($path));
    }

    private function describe(Page $page, Block $block): array
    {
        return [
            'label' => (string) $block,
            'editUrl' => $this->blockFocusUrl($this->adminUrlGenerator, PageCrudController::class, $page->getId(), $block),
        ];
    }
}
