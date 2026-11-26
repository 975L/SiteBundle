<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\SiteBundle\Management;

use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

// Implemented by an app whose pages fall into parts with a navigation of their own (a members' area, a catalog): each variant it declares gets a navbar and a footer to compose in the back office, which a template picks by setting "menuVariant", the site's own menus showing wherever it does not or the variant's menu holds nothing
#[AutoconfigureTag(self::TAG)]
interface MenuVariantProviderInterface
{
    public const string TAG = 'c975l_site.menu_variant_provider';

    // The variants, as "key => label" ("members" => "Members"), each key 1 to 13 lowercase letters, digits, "_" or "-", the label naming the variant's menus in the back office
    /** @return array<string, string> */
    public function getMenuVariants(): array;
}
