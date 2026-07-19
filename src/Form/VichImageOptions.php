<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\SiteBundle\Form;

use Symfony\Component\Validator\Constraints\File as FileConstraint;

// Shared Vich image-upload field options, for both an EasyAdmin Field::new()->setFormTypeOptions(...) and a plain Symfony FormBuilder::add(..., VichImageType::class, ...) (see OgImageType, SiteGraphicCrudController, CollectionItemCrudController) - one place to keep in sync instead of hand-duplicating this block per form/controller
final class VichImageOptions
{
    public static function default(string $maxSize = '10M', bool $required = false): array
    {
        return [
            'required' => $required,
            'allow_delete' => true,
            'download_uri' => true,
            'asset_helper' => true,
            'constraints' => [
                new FileConstraint(maxSize: $maxSize),
            ],
        ];
    }
}
