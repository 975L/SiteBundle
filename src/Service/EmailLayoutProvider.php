<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\SiteBundle\Service;

use c975L\UiBundle\Contract\EmailLayoutProviderInterface;
use Twig\Environment;

// Plugs this bundle's branded email layout into EmailTemplateRenderer, so preview and real send match
class EmailLayoutProvider implements EmailLayoutProviderInterface
{
    public function __construct(
        private readonly Environment $twig,
    ) {
    }

    // The locale is the one the body was resolved in, handed down so the layout's own four sentences are read in the
    // same language rather than in whichever one the database returns first (see fullLayout.html.twig)
    public function wrap(string $bodyHtml, ?string $locale = null): string
    {
        return $this->twig->render('@c975LSite/emails/emailTemplateLayout.html.twig', ['bodyHtml' => $bodyHtml, 'locale' => $locale]);
    }
}
