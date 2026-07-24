<?php
/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */
namespace c975L\SiteBundle\Controller\Management\Trait;

use Symfony\Component\Routing\Exception\RouteNotFoundException;

// Shared by PageCrudController/MenuCrudController: builds the "blocks" CollectionField's row_attr,
// read by UiBundle's ea-sortable.js/BlockMoveController to let an editor drag an already-saved Block
// into a container present on this same page/menu (or back out to top-level).
//
// The route ('management_ui_block_move') is deliberately looked up by its literal id, not
// c975L\UiBundle\Controller\Management\BlockMoveController::MOVE_ROUTE - that controller ships in a
// UiBundle release this bundle doesn't require yet (see UPGRADE.md/composer.json). generateUrl() is
// wrapped so this degrades to no row_attr at all (feature silently absent) rather than a fatal
// RouteNotFoundException on every Page/Menu edit screen until that UiBundle version is required.
trait BlockMoveRowAttrTrait
{
    private function blockMoveRowAttr(string $ownerType, ?int $ownerId): array
    {
        if (null === $ownerId) {
            return [];
        }

        try {
            $url = $this->generateUrl('management_ui_block_move');
        } catch (RouteNotFoundException) {
            return [];
        }

        return [
            'data-block-collection' => '1',
            'data-block-owner-type' => $ownerType,
            'data-block-owner-id' => $ownerId,
            'data-block-move-url' => $url,
            'data-block-move-csrf-token' => $this->csrfTokenManager->getToken('management_ui_block_move')->getValue(),
            'data-block-move-failed-label' => $this->translator->trans('flash.block_move_failed', [], 'ui'),
        ];
    }
}
