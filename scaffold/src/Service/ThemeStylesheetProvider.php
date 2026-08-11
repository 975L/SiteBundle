<?php

namespace App\Service;

use c975L\UiBundle\Contract\BundleStylesheetProviderInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

// Contributes this site's own stylesheets - assets/styles/themes/*.css, one per installed c975L bundle, then whatever the site keeps in assets/styles/ itself, all copied here once by "c975l:scaffold:install" and owned by the app from then on. Registering them rather than importing them from assets/app.js is what keeps them in the single bundles/build/site.css the bundles already share: AssetMapper never merges CSS, so an import would cost one request per file. It also means they load after every bundle's own stylesheet, this provider carrying no "priority" while the bundles' carry 100 - which is exactly what a theme needs to win. Both directories are read rather than a list being kept here: installing another c975L bundle drops its own theme file next to the others, and a site adding a stylesheet of its own is picked up the same way, with no change to this class.
// Importing a stylesheet from JS also puts it in the import map, where AssetMapper addresses a CSS entry by a "data:application/javascript," URL - a scheme script-src has no reason to carry, so the page's own Content-Security-Policy blocks it and the whole app entrypoint fails with it. Symfony's own recipe writes exactly that import into assets/app.js, which is why "c975l:site:create" warns about it (see ScaffoldInstaller::themeImportReminder()).
class ThemeStylesheetProvider implements BundleStylesheetProviderInterface
{
    private const string THEMES_DIR = 'assets/styles/themes';

    private const string STYLES_DIR = 'assets/styles';

    // The site's own overrides, kept last of all whatever the alphabet says: it is where the design has the final word over every theme file above it
    private const string LAST_STYLESHEET = 'app.css';

    public function __construct(
        #[Autowire('%kernel.project_dir%')]
        private readonly string $projectDir,
    ) {
    }

    public function getStylesheets(): array
    {
        return [
            ...$this->collect(self::THEMES_DIR),
            ...$this->collect(self::STYLES_DIR),
        ];
    }

    // The stylesheets a directory holds, as paths relative to the project root, in the order the cascade must read them
    /** @return string[] */
    private function collect(string $directory): array
    {
        // Sorted so the concatenated stylesheet is byte-identical from one build to the next, whatever order the filesystem hands the entries back in
        $files = array_map(basename(...), glob($this->projectDir . '/' . $directory . '/*.css') ?: []);
        sort($files);

        // Only ever moves app.css, and only when it is not already where sorting put it - a site's partials are conventionally underscore-prefixed, which sorts them ahead of it anyway
        $last = array_search(self::LAST_STYLESHEET, $files, true);
        if (false !== $last) {
            $files[] = array_splice($files, $last, 1)[0];
        }

        return array_map(fn (string $file): string => $directory . '/' . $file, $files);
    }
}
