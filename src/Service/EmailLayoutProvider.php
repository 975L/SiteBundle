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

// Plugs SiteBundle's own branded email layout (header/footer, no-spam text, legal mentions - fullLayout.html.twig)
// into UiBundle\Service\EmailTemplateRenderer::render(), auto-discovered by EmailLayoutProviderPass - so an
// EmailTemplate preview (EmailTemplateCrudController) and a real send (e.g. SendEmailFormAction) both render
// exactly like a recipient would actually see it, instead of UiBundle's bare standalone document
class EmailLayoutProvider implements EmailLayoutProviderInterface
{
    public function __construct(
        private readonly Environment $twig,
    ) {
    }

    public function wrap(string $bodyHtml): string
    {
        return $this->twig->render('@c975LSite/emails/emailTemplateLayout.html.twig', ['bodyHtml' => $bodyHtml]);
    }
}
