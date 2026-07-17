<?php
/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */
namespace c975L\SiteBundle\Management;

use App\Entity\User;
use c975L\SiteBundle\Entity\Page;
use c975L\UiBundle\Entity\Block;

// Turns a template's "blocks" array (see SiteTemplateProvider / config/templates/*.json) into real
// Block entities on a Page - shared by PageCrudController::applyTemplate() (admin action) and
// TemplateApplyCommand (CLI), so both stay in sync with a single implementation
class TemplateApplier
{
    // Builds transient Block objects from a template's block specs, attached to no Page
    public function build(array $template, int $startPosition = 0): array
    {
        $blocks = [];
        $position = $startPosition;

        foreach ($template['blocks'] as $blockSpec) {
            $blocks[] = (new Block())
                ->setKind($blockSpec['kind'])
                ->setData($blockSpec['data'] ?? [])
                ->setPosition($position++);
        }

        return $blocks;
    }

    // Appends the template's blocks after the page's existing ones (does not touch what's already there)
    public function apply(Page $page, array $template, ?User $user = null): int
    {
        $blocks = $this->build($template, count($page->getBlocks()));

        foreach ($blocks as $block) {
            if (null !== $user) {
                $block->setUser($user);
            }

            $page->addBlock($block);
        }

        return count($blocks);
    }
}
