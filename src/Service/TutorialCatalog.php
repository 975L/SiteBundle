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
use Symfony\Contracts\Translation\TranslatorInterface;

// The films of the back office's guided projects this site publishes: every guided project the installed bundles and the app offer, crossed with the manifest left in public/medias/films/<locale>/ - or in private/medias/films/<locale>/ for films only the back office shows (see findPrivate()). Whatever shoots the films only has to leave that folder as README's "Tutorial films" describes it
class TutorialCatalog
{
    // Under public/medias with the uploads - or private/medias for the films only the back office shows -, backed up and copied between servers like them (see SiteBackupPathProvider)
    public const string DIRECTORY = 'medias/films';

    public const string MANIFEST = 'films.json';

    // The dashboard's guided tour, filmed beside the projects under this name though it is none (see ConfigBundle's OnboardingStepBuilder)
    public const string TOUR = 'guided-tour';

    // The manifests already read, by folder and locale: a dashboard asks whether each of its projects is filmed (see TutorialFilmUrlProvider)
    /** @var array<string, array<string, array<string, array<string, mixed>>>> */
    private array $manifests = [];

    public function __construct(
        private readonly GuidedProjectBuilder $guidedProjectBuilder,
        private readonly TranslatorInterface $translator,
        #[Autowire('%kernel.project_dir%/public')]
        private readonly string $publicDirectory,
        #[Autowire(param: 'kernel.default_locale')]
        private readonly string $defaultLocale,
        // Out of the web server's reach, for the films of a back office nobody else is meant to see (see TutorialFilmController)
        #[Autowire('%kernel.project_dir%/private')]
        private readonly string $privateDirectory,
    ) {
    }

    // Every project having a film, in the guided sequence's order, the guided tour leading as what a newcomer opens first. getAllProjects() rather than getProjects(): a visitor holds no role, and the filtered list would leave out every parcours
    /** @return list<array<string, mixed>> */
    public function all(string $locale): array
    {
        return $this->tutorials(
            [$this->tour($locale), ...$this->guidedProjectBuilder->getAllProjects()],
            $locale,
            $this->publicDirectory,
            static fn (string $slug, string $filmLocale, string $extension, int $version): string => sprintf('/%s/%s/%s.%s?v=%d', self::DIRECTORY, $filmLocale, $slug, $extension, $version),
        );
    }

    // The films only the back office shows, as all() lists the public ones - their files served from wherever $fileUrl says (see TutorialFilmController), and only those of the parcours the reader may follow, getProjects() answering for their roles like the route serving them does
    /**
     * @param \Closure(string $slug, string $locale, string $extension, int $version): string $fileUrl
     *
     * @return list<array<string, mixed>>
     */
    public function allPrivate(string $locale, \Closure $fileUrl): array
    {
        return $this->tutorials($this->guidedProjectBuilder->getProjects(), $locale, $this->privateDirectory, $fileUrl);
    }

    // The projects crossed with the manifests of one folder. Film by film rather than page by page: a language with a handful of its own films still shows the others in the site's language, subtitled
    /**
     * @param list<array<string, mixed>> $projects
     *
     * @return list<array<string, mixed>>
     */
    private function tutorials(array $projects, string $locale, string $directory, \Closure $fileUrl): array
    {
        $manifests = $this->manifests($locale, $directory);

        $tutorials = [];
        foreach ($projects as $project) {
            $filmLocale = array_find_key($manifests, static fn (array $films): bool => isset($films[$project['slug']]));
            if (null === $filmLocale) {
                continue;
            }

            $film = $manifests[$filmLocale][$project['slug']];
            $url = static fn (string $extension): string => $fileUrl($project['slug'], $filmLocale, $extension, (int) $film['version']);
            $tutorials[] = [
                'slug' => $project['slug'],
                'label' => $project['label'],
                'description' => $project['description'],
                'narrated' => $film['narrated'],
                'version' => $film['version'],
                'shotAt' => $film['shotAt'] ?? $film['version'],
                'shotOn' => $film['shotOn'] ?? null,
                'video' => $url('webm'),
                'subtitles' => $url('vtt'),
                'poster' => $url('jpg'),
                'locale' => $filmLocale,
                'steps' => $this->steps($project['steps'], $film['starts']),
            ];
        }

        return $tutorials;
    }

    // The guided tour as a project, without steps: they hang on what the dashboard shows its reader, which a visitor's page cannot rebuild
    /** @return array{slug: string, label: string, description: string, steps: list<array{label: string}>} */
    private function tour(string $locale): array
    {
        return [
            'slug' => self::TOUR,
            'label' => $this->translator->trans('label.onboarding_start', [], 'config', $locale),
            'description' => $this->translator->trans('description.tutorial_guided_tour', [], 'site', $locale),
            'steps' => [],
        ];
    }

    // Whether a project has a film in that language or in the site's, read off the manifests alone - what ConfigBundle's own project list asks while it is being built, and so without asking it back for its projects
    public function isFilmed(string $slug, string $locale): bool
    {
        return array_any($this->manifests($locale, $this->publicDirectory), static fn (array $films): bool => isset($films[$slug]));
    }

    // Whether a project has a public film in that very language, the site's not standing in for it - what outranks a film only the back office shows found in the site's language (see TutorialFilmUrlProvider)
    public function isFilmedIn(string $slug, string $locale): bool
    {
        return isset($this->manifest($locale, $this->publicDirectory)[$slug]);
    }

    // The film only the back office shows of a project, read off private/medias/films alone - its locale (the one asked for, the site's otherwise), version and whether it speaks, null when it has none. Without asking GuidedProjectBuilder back for its projects, since it is asked while they are being built
    /** @return ?array{locale: string, version: int, narrated: bool} */
    public function findPrivate(string $slug, string $locale): ?array
    {
        $manifests = $this->manifests($locale, $this->privateDirectory);
        $candidate = array_find_key($manifests, static fn (array $films): bool => isset($films[$slug]));
        if (null === $candidate) {
            return null;
        }

        $film = $manifests[$candidate][$slug];

        return ['locale' => $candidate, 'version' => (int) $film['version'], 'narrated' => $film['narrated']];
    }

    // Where one file of a film only the back office shows lies on disk ("webm", "vtt" or "jpg")
    public function privateFile(string $slug, string $locale, string $extension): string
    {
        return sprintf('%s/%s/%s/%s.%s', $this->privateDirectory, self::DIRECTORY, $locale, $slug, $extension);
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

    // The manifests a reader in that language is served from, keyed by their locale: theirs first, then the site's - the one fallback rule every lookup goes through
    /** @return array<string, array<string, array{narrated: bool, version: int, shotAt?: int, shotOn: ?string, starts: list<float>}>> */
    private function manifests(string $locale, string $directory): array
    {
        $manifests = [];
        foreach (array_unique([$locale, $this->defaultLocale]) as $candidate) {
            $manifests[$candidate] = $this->manifest($candidate, $directory);
        }

        return $manifests;
    }

    // The films left for one locale in the given folder, none when it has no folder yet. An entry without a version is dropped and the others get their defaults, so a hand-edited manifest never breaks the page
    /** @return array<string, array{narrated: bool, version: int, shotAt?: int, shotOn: ?string, starts: list<float>}> */
    private function manifest(string $locale, string $directory): array
    {
        return $this->manifests[$directory][$locale] ??= $this->readManifest(sprintf('%s/%s/%s/%s', $directory, self::DIRECTORY, $locale, self::MANIFEST));
    }

    // One manifest file, decoded and normalized
    /** @return array<string, array{narrated: bool, version: int, shotAt?: int, shotOn: ?string, starts: list<float>}> */
    private function readManifest(string $file): array
    {
        $decoded = is_file($file) ? json_decode((string) file_get_contents($file), true) : [];
        $films = array_filter(\is_array($decoded) ? $decoded : [], static fn (mixed $film): bool => \is_array($film) && isset($film['version']));

        return array_map(static fn (array $film): array => [
            ...$film,
            'narrated' => (bool) ($film['narrated'] ?? false),
            'shotOn' => \is_string($film['shotOn'] ?? null) && '' !== $film['shotOn'] ? $film['shotOn'] : null,
            'starts' => array_values((array) ($film['starts'] ?? [])),
        ], $films);
    }
}
