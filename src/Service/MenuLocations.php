<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\SiteBundle\Service;

use c975L\SiteBundle\Controller\Management\MenuCrudController;
use c975L\SiteBundle\Entity\Menu;
use c975L\SiteBundle\Management\MenuVariantProviderInterface;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;
use Symfony\Contracts\Translation\TranslatorInterface;

// The menu locations of the site: the bundle's own, then a navbar and a footer for each variant an app declares (see MenuVariantProviderInterface), "navbar.members" holding the navbar of the "members" variant
class MenuLocations
{
    // Between a location and its variant: allowed in a cache key, where ":" and "@" are not, and apart from the "-" of "navbar-brand"
    public const string SEPARATOR = '.';

    // The locations a variant has a menu of
    public const array VARIANT_BASES = [Menu::LOCATION_NAVBAR, Menu::LOCATION_FOOTER];

    // A variant key, short enough for "footer." and itself to fit the 20 characters of Menu::$location
    private const string VARIANT_PATTERN = '/^[a-z0-9_-]{1,13}$/';

    /** @var ?array<string, string> */
    private ?array $variants = null;

    /** @param iterable<MenuVariantProviderInterface> $providers */
    public function __construct(
        private readonly TranslatorInterface $translator,
        #[AutowireIterator(MenuVariantProviderInterface::TAG)]
        private readonly iterable $providers = [],
    ) {
    }

    // Every location, as "location => translated label", a variant's menus named after the location they stand in for
    /** @return array<string, string> */
    public function all(): array
    {
        $locations = [];
        foreach (MenuCrudController::LOCATION_LABELS as $location => $labelKey) {
            $locations[$location] = $this->translator->trans($labelKey, [], 'site');
        }
        foreach ($this->variants() as $variant => $label) {
            foreach (self::VARIANT_BASES as $base) {
                $locations[$base . self::SEPARATOR . $variant] = $locations[$base] . ' · ' . $label;
            }
        }

        return $locations;
    }

    // Whether a menu may be created at this location
    public function has(string $location): bool
    {
        return isset($this->all()[$location]);
    }

    // The name of a location, "Menu" for one neither the bundle nor the app declares
    public function label(string $location): string
    {
        return $this->all()[$location] ?? $this->translator->trans('label.menu', [], 'site');
    }

    // The location of a variant's menu, null for no variant, one no app declared, or a location variants have no menu of
    public function variantLocation(string $base, ?string $variant): ?string
    {
        if (null === $variant || !isset($this->variants()[$variant]) || !\in_array($base, self::VARIANT_BASES, true)) {
            return null;
        }

        return $base . self::SEPARATOR . $variant;
    }

    // The location a variant's menu stands in for ("navbar" for "navbar.members"), the location itself otherwise
    public static function base(string $location): string
    {
        return explode(self::SEPARATOR, $location, 2)[0];
    }

    // The variants of every provider, a key that would not fit a location refused at once
    /** @return array<string, string> */
    private function variants(): array
    {
        if (null !== $this->variants) {
            return $this->variants;
        }

        $this->variants = [];
        foreach ($this->providers as $provider) {
            foreach ($provider->getMenuVariants() as $variant => $label) {
                if (1 !== preg_match(self::VARIANT_PATTERN, (string) $variant)) {
                    throw new \LogicException(\sprintf('Menu variant "%s" of %s: 1 to 13 lowercase letters, digits, "_" or "-".', $variant, $provider::class));
                }
                $this->variants[(string) $variant] = $label;
            }
        }

        return $this->variants;
    }
}
