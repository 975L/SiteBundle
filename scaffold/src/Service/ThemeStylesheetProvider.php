<?php

namespace App\Service;

use c975L\UiBundle\Contract\BundleStylesheetProviderInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

// Contributes this site's own theme files - assets/styles/themes/*.css, one per installed c975L bundle, copied here once by "c975l:scaffold:install" and owned by the app from then on. Registering them rather than importing them from assets/app.js is what keeps them in the single bundles/build/site.css the bundles already share: AssetMapper never merges CSS, so an import would cost one request per file. It also means they load after every bundle's own stylesheet, this provider carrying no "priority" while the bundles' carry 100 - which is exactly what a theme needs to win. The directory is read rather than a list being kept here: installing another c975L bundle drops its own theme file next to the others and it is picked up with no change to this class.
class ThemeStylesheetProvider implements BundleStylesheetProviderInterface
{
    private const string THEMES_DIR = 'assets/styles/themes';

    public function __construct(
        #[Autowire('%kernel.project_dir%')]
        private readonly string $projectDir,
    ) {
    }

    public function getStylesheets(): array
    {
        // Sorted so the concatenated stylesheet is byte-identical from one build to the next, whatever order the filesystem hands the entries back in - the files declare disjoint tokens, so which one comes first never changes what the page looks like
        $files = glob($this->projectDir . '/' . self::THEMES_DIR . '/*.css') ?: [];
        sort($files);

        return array_map(fn (string $file): string => self::THEMES_DIR . '/' . basename($file), $files);
    }
}
