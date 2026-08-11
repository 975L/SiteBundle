<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

use Rector\Config\RectorConfig;
use Rector\Doctrine\Set\DoctrineSetList;
use Rector\Php80\Rector\ClassMethod\AddParamBasedOnParentClassMethodRector;
use Rector\Php82\Rector\Class_\ReadOnlyClassRector;
use Rector\Symfony\Set\SymfonySetList;

// The same sets a site gets from SymfonyMigrate.sh, deliberately: the scaffold is copied as is into the applications, where that configuration is what runs over it. Anything this bundle leaves behind is rewritten there, and the rewritten file no longer matches the hash ScaffoldInstaller recorded, so the site is told forever it customized a file it never touched. Hence scaffold/ in the paths below, next to the bundle's own code
// withPhpSets() takes its target from composer.json rather than naming a version here: the bundles and the sites both require ">=8.4", so both resolve to the same rules and neither can drift ahead of the other
return RectorConfig::configure()
    ->withPaths([
        __DIR__ . '/src',
        __DIR__ . '/tests',
        __DIR__ . '/scaffold',
    ])
    ->withPhpSets()
    ->withSets([
        SymfonySetList::SYMFONY_80,
        DoctrineSetList::ANNOTATIONS_TO_ATTRIBUTES,
    ])
    // Two rules are dropped rather than followed: a readonly class can only be extended by another readonly one, which closes the door these bundles are built to leave open - a site overriding a service would have to make its own readonly too, and could then no longer hold state of its own; and copying a parent's parameters after a variadic $args does not compile, while that variadic is deliberate in the CrudControllers, where it absorbs EasyAdmin's signature changes without the bundle having to follow them
    ->withSkip([
        AddParamBasedOnParentClassMethodRector::class,
        ReadOnlyClassRector::class,
    ]);
