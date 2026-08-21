<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\SiteBundle\Tests;

use c975L\UiBundle\DependencyInjection\Compiler\BlockRegistryPass;
use c975L\UiBundle\Registry\BlockRegistry;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;
use Symfony\Contracts\Translation\TranslatorInterface;

// A footer stacking its social links above its links puts each set in a "menu_group" - several separate declarations make that possible, and none of them fails loudly on its own
class FooterMenuGroupTest extends TestCase
{
    // The slot context of "menu_group" (see config/services.yaml), a menu's own rather than UiBundle's "flex_slot"
    private const string MENU_SLOT_CONTEXT = 'menu_slot';

    /**
     * @return array<string, array{string}>
     */
    public static function stylesheetProvider(): array
    {
        return [
            'styles.css' => ['styles.css'],
            'styles.min.css' => ['styles.min.css'],
        ];
    }

    // Without its full width the group would just be one more item of the footer's own flex row, which is what it exists to break out of
    #[\PHPUnit\Framework\Attributes\DataProvider('stylesheetProvider')]
    public function testEachStylesheetGivesAFooterGroupItsOwnLine(string $file): void
    {
        $this->assertStringContainsString(
            'footer .menu-items .blocks-group,footer .menu-items .blocks>:has(>.blocks-group){flex:0 0 100%',
            $this->stylesheet($file),
            sprintf('"%s" no longer gives a footer menu group a line of its own.', $file)
        );
    }

    // UiBundle's Block.html.twig wraps a block in a div of its own as soon as it carries an animation or an editor's edit-url, and it is that wrapper the footer's flex row then lays out - the group itself is no longer the item
    #[\PHPUnit\Framework\Attributes\DataProvider('stylesheetProvider')]
    public function testTheRuleReachesTheGroupThroughABlockWrapper(string $file): void
    {
        $this->assertStringContainsString(
            '.blocks>:has(>.blocks-group)',
            $this->stylesheet($file),
            sprintf('"%s" only sizes the group itself, so an animated group loses its line.', $file)
        );
    }

    // "menu_slot" is the group's own slot context: without it a link can be neither picked nor dragged (see BlockMoveController::validateTarget()) into a group
    public function testTheLinkKindIsAllowedInAMenuGroupsSlots(): void
    {
        $this->assertTrue($this->registry()->isAllowedInContext('menu_link', self::MENU_SLOT_CONTEXT));
    }

    // The whole point of a context of its own: a container placed on a Page takes UiBundle's default slot context, where a menu link has no business being offered
    public function testTheLinkKindStaysOutOfAPagesContainers(): void
    {
        $registry = $this->registry();

        $this->assertFalse($registry->isAllowedInContext('menu_link', BlockRegistry::SLOT_CONTEXT));
        $this->assertFalse($registry->isAllowedInContext('menu_link', BlockRegistry::FLEX_COLUMNS_SLOT_CONTEXT));
    }

    // A navbar holds links and nothing else (its context is exclusive), which the grouping must not open up
    public function testANavbarStillOffersLinksAndNothingElse(): void
    {
        $registry = $this->registry();

        $this->assertTrue($registry->isAllowedInContext('menu_link', BlockRegistry::MENU_NAVBAR_CONTEXT));
        $this->assertFalse($registry->isAllowedInContext('menu_group', BlockRegistry::MENU_NAVBAR_CONTEXT));
    }

    // The group is offered where a menu takes any kind (footer, emails), and never inside itself - one level is the whole tree Footer.html.twig walks
    public function testTheGroupIsOfferedInAMenuButNeverInsideItself(): void
    {
        $registry = $this->registry();

        $this->assertTrue($registry->isAllowedInContext('menu_group', BlockRegistry::MENU_CONTEXT));
        $this->assertFalse($registry->isAllowedInContext('menu_group', self::MENU_SLOT_CONTEXT));
    }

    // The links can sit one level down now, and the copyright span would be rendered on top of a "Copyright" link the filter no longer sees
    public function testTheCopyrightCheckLooksIntoTheGroupsToo(): void
    {
        $template = (string) file_get_contents(dirname(__DIR__) . '/templates/components/General/Footer.html.twig');

        $this->assertStringContainsString('block.slots|map(slot => slot)', $template);
        $this->assertStringContainsString('footerItems|filter(', $template);
    }

    // The blocks are cached across requests, and a lazy collection cached uninitialized comes back empty - a group would then render no slot at all, and a block carrying a media would lose its image
    // The slots are preloaded rather than joined since a join only ever answered for the first level of them, a group nested in a group coming back empty just the same (see BlockRepository::preloadSlots)
    public function testTheMenuQueryTakesTheMediasAndPreloadsTheSlots(): void
    {
        $repository = (string) file_get_contents(dirname(__DIR__) . '/src/Repository/MenuRepository.php');

        $this->assertStringContainsString("->select('m, b, bm')", $repository);
        $this->assertStringContainsString("->leftJoin('b.medias', 'bm')", $repository);
        $this->assertStringContainsString('$this->blockRepository->preloadSlots($menu->getBlocks())', $repository);
    }

    // This bundle's real block declarations replayed onto a real registry, as the compiler pass does
    private function registry(): BlockRegistry
    {
        $container = new ContainerBuilder();
        // The registry itself is UiBundle's own service, registered bare here: only the calls the pass adds from this bundle's tags are read back below
        $container->register(BlockRegistry::class, BlockRegistry::class);
        new YamlFileLoader($container, new FileLocator(dirname(__DIR__) . '/config'))->load('services.yaml');
        new BlockRegistryPass()->process($container);

        $translator = $this->createStub(TranslatorInterface::class);
        $translator->method('trans')->willReturnCallback(static fn (string $key) => $key);

        $registry = new BlockRegistry($translator);
        foreach ($container->getDefinition(BlockRegistry::class)->getMethodCalls() as [$method, $arguments]) {
            $registry->{$method}(...$arguments);
        }

        return $registry;
    }

    // Strips comments and collapses whitespace - around ">" as well, the minifier removing it where the expanded sheet keeps it - so the same assertions hold on both sheets
    private function stylesheet(string $file): string
    {
        $path = dirname(__DIR__) . '/public/css/' . $file;
        $this->assertFileExists($path, sprintf('"%s" is missing, the sass has not been compiled.', $file));

        $css = (string) preg_replace('#/\*.*?\*/#s', '', (string) file_get_contents($path));

        return (string) preg_replace('/\s*([{};:,>])\s*/', '$1', $css);
    }
}
