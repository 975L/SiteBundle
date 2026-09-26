<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\SiteBundle\Service;

use c975L\ConfigBundle\Management\GuidedProjectBuilder;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

// The films of the back office's guided projects this site publishes: every guided project the installed bundles and the app offer, crossed with the manifest left in public/medias/films/<locale>/. Whatever shoots the films only has to leave that folder as README's "Tutorial films" describes it
class TutorialCatalog
{
    // Under public/medias with the uploads, backed up and copied between servers like them (see SiteBackupPathProvider)
    public const string DIRECTORY = 'medias/films';

    public const string MANIFEST = 'films.json';

    // The manifests already read, by locale: a dashboard asks whether each of its projects is filmed (see TutorialFilmUrlProvider)
    /** @var array<string, array<string, array<string, mixed>>> */
    private array $manifests = [];

    public function __construct(
        private readonly GuidedProjectBuilder $guidedProjectBuilder,
        #[Autowire('%kernel.project_dir%/public')]
        private readonly string $publicDirectory,
        #[Autowire(param: 'kernel.default_locale')]
        private readonly string $defaultLocale,
    ) {
    }

    // Every project having a film, in the guided sequence's order. getAllProjects() rather than getProjects(): a visitor holds no role, and the filtered list would leave out every parcours
    /** @return list<array<string, mixed>> */
    public function all(string $locale): array
    {
        // Film by film rather than page by page: a language with a handful of its own films still shows the others in the site's language, subtitled
        $manifests = [];
        foreach (array_unique([$locale, $this->defaultLocale]) as $candidate) {
            $manifests[$candidate] = $this->manifest($candidate);
        }

        $tutorials = [];
        foreach ($this->guidedProjectBuilder->getAllProjects() as $project) {
            $filmLocale = array_find_key($manifests, static fn (array $films): bool => isset($films[$project['slug']]));
            if (null === $filmLocale) {
                continue;
            }

            $film = $manifests[$filmLocale][$project['slug']];
            $base = sprintf('/%s/%s/%s', self::DIRECTORY, $filmLocale, $project['slug']);
            $version = '?v=' . $film['version'];
            $tutorials[] = [
                'slug' => $project['slug'],
                'label' => $project['label'],
                'description' => $project['description'],
                'narrated' => $film['narrated'],
                'version' => $film['version'],
                'shotAt' => $film['shotAt'] ?? $film['version'],
                'video' => $base . '.webm' . $version,
                'subtitles' => $base . '.vtt' . $version,
                'poster' => $base . '.jpg' . $version,
                'locale' => $filmLocale,
                'steps' => $this->steps($project['steps'], $film['starts']),
            ];
        }

        return $tutorials;
    }

    // Whether a project has a film in that language or in the site's, read off the manifests alone - what ConfigBundle's own project list asks while it is being built, and so without asking it back for its projects
    public function isFilmed(string $slug, string $locale): bool
    {
        return isset($this->manifest($locale)[$slug]) || isset($this->manifest($this->defaultLocale)[$slug]);
    }

    // One filmed project by its slug, null when no film or no project answers to it
    /** @return ?array<string, mixed> */
    public function find(string $slug, string $locale): ?array
    {
        return array_find($this->all($locale), static fn (array $tutorial): bool => $slug === $tutorial['slug']);
    }

    // The steps with the second each starts at, none timed when the film counts a different number of steps than the project
    /**
     * @param list<array{label: string}> $steps
     * @param list<float>                $starts
     *
     * @return list<array{label: string, start: ?float}>
     */
    private function steps(array $steps, array $starts): array
    {
        $timed = \count($starts) === \count($steps);

        return array_map(static fn (array $step, int $index): array => [
            'label' => $step['label'],
            'start' => $timed ? (float) $starts[$index] : null,
        ], $steps, array_keys($steps));
    }

    // The films published for one locale, none when it has no folder yet. An entry without a version is dropped and the others get their defaults, so a hand-edited manifest never breaks the page
    /** @return array<string, array{narrated: bool, version: int, shotAt?: int, starts: list<float>}> */
    private function manifest(string $locale): array
    {
        if (!isset($this->manifests[$locale])) {
            $file = sprintf('%s/%s/%s/%s', $this->publicDirectory, self::DIRECTORY, $locale, self::MANIFEST);
            $decoded = is_file($file) ? json_decode((string) file_get_contents($file), true) : [];
            $films = array_filter(\is_array($decoded) ? $decoded : [], static fn (mixed $film): bool => \is_array($film) && isset($film['version']));
            $this->manifests[$locale] = array_map(static fn (array $film): array => [
                ...$film,
                'narrated' => (bool) ($film['narrated'] ?? false),
                'starts' => array_values((array) ($film['starts'] ?? [])),
            ], $films);
        }

        return $this->manifests[$locale];
    }
}
