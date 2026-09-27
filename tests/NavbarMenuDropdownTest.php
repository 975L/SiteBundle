<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\SiteBundle\Tests;

use c975L\SiteBundle\Form\Block\MenuDropdownType;
use c975L\UiBundle\DependencyInjection\Compiler\BlockRegistryPass;
use c975L\UiBundle\Registry\BlockRegistry;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;
use Symfony\Component\Form\Extension\Validator\ValidatorExtension;
use Symfony\Component\Form\Forms;
use Symfony\Component\Validator\Validation;
use Symfony\Contracts\Translation\TranslatorInterface;

// The "menu_dropdown" block: offered in a navbar alone, holding links the way a "menu_group" does, drawn as a dropdown on a desktop
class NavbarMenuDropdownTest extends TestCase
{
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

    /**
     * @return array<string, array{string}>
     */
    public static function dropdownTemplateProvider(): array
    {
        return [
            'block' => ['blocks/MenuDropdown.html.twig'],
            'languages' => ['components/General/Languages.html.twig'],
        ];
    }

    // UiBundle's menu.js closes the burger on a click on any "menu-link": the title carrying the class would fold the whole menu on a phone the moment it is touched
    #[\PHPUnit\Framework\Attributes\DataProvider('dropdownTemplateProvider')]
    public function testTheTitleIsNotAMenuLink(string $template): void
    {
        $source = (string) file_get_contents(dirname(__DIR__) . '/templates/' . $template);

        $this->assertMatchesRegularExpression('/<summary class="menu-dropdown__summary"/', $source);
        $this->assertDoesNotMatchRegularExpression('/<summary[^>]*menu-link/', $source);
    }

    // A navbar is an exclusive context: the dropdown has to opt into it to be offered there at all, and only there - a footer or a Page has no use for it
    public function testTheDropdownIsOfferedInANavbarAlone(): void
    {
        $registry = $this->registry();

        $this->assertTrue($registry->isAllowedInContext('menu_dropdown', BlockRegistry::MENU_NAVBAR_CONTEXT));
        $this->assertFalse($registry->isAllowedInContext('menu_dropdown', BlockRegistry::MENU_CONTEXT));
        $this->assertFalse($registry->isAllowedInContext('menu_dropdown', BlockRegistry::SLOT_CONTEXT));
    }

    // Its slots are the menu group's own context, so a link goes into it the same way, and a dropdown can't be put into another one
    public function testTheDropdownHoldsLinksAndNoOtherContainer(): void
    {
        $registry = $this->registry();

        $this->assertTrue($registry->isContainer('menu_dropdown'));
        $this->assertSame('menu_slot', $registry->getSlotContext('menu_dropdown'));
        $this->assertTrue($registry->isAllowedInContext('menu_link', 'menu_slot'));
        $this->assertFalse($registry->isAllowedInContext('menu_dropdown', 'menu_slot'));
    }

    // The menus saved before it keep their kinds as they were: the group stays out of a navbar
    public function testTheMenuGroupIsLeftAsItWas(): void
    {
        $registry = $this->registry();

        $this->assertFalse($registry->isAllowedInContext('menu_group', BlockRegistry::MENU_NAVBAR_CONTEXT));
        $this->assertTrue($registry->isAllowedInContext('menu_group', BlockRegistry::MENU_CONTEXT));
    }

    // A title is required, the visibility falling back to everyone as a link's does
    public function testTheFormAsksForATitleAndDefaultsToEveryone(): void
    {
        $factory = Forms::createFormFactoryBuilder()->addExtension(new ValidatorExtension(Validation::createValidator()))->getFormFactory();

        $empty = $factory->create(MenuDropdownType::class)->submit([]);
        $this->assertFalse($empty->isValid());
        $this->assertSame('all', $empty->get('visibility')->getData());

        $filled = $factory->create(MenuDropdownType::class)->submit(['label' => 'My account', 'visibility' => 'members']);
        $this->assertTrue($filled->isValid());
    }

    // On a desktop the links hang under their title instead of pushing the bar down
    #[\PHPUnit\Framework\Attributes\DataProvider('stylesheetProvider')]
    public function testEachStylesheetHangsTheLinksUnderTheTitle(string $file): void
    {
        $css = $this->stylesheet($file);

        $this->assertMatchesRegularExpression('/\.menu \.menu-dropdown\{position:relative;?\}/', $css);
        $this->assertMatchesRegularExpression('/\.menu \.menu-dropdown__items\{position:absolute;top:100%/', $css);
    }

    private function registry(): BlockRegistry
    {
        $container = new ContainerBuilder();
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

    private function stylesheet(string $file): string
    {
        $path = dirname(__DIR__) . '/public/css/' . $file;
        $this->assertFileExists($path);

        $css = (string) preg_replace('#/\*.*?\*/#s', '', (string) file_get_contents($path));

        return (string) preg_replace('/\s*([{};:,>])\s*/', '$1', $css);
    }
}
