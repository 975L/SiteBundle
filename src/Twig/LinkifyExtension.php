<?php
/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\SiteBundle\Twig;

use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

class LinkifyExtension extends AbstractExtension
{
    public function getFilters(): array
    {
        return [
            new TwigFilter('linkify', [self::class, 'linkify'], ['is_safe' => ['html']]),
        ];
    }

    // Splits the RAW string around http(s) URLs first (so a literal quote right after a URL
    // still stops the match, instead of being turned into "&quot;" by an earlier escaping pass
    // and swallowed into the link), then HTML-escapes each part separately
    public static function linkify(?string $string): string
    {
        $parts = preg_split('#(https?://[^\s"\'<>]+)#', $string ?? '', -1, PREG_SPLIT_DELIM_CAPTURE);

        $html = '';
        foreach ($parts as $i => $part) {
            if (0 === $i % 2) {
                $html .= htmlspecialchars($part, ENT_QUOTES, 'UTF-8');

                continue;
            }

            // Trailing punctuation (end-of-sentence period, comma, closing parenthesis, ...)
            // belongs to the surrounding text, not the URL itself
            $url = rtrim($part, '.,;:!?)');
            $trailing = substr($part, \strlen($url));
            $escapedUrl = htmlspecialchars($url, ENT_QUOTES, 'UTF-8');

            $html .= sprintf('<a href="%s" target="_blank" rel="noopener noreferrer">%s</a>', $escapedUrl, $escapedUrl)
                . htmlspecialchars($trailing, ENT_QUOTES, 'UTF-8');
        }

        return $html;
    }
}
