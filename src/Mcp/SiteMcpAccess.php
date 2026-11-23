<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\SiteBundle\Mcp;

use c975L\ConfigBundle\Contract\UserInterface;
use c975L\ConfigBundle\Service\ConfigServiceInterface;
use Mcp\Server\Exception\ToolCallException;
use Symfony\Bundle\SecurityBundle\Security;

// What every site tool checks before answering: the account is an editor, as on the back office's pages and collections
class SiteMcpAccess
{
    public function __construct(
        private readonly ConfigServiceInterface $configService,
        private readonly Security $security,
    ) {
    }

    // The pages and the collections are the editor's business, $subject naming what the refusal is about
    public function guard(string $subject): void
    {
        if (!$this->security->isGranted((string) $this->configService->get('site-role-editor'))) {
            throw new ToolCallException(sprintf('This account may not work on the %s.', $subject));
        }
    }

    // The account the token stands for, recorded on what it writes
    public function user(): ?UserInterface
    {
        $user = $this->security->getUser();

        return $user instanceof UserInterface ? $user : null;
    }
}
