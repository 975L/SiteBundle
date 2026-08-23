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
use c975L\SiteBundle\Entity\Menu;
use c975L\SiteBundle\Management\SiteGuidedProjectProvider;
use c975L\UiBundle\Service\BlockMoveRowAttrBuilder;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\EasyAdminBundle;
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

    // One value per key rather than a single one for all of them: the projects no longer share a role, and a stub answering the same thing everywhere would let a project ask for the wrong one unnoticed
    private function createConfigService(): ConfigServiceInterface
    {
        $configService = $this->createStub(ConfigServiceInterface::class);
        $configService->method('get')->willReturnCallback(static fn (string $key): string => match ($key) {
            'site-role-editor' => 'ROLE_EDITOR',
            'site-role-admin' => 'ROLE_ADMIN',
            default => throw new \InvalidArgumentException(sprintf('Unexpected config key "%s"', $key)),
        });

        return $configService;
    }

    private function createProvider(array &$controllers = []): SiteGuidedProjectProvider
    {
        return new SiteGuidedProjectProvider($this->createAdminUrlGenerator($controllers), $this->createConfigService());
    }

    // The sequence follows the sidebar's own reading order (Collections, Pages, then the advanced "Menus"), so a project sits where the user finds the screen it walks - and the ones sharing the pages follow the order a page lives: created, made findable, checked, then reworked
    public function testGetGuidedProjectsReturnsNineProjectsContinuingConfigBundlesOrderSequence(): void
    {
        $projects = $this->createProvider()->getGuidedProjects();

        $this->assertSame(
            ['site-collection', 'site-page-creation', 'site-page-seo', 'site-page-health', 'site-page-revision', 'site-trash', 'site-content-export', 'site-page-menu', 'site-footer'],
            array_column($projects, 'slug')
        );
        $this->assertSame([2010, 2020, 2030, 2040, 2050, 2060, 2070, 2080, 2090], array_column($projects, 'order'));
    }

    // Orders are merged across every bundle contributing projects, and two equal ones leave their sequence to the order the providers happen to be registered in - this bundle's own block is the 2000 GuidedProjectProviderInterface reserves it
    public function testEveryOrderStaysWithinThisBundlesReservedRange(): void
    {
        $orders = array_column($this->createProvider()->getGuidedProjects(), 'order', 'slug');

        foreach ($orders as $slug => $order) {
            $this->assertGreaterThanOrEqual(2000, $order, sprintf('Project "%s" reaches below this bundle\'s own 2000 block', $slug));
            $this->assertLessThanOrEqual(2999, $order, sprintf('Project "%s" reaches into UiBundle\'s own 3000 block', $slug));
        }

        $this->assertSameSize($orders, array_unique($orders), 'Two projects sharing an order leave their sequence to chance');
    }

    public function testEverySlugIsPrefixedWithTheBundleName(): void
    {
        foreach ($this->createProvider()->getGuidedProjects() as $project) {
            $this->assertStringStartsWith('site-', $project['slug'], 'A slug is unique across every bundle contributing projects');
        }
    }

    // A project is gated by the strictest role any of its steps needs: emptying the trash and exporting content are "site-role-admin" (see PageCrudController), everything else the editor's own screens
    public function testEveryProjectIsGatedByTheRoleItsOwnScreensNeed(): void
    {
        $expected = [
            'site-collection' => 'ROLE_EDITOR',
            'site-page-creation' => 'ROLE_EDITOR',
            'site-page-seo' => 'ROLE_EDITOR',
            'site-page-health' => 'ROLE_EDITOR',
            'site-page-revision' => 'ROLE_EDITOR',
            'site-trash' => 'ROLE_ADMIN',
            'site-content-export' => 'ROLE_ADMIN',
            'site-page-menu' => 'ROLE_EDITOR',
            'site-footer' => 'ROLE_EDITOR',
        ];

        foreach ($this->createProvider()->getGuidedProjects() as $project) {
            $this->assertSame($expected[$project['slug']], $project['role'] ?? null, sprintf('Project "%s" walks screens the current user may not reach', $project['slug']));
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
            ['CollectionCrudController', 'PageCrudController', 'PageCrudController', 'PageCrudController', 'PageCrudController', 'PageCrudController', 'PageCrudController', 'MenuCrudController', 'MenuCrudController'],
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

    // The two menu projects walk the very same screen, told apart by the location they create - a footer parcours opening the navbar would silently teach the wrong menu
    public function testEachMenuProjectHighlightsItsOwnLocationButton(): void
    {
        $highlights = [];
        foreach ($this->createProvider()->getGuidedProjects() as $project) {
            $highlights[$project['slug']] = array_column($project['steps'], 'highlight');
        }

        $this->assertContains(sprintf('button[name="location"][value="%s"]', Menu::LOCATION_NAVBAR), $highlights['site-page-menu']);
        $this->assertContains(sprintf('button[name="location"][value="%s"]', Menu::LOCATION_FOOTER), $highlights['site-footer']);
    }

    // "style" is offered on the footer alone (see MenuCrudController::configureFields()), so only the footer parcours may point at it
    public function testOnlyTheFooterProjectHighlightsTheFooterOnlyStyleField(): void
    {
        foreach ($this->createProvider()->getGuidedProjects() as $project) {
            $highlights = array_column($project['steps'], 'highlight');

            if ('site-footer' === $project['slug']) {
                $this->assertContains('#Menu_style' . self::TOM_SELECT_SUFFIX, $highlights);

                continue;
            }

            $this->assertNotContains('#Menu_style' . self::TOM_SELECT_SUFFIX, $highlights, sprintf('Project "%s" points at a field only the footer renders', $project['slug']));
        }
    }

    // The one highlighted id this bundle does not render itself: it belongs to EasyAdmin's index template, so an upgrade renaming it would leave the export step pointing at nothing, silently
    public function testTheBatchCheckboxIdStillExistsInEasyAdminsIndexTemplate(): void
    {
        $easyAdminRoot = \dirname((string) new \ReflectionClass(EasyAdminBundle::class)->getFileName(), 2);
        $template = (string) file_get_contents($easyAdminRoot . '/templates/crud/index.html.twig');

        $this->assertStringContainsString('id="form-batch-checkbox-all"', $template);
    }

    /**
     * The CRUD controller each entity's form ids come from - "CollectionGroup" rows are edited by CollectionCrudController, so the id prefix alone doesn't give the file away.
     *
     * @var array<string, string>
     */
    private const array FIELD_CONTROLLERS = [
        'Page' => 'PageCrudController',
        'Menu' => 'MenuCrudController',
        'CollectionGroup' => 'CollectionCrudController',
    ];

    // The suffix a field wrapped by TomSelect has to carry, the original control being clipped to a pixel once the widget replaces it
    private const string TOM_SELECT_SUFFIX = ' + .ts-wrapper';

    // EasyAdmin builds a form field's id off the entity and the property, so a renamed or removed property leaves the step pointing at nothing - and a form the user never sees fails silently, unlike a route
    public function testEveryFieldHighlightNamesAPropertyItsControllerStillDeclares(): void
    {
        foreach ($this->fieldHighlights() as [$project, $index, $entity, $property, $highlight]) {
            $this->assertArrayHasKey($entity, self::FIELD_CONTROLLERS, sprintf('Step %d of "%s" names an entity no controller of this map edits', $index, $project));

            $this->assertArrayHasKey(
                $property,
                $this->declaredFields(self::FIELD_CONTROLLERS[$entity]),
                sprintf('Step %d of "%s" highlights "%s", which %s no longer declares', $index, $project, $highlight, self::FIELD_CONTROLLERS[$entity])
            );
        }
    }

    // A ChoiceField takes EasyAdmin's autocomplete widget unless it asks for the native or the expanded one (see ChoiceConfigurator), and TomSelect then hides the original select behind ".ts-hidden-accessible" - clipped to a pixel, so a step pointing at its id outlines nothing the user can see. The wrapper TomSelect inserts right after it is what shows
    public function testEveryChoiceFieldHighlightPointsAtTheWidgetAndNotAtTheClippedSelect(): void
    {
        foreach ($this->fieldHighlights() as [$project, $index, $entity, $property, $highlight]) {
            $isChoice = 'ChoiceField' === $this->declaredFields(self::FIELD_CONTROLLERS[$entity])[$property];
            $message = sprintf('Step %d of "%s" highlights "%s"', $index, $project, $highlight);

            if ($isChoice) {
                $this->assertStringEndsWith(self::TOM_SELECT_SUFFIX, $highlight, $message . ', a ChoiceField whose select TomSelect clips - point at the wrapper instead');

                continue;
            }

            $this->assertStringEndsNotWith(self::TOM_SELECT_SUFFIX, $highlight, $message . ', which is no ChoiceField and therefore has no TomSelect wrapper next to it');
        }
    }

    // The rule above is only worth its run while at least one field it governs is actually pointed at
    public function testAChoiceFieldIsStillWalkedByAProject(): void
    {
        $choices = [];
        foreach ($this->fieldHighlights() as [, , $entity, $property, $highlight]) {
            if ('ChoiceField' === ($this->declaredFields(self::FIELD_CONTROLLERS[$entity] ?? '')[$property] ?? null)) {
                $choices[] = $highlight;
            }
        }

        $this->assertNotEmpty($choices, 'No step points at a ChoiceField any more, so the TomSelect rule above asserts nothing');
    }

    /**
     * Every step pointing at a form field, as [project slug, step index, entity, property, selector] - the TomSelect suffix left on the selector and stripped off the property.
     *
     * @return list<array{0: string, 1: int, 2: string, 3: string, 4: string}>
     */
    private function fieldHighlights(): array
    {
        $highlights = [];
        foreach ($this->createProvider()->getGuidedProjects() as $project) {
            foreach ($project['steps'] as $index => $step) {
                $highlight = $step['highlight'] ?? '';
                if (!preg_match('/^#(\w+)_(\w+)(?: \+ \.[\w-]+)?$/', $highlight, $matches)) {
                    continue;
                }

                $highlights[] = [$project['slug'], $index, $matches[1], $matches[2], $highlight];
            }
        }

        return $highlights;
    }

    // The QR code widget is rendered by this bundle's own form theme block, which prints no id - the row's marker is the only thing the step can point at, and it lives in the controller
    public function testTheQrCodeStepPointsAtTheMarkerTheControllerSets(): void
    {
        $source = $this->controllerSource('PageCrudController');

        $this->assertStringContainsString("'data-page-qrcode'", $source, 'The marker is set nowhere in PageCrudController, so the QR code row no longer carries it');
        $this->assertStringContainsString('[data-page-qrcode]', $this->highlightsOf('site-page-health'));
    }

    // A collection field is pointed at through the row marker its controller sets, never through an id: EasyAdmin's collection_row merges its markers into row_attr and renders form_row, which writes no id at all - a "#Page_blocks" step would highlight nothing, silently
    public function testTheBlockCollectionStepsPointAtTheMarkerTheControllersSet(): void
    {
        foreach (['PageCrudController', 'MenuCrudController'] as $controller) {
            $this->assertStringContainsString('blockMoveRowAttrBuilder->build(', $this->controllerSource($controller), sprintf('%s no longer marks its blocks collection, so the row carries no selector to point at', $controller));
        }

        foreach (['site-page-creation', 'site-page-revision', 'site-page-menu', 'site-footer'] as $slug) {
            $this->assertStringContainsString(sprintf('[data-ui-sort-group="%s"]', BlockMoveRowAttrBuilder::GROUP), $this->highlightsOf($slug));
        }
    }

    // The health check step points at the last tab rather than at its id, EasyAdmin building that id off the translated label - so the tab has to stay the last one declared
    public function testTheHealthCheckTabIsStillTheLastTabDeclared(): void
    {
        preg_match_all('/FormField::addTab\(\s*t\(\s*\'([\w.]+)\'/', $this->controllerSource('PageCrudController'), $tabs);

        $this->assertNotEmpty($tabs[1], 'PageCrudController no longer lays its form out in tabs, and the positional selector below has nothing to count');
        $this->assertSame('label.tab_health_check', end($tabs[1]));
        $this->assertStringContainsString('.form-tabs-tablist .nav-item:last-child .nav-link', $this->highlightsOf('site-page-health'));
    }

    // The highlights of one project, as a single string to search
    private function highlightsOf(string $slug): string
    {
        foreach ($this->createProvider()->getGuidedProjects() as $project) {
            if ($slug === $project['slug']) {
                return implode(' ', array_column($project['steps'], 'highlight'));
            }
        }

        $this->fail(sprintf('No project named "%s"', $slug));
    }

    /**
     * Every property a controller's configureFields() names, mapped onto the field class declaring it.
     *
     * @return array<string, string>
     */
    private function declaredFields(string $controller): array
    {
        if ('' === $controller) {
            return [];
        }

        preg_match_all('/(\w*Field)::new\(\s*\'(\w+)\'/', $this->controllerSource($controller), $fields, PREG_SET_ORDER);

        return array_column($fields, 1, 2);
    }

    private function controllerSource(string $controller): string
    {
        return (string) file_get_contents(sprintf('%s/src/Controller/Management/%s.php', \dirname(__DIR__, 2), $controller));
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
     * EasyAdmin's own actions, this bundle's custom ones, and the groups stating a class of their own.
     *
     * @return string[]
     */
    private function renderedActionNames(): array
    {
        $names = [];
        foreach (new \ReflectionClass(Action::class)->getConstants() as $constant => $value) {
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
