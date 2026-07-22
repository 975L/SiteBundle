<?php
/*
 * (c) 2018: 975L <contact@975l.com>
 * (c) 2018: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\SiteBundle;

use c975L\ConfigBundle\DependencyInjection\Compiler\TaggedInterfacePass;
use c975L\SiteBundle\Management\TemplateProviderInterface;
use c975L\UiBundle\Namer\UiMediaNamer;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Bundle\AbstractBundle;

class c975LSiteBundle extends AbstractBundle
{
    public function build(ContainerBuilder $container): void
    {
        $container->addCompilerPass(new TaggedInterfacePass(TemplateProviderInterface::class, 'c975l.template_provider'));
    }

    public function loadExtension(array $config, ContainerConfigurator $containerConfigurator, ContainerBuilder $containerBuilder): void
    {
        $containerConfigurator->import('../config/services.yaml');
    }

    public function prependExtension(ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        $builder->prependExtensionConfig('framework', [
            'asset_mapper' => [
                'paths' => [
                    __DIR__ . '/../assets' => '@c975l/site-bundle',
                ],
            ],
        ]);

        // Registers public/css as a Twig namespace so compiled stylesheets can be embedded raw via source(), e.g. emails.min.css in fullLayout.html.twig
        $builder->prependExtensionConfig('twig', [
            'paths' => [
                __DIR__ . '/../public/css' => 'c975LSiteCss',
            ],
        ]);

        // CollectionItem's/Font's own uploadable field (not UiBundle\Media) - the global "storage" (Nested FileSystemStorage) is already set once by UiBundle's own prependExtension(), no need to repeat it here, same as BookBundle's/ShopBundle's own mappings
        if ($builder->hasExtension('vich_uploader')) {
            $builder->prependExtensionConfig('vich_uploader', [
                'mappings' => [
                    'collection_item' => [
                        'uri_prefix' => '',
                        'upload_destination' => '%kernel.project_dir%/public',
                        'namer' => UiMediaNamer::class,
                        'inject_on_load' => false,
                        'delete_on_update' => true,
                        'delete_on_remove' => true,
                    ],
                    // Admin-uploaded font files (ttf/woff/woff2), stored under public/medias/fonts (see Font::getVichMediaPath) - see FontCssListener for the generated @font-face rules
                    'site_font' => [
                        'uri_prefix' => '',
                        'upload_destination' => '%kernel.project_dir%/public',
                        'namer' => UiMediaNamer::class,
                        'inject_on_load' => false,
                        'delete_on_update' => true,
                        'delete_on_remove' => true,
                    ],
                ],
            ]);
        }
    }

    public function getPath(): string
    {
        return \dirname(__DIR__);
    }
}
