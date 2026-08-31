<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\SiteBundle\Tests\Controller\Management;

use c975L\ConfigBundle\Service\ConfigServiceInterface;
use c975L\SiteBundle\Controller\Management\TranslationController;
use c975L\SiteBundle\Entity\Menu;
use c975L\SiteBundle\Repository\MenuRepository;
use c975L\UiBundle\Entity\Block;
use c975L\UiBundle\Entity\Translation;
use c975L\UiBundle\Registry\BlockRegistry;
use c975L\UiBundle\Service\ContentTranslator;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use Twig\Environment;

class TranslationControllerTest extends TestCase
{
    private ContentTranslator & MockObject $contentTranslator;

    // A menu holds no text of its own: only its items' own labels, and only those an editor set by hand
    private function createMenu(): Menu
    {
        $menu = new Menu();
        $menu->setLocation(Menu::LOCATION_NAVBAR);
        new \ReflectionProperty(Menu::class, 'id')->setValue($menu, 7);

        $link = new Block();
        $link->setKind('menu_link')->setData(['target' => 'page:12', 'label' => 'Nos ateliers']);
        new \ReflectionProperty(Block::class, 'id')->setValue($link, 56);
        $menu->addBlock($link);

        return $menu;
    }

    private function createController(Request $request): TranslationController
    {
        $menuRepository = $this->createStub(MenuRepository::class);
        $menuRepository->method('find')->willReturn($this->createMenu());

        $this->contentTranslator = $this->createMock(ContentTranslator::class);
        $this->contentTranslator->method('getTranslatableLocales')->willReturn(['en']);
        $this->contentTranslator->method('all')->willReturn([]);

        $blockRegistry = $this->createStub(BlockRegistry::class);
        $blockRegistry->method('getTranslatable')->willReturnCallback(
            static fn (string $kind): array => 'menu_link' === $kind ? ['label'] : ['title', 'content']
        );
        $blockRegistry->method('getLabel')->willReturn('Section de texte');

        $configService = $this->createStub(ConfigServiceInterface::class);
        $configService->method('get')->willReturn('ROLE_EDITOR');

        $translator = $this->createStub(TranslatorInterface::class);
        $translator->method('trans')->willReturnArgument(0);

        $controller = new TranslationController(
            $menuRepository,
            $this->contentTranslator,
            $blockRegistry,
            $configService,
            $translator,
        );
        $controller->setContainer($this->createContainer($request));

        return $controller;
    }

    private function createContainer(Request $request): Container
    {
        $authorization = $this->createStub(AuthorizationCheckerInterface::class);
        $authorization->method('isGranted')->willReturn(true);

        $csrf = $this->createStub(CsrfTokenManagerInterface::class);
        $csrf->method('isTokenValid')->willReturn(true);

        $router = $this->createStub(UrlGeneratorInterface::class);
        $router->method('generate')->willReturn('/management/page-translate/12/en');

        $twig = $this->createStub(Environment::class);
        $twig->method('render')->willReturnCallback(static fn (string $name, array $context) => json_encode(array_map(
            static fn (mixed $value) => \is_array($value) ? $value : (string) $value,
            ['rows' => array_column($context['rows'], 'name')],
        ), JSON_THROW_ON_ERROR));

        $container = new Container();
        $container->set('security.authorization_checker', $authorization);
        $container->set('security.csrf.token_manager', $csrf);
        $container->set('router', $router);
        $container->set('twig', $twig);
        $requestStack = new RequestStack([$request]);
        $container->set('request_stack', $requestStack);

        return $container;
    }

    private function createRequest(string $method = 'GET', array $values = []): Request
    {
        $request = Request::create('/management/page-translate/12/en', $method, [] === $values ? [] : ['values' => $values, '_token' => 'valid']);
        $request->setSession(new Session(new MockArraySessionStorage()));

        return $request;
    }

    #[\PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations]
    // One row per label to say again, and nothing for an item taking its label from the page it points at - that one is translated with its page
    public function testAMenuOffersItsOwnLabels(): void
    {
        $request = $this->createRequest();

        $response = $this->createController($request)->menu($request, 7, 'en');

        $this->assertSame(
            ['rows' => ['block_56_label']],
            json_decode((string) $response->getContent(), true, 512, JSON_THROW_ON_ERROR),
        );
    }

    #[\PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations]
    // A language the site does not declare has no screen
    public function testALanguageTheSiteDoesNotDeclareIsNotFound(): void
    {
        $this->expectException(\Symfony\Component\HttpKernel\Exception\NotFoundHttpException::class);

        $request = $this->createRequest();

        $this->createController($request)->menu($request, 7, 'de');
    }

    // What is sent back goes to the menu it belongs to
    public function testWhatIsPostedIsStoredForTheMenusOwnBlocks(): void
    {
        $request = $this->createRequest('POST', ['block_56_label' => 'Contact us']);
        $controller = $this->createController($request);

        $stored = [];
        $this->contentTranslator->expects($this->once())->method('store')
            ->willReturnCallback(static function (string $owner, int $id, string $locale, array $values) use (&$stored): void {
                $stored[$owner . ':' . $id . ':' . $locale] = $values;
            });

        $controller->menu($request, 7, 'en');

        $this->assertSame([Translation::OWNER_BLOCK . ':56:en' => ['label' => 'Contact us']], $stored);
    }
}
