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

class Nl2brExtension extends AbstractExtension
{
    public function getFilters(): array
    {
        return [
            // Écrase le filtre natif 'nl2br' de Twig
            new TwigFilter('nl2br', [self::class, 'nl2br'], ['is_safe' => ['html']]),
        ];
    }

    public static function nl2br($string): string
    {
        return nl2br($string ?? '', false);
    }
}
