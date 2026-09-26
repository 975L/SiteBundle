<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\SiteBundle\Tests\Service;

use c975L\ConfigBundle\Management\GuidedProjectBuilder;
use c975L\SiteBundle\Service\TutorialCatalog;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;

class TutorialCatalogTest extends TestCase
{
    private string $public;

    protected function setUp(): void
    {
        $this->public = sys_get_temp_dir() . '/tutorial-catalog-' . uniqid();
        mkdir($this->public . '/medias/films/fr', 0o775, true);
        file_put_contents($this->public . '/medias/films/fr/films.json', json_encode([
            'site-page-creation' => ['narrated' => true, 'version' => 1758000000, 'starts' => [7.4, 16.4]],
            'config-settings' => ['narrated' => false, 'version' => 1758000000, 'shotAt' => 1757000000, 'starts' => [5.0]],
            'ui-media' => ['narrated' => false, 'version' => 1758000000, 'starts' => [3.0]],
        ]));
    }

    protected function tearDown(): void
    {
        new Filesystem()->remove($this->public);
    }

    private function catalog(): TutorialCatalog
    {
        $step = static fn (string $label): array => ['label' => $label, 'description' => '', 'narration' => '', 'url' => null, 'highlight' => null];
        $builder = $this->createStub(GuidedProjectBuilder::class);
        $builder->method('getAllProjects')->willReturn([
            ['slug' => 'config-settings', 'label' => 'Régler le site', 'description' => '', 'steps' => [$step('Ouvrir'), $step('Enregistrer')]],
            ['slug' => 'site-page-creation', 'label' => 'Créer une page', 'description' => '', 'steps' => [$step('Ouvrir'), $step('Enregistrer')]],
            ['slug' => 'ui-media', 'label' => 'Téléverser une image', 'description' => '', 'steps' => [$step('Téléverser')]],
            ['slug' => 'site-trash', 'label' => 'Vider la corbeille', 'description' => '', 'steps' => [$step('Vider')]],
        ]);

        return new TutorialCatalog($builder, $this->public, 'fr');
    }

    // A project without a film stays off the list, the others keep the guided sequence's order, served from public/medias/films
    public function testOnlyFilmedProjectsShowInTheGuidedOrder(): void
    {
        $tutorials = $this->catalog()->all('fr');

        $this->assertSame(['config-settings', 'site-page-creation', 'ui-media'], array_column($tutorials, 'slug'));
        $this->assertSame('/medias/films/fr/site-page-creation.webm?v=1758000000', $tutorials[1]['video']);
        $this->assertSame('/medias/films/fr/site-page-creation.vtt?v=1758000000', $tutorials[1]['subtitles']);
    }

    // A film says when it was shot rather than when it was published, the publication date standing in when the manifest has no shooting date
    public function testAFilmCarriesItsShootingDate(): void
    {
        $this->assertSame(1757000000, $this->catalog()->find('config-settings', 'fr')['shotAt']);
        $this->assertSame(1758000000, $this->catalog()->find('ui-media', 'fr')['shotAt']);
    }

    // Each step gets the second it starts at, unless the film counts a different number of steps than the project, then none does
    public function testStepsAreTimedOnlyWhenTheFilmMatchesTheProject(): void
    {
        $catalog = $this->catalog();

        $this->assertSame([7.4, 16.4], array_column($catalog->find('site-page-creation', 'fr')['steps'], 'start'));
        $this->assertSame([null, null], array_column($catalog->find('config-settings', 'fr')['steps'], 'start'));
    }

    // A language holding a few films of its own shows those, and the site's own films for the rest
    public function testTheFallbackIsDecidedFilmByFilm(): void
    {
        mkdir($this->public . '/medias/films/en', 0o775, true);
        file_put_contents($this->public . '/medias/films/en/films.json', json_encode([
            'ui-media' => ['narrated' => true, 'version' => 1759000000, 'starts' => [2.0]],
        ]));

        $this->assertSame('en', $this->catalog()->find('ui-media', 'en')['locale']);
        $this->assertSame('fr', $this->catalog()->find('config-settings', 'en')['locale']);
    }

    // Whether a project is filmed is read off the manifests alone, the site's own language standing in for another
    public function testIsFilmedReadsTheManifests(): void
    {
        $this->assertTrue($this->catalog()->isFilmed('ui-media', 'en'));
        $this->assertFalse($this->catalog()->isFilmed('site-trash', 'fr'));
    }

    // A hand-edited manifest never breaks the page: an entry without a version is dropped, the others get their defaults, and a manifest that is no object shows nothing
    public function testABrokenManifestIsNormalized(): void
    {
        file_put_contents($this->public . '/medias/films/fr/films.json', json_encode([
            'site-page-creation' => ['version' => 1758000000],
            'ui-media' => ['narrated' => true, 'starts' => [3.0]],
            'config-settings' => 'broken',
        ]));

        $tutorials = $this->catalog()->all('fr');
        $this->assertSame(['site-page-creation'], array_column($tutorials, 'slug'));
        $this->assertFalse($tutorials[0]['narrated']);
        $this->assertSame([null, null], array_column($tutorials[0]['steps'], 'start'));
        $this->assertFalse($this->catalog()->isFilmed('ui-media', 'fr'));

        file_put_contents($this->public . '/medias/films/fr/films.json', '"broken"');
        $this->assertSame([], $this->catalog()->all('fr'));
    }

    // No film published at all is an empty list, and a project with no film is not found
    public function testNothingPublishedIsNothingShown(): void
    {
        new Filesystem()->remove($this->public . '/medias');

        $this->assertSame([], $this->catalog()->all('fr'));
        $this->assertNull($this->catalog()->find('site-trash', 'fr'));
    }
}
