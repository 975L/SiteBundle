<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\SiteBundle\Twig;

use c975L\SiteBundle\Service\TwigContentTemplateChecker;
use Twig\Attribute\AsTwigFunction;

// Hands TwigContent.html.twig the check it makes before including the path a block carries (see TwigContentTemplateChecker) - the template is the last gate, a Block being writable by a fixture or an import as well as by the form
class TwigContentExtension
{
    public function __construct(private readonly TwigContentTemplateChecker $checker)
    {
    }

    // Reads on the path as the block carries it, an empty one included, which the template also guards against
    #[AsTwigFunction('site_twig_content_allowed')]
    public function isAllowed(?string $templatePath): bool
    {
        return $this->checker->isAllowed($templatePath);
    }
}
