<?php
/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\SiteBundle\Tests\Management;

use c975L\ConfigBundle\Service\ConfigServiceInterface;
use c975L\SiteBundle\Management\SiteGuidedProjectProvider;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGeneratorInterface;
use PHPUnit\Framework\TestCase;

class SiteGuidedProjectProviderTest extends TestCase
{
    private function createAdminUrlGenerator(array &$controllers = []): AdminUrlGeneratorInterface
    {
        $generator = $this->createStub(AdminUrlGeneratorInterface::class);
        $generator->method('unsetAll')->willReturnSelf();
        $generator->method('setController')->willReturnCallback(function (string $controller) use ($generator, &$controllers) {
            $controllers[] = $controller;

            return $generator;
        });
        $generator->method('setAction')->willReturnSelf();
        $generator->method('generateUrl')->willReturn('/management/x');

        return $generator;
    }

    private function createConfigService(): ConfigServiceInterface
    {
        $configService = $this->createStub(ConfigServiceInterface::class);
        $configService->method('get')->willReturn('ROLE_EDITOR');

        return $configService;
    }

    private function createProvider(array &$controllers = []): SiteGuidedProjectProvider
    {
        return new SiteGuidedProjectProvider($this->createAdminUrlGenerator($controllers), $this->createConfigService());
    }

    public function testGetGuidedProjectsReturnsFourProjectsContinuingConfigBundlesOrderSequence(): void
    {
        $projects = $this->createProvider()->getGuidedProjects();

        $this->assertSame(
            ['site-page-creation', 'site-page-menu', 'site-collection', 'site-page-revision'],
            array_column($projects, 'slug')
        );
        $this->assertSame([50, 60, 70, 80], array_column($projects, 'order'));
    }

    public function testEverySlugIsPrefixedWithTheBundleName(): void
    {
        foreach ($this->createProvider()->getGuidedProjects() as $project) {
            $this->assertStringStartsWith('site-', $project['slug'], 'A slug is unique across every bundle contributing projects');
        }
    }

    // Every screen these projects walk sits behind "site-role-editor", so an admin lacking it is never offered the parcours
    public function testEveryProjectIsGatedByTheEditorRole(): void
    {
        foreach ($this->createProvider()->getGuidedProjects() as $project) {
            $this->assertSame('ROLE_EDITOR', $project['role'] ?? null, sprintf('Project "%s" walks screens the current user may not reach', $project['slug']));
        }
    }

    public function testEveryProjectCarriesTheSiteTranslationDomainAndSteps(): void
    {
        foreach ($this->createProvider()->getGuidedProjects() as $project) {
            $this->assertSame('site', $project['translation_domain']);
            $this->assertNotEmpty($project['steps']);
        }
    }

    public function testNoStepSetsBothUrlAndHighlight(): void
    {
        foreach ($this->createProvider()->getGuidedProjects() as $project) {
            foreach ($project['steps'] as $index => $step) {
                $this->assertFalse(
                    isset($step['url']) && isset($step['highlight']),
                    sprintf('Step %d of "%s" sets both url and highlight', $index, $project['slug'])
                );
            }
        }
    }

    // Only the opening step leaves the screen, everything after it walking the one the user has been sent to
    public function testOnlyTheFirstStepOfEachProjectCarriesAnUrl(): void
    {
        foreach ($this->createProvider()->getGuidedProjects() as $project) {
            $steps = $project['steps'];

            $this->assertArrayHasKey('url', $steps[0], sprintf('Project "%s" does not open on a screen', $project['slug']));

            foreach (array_slice($steps, 1) as $index => $step) {
                $this->assertArrayNotHasKey('url', $step, sprintf('Step %d of "%s" leaves the screen again', $index + 1, $project['slug']));
            }
        }
    }

    public function testProjectsOpenOnTheirOwnCrudIndex(): void
    {
        $controllers = [];
        $this->createProvider($controllers)->getGuidedProjects();

        $this->assertSame(
            ['PageCrudController', 'MenuCrudController', 'CollectionCrudController', 'PageCrudController'],
            array_map(static fn (string $fqcn): string => basename(str_replace('\\', '/', $fqcn)), $controllers)
        );
    }

    // A label or description with no translation reads as its own key in the panel
    public function testEveryLabelAndDescriptionIsTranslated(): void
    {
        $translated = $this->translatedKeys();

        foreach ($this->createProvider()->getGuidedProjects() as $project) {
            foreach ([$project, ...$project['steps']] as $item) {
                $this->assertContains($item['label'], $translated);
                if (isset($item['description'])) {
                    $this->assertContains($item['description'], $translated);
                }
            }
        }
    }

    // A step highlighting a selector nothing renders points the user at nothing at all
    public function testEveryActionHighlightNamesARenderedAction(): void
    {
        $rendered = $this->renderedActionNames();

        foreach ($this->createProvider()->getGuidedProjects() as $project) {
            foreach ($project['steps'] as $index => $step) {
                if (!preg_match('/^\.action-(\w+)$/', $step['highlight'] ?? '', $matches)) {
                    continue;
                }

                $this->assertContains(
                    $matches[1],
                    $rendered,
                    sprintf(
                        'Step %d of "%s" highlights ".action-%s", which no rendered action carries. EasyAdmin builds that class from the action\'s own name (Action::SAVE_AND_RETURN is "saveAndReturn", not "save"), and gives it to an ActionDto alone - an ActionGroup has to state its own through setCssClass().',
                        $index,
                        $project['slug'],
                        $matches[1]
                    )
                );
            }
        }
    }

    /**
     * EasyAdmin's own actions, this bundle's custom ones, and the groups stating a class of their own
     *
     * @return string[]
     */
    private function renderedActionNames(): array
    {
        $names = [];
        foreach ((new \ReflectionClass(Action::class))->getConstants() as $constant => $value) {
            if (\is_string($value) && !str_starts_with($constant, 'TYPE_')) {
                $names[] = $value;
            }
        }

        $controllers = new \DirectoryIterator(\dirname(__DIR__, 2) . '/src/Controller/Management');
        foreach ($controllers as $controller) {
            if ($controller->isDot() || 'php' !== $controller->getExtension()) {
                continue;
            }

            $source = (string) file_get_contents($controller->getPathname());
            preg_match_all('/Action::new\(\s*\'(\w+)\'/', $source, $custom);
            preg_match_all('/setCssClass\(\s*\'action-(\w+)\'/', $source, $groups);
            $names = [...$names, ...$custom[1], ...$groups[1]];
        }

        return array_unique($names);
    }

    private function translatedKeys(): array
    {
        $xliff = new \DOMDocument();
        $xliff->load(\dirname(__DIR__, 2) . '/translations/site.fr.xlf');

        $keys = [];
        foreach ($xliff->getElementsByTagName('source') as $source) {
            $keys[] = $source->textContent;
        }

        return $keys;
    }
}
