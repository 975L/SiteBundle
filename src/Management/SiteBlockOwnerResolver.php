<?php
/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */
namespace c975L\SiteBundle\Management;

use c975L\SiteBundle\Repository\MenuRepository;
use c975L\SiteBundle\Repository\PageRepository;
use c975L\UiBundle\Contract\BlockOwnerResolverInterface;
use c975L\UiBundle\Contract\HasBlocksInterface;

// Lets UiBundle's BlockMoveController relocate a Block belonging to a Page or a Menu, without depending
// on either concrete class - auto-discovered by BlockOwnerResolverPass (see Readme)
class SiteBlockOwnerResolver implements BlockOwnerResolverInterface
{
    // Shared with PageCrudController/MenuCrudController's own blockMoveRowAttr() calls, so the owner-type strings only ever exist in one place
    public const TYPE_PAGE = 'page';
    public const TYPE_MENU = 'menu';

    private const TYPES = [self::TYPE_PAGE, self::TYPE_MENU];

    public function __construct(
        private readonly PageRepository $pageRepository,
        private readonly MenuRepository $menuRepository,
    ) {
    }

    public function supports(string $ownerType): bool
    {
        return in_array($ownerType, self::TYPES, true);
    }

    public function find(string $ownerType, int $ownerId): ?HasBlocksInterface
    {
        return match ($ownerType) {
            self::TYPE_PAGE => $this->pageRepository->find($ownerId),
            self::TYPE_MENU => $this->menuRepository->find($ownerId),
            default => null,
        };
    }
}
