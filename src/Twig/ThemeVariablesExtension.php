<?php
/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\SiteBundle\Twig;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

// Exposes the CSS file compiled by ThemeVariablesCssListener from the admin-editable "theme" group configs, so it can be inlined in emails (no <link> possible there) instead of duplicated by hand in the previous _user-variables.css/_user-typography.css override stubs
class ThemeVariablesExtension extends AbstractExtension
{
    public function __construct(
        #[Autowire('%kernel.project_dir%')]
        private readonly string $projectDir,
    ) {}

    public function getFunctions(): array
    {
        return [
            new TwigFunction('theme_variables_css', [$this, 'getThemeVariablesCss'], ['is_safe' => ['html']]),
        ];
    }

    public function getThemeVariablesCss(): string
    {
        $path = $this->projectDir . '/public/bundles/build/site-theme.css';

        return is_file($path) ? (file_get_contents($path) ?: '') : '';
    }
}
