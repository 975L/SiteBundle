<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\SiteBundle\Tests\Command;

use c975L\ConfigBundle\Entity\Config;
use c975L\ConfigBundle\Repository\ConfigRepository;
use c975L\SiteBundle\Command\SiteContentAdoptConfigTextsCommand;
use c975L\SiteBundle\Entity\Menu;
use c975L\SiteBundle\Repository\MenuRepository;
use c975L\UiBundle\Entity\Block;
use c975L\UiBundle\Entity\EmailBlock;
use c975L\UiBundle\Entity\EmailTemplate;
use c975L\UiBundle\Repository\EmailTemplateRepository;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;

// What an admin wrote in the configuration is what the template keeps - the declaration only ever provides what a site starts from
class SiteContentAdoptConfigTextsCommandTest extends TestCase
{
    private array $removed = [];

    protected function setUp(): void
    {
        $this->removed = [];
    }

    // The whole point: the site's own sentence replaces the seeded one, and the entry goes
    public function testTheSitesOwnWordingReplacesTheSeededOne(): void
    {
        $template = $this->createTemplate('layout_hello', 'Bonjour,');
        $config = $this->createConfig('email-text-hello', '<p>Salut !</p>');

        $this->migrate([$config], ['layout_hello' => $template]);

        $this->assertSame('<p>Salut !</p>', $template->getBlocks()->first()->getContent());
        $this->assertSame([$config], $this->removed);
    }

    // The layout used to substitute "%site%" itself; a template resolves "{{ site }}" at render time, so the placeholder changes syntax on the way
    public function testThePlaceholderIsRewrittenToTheTemplateSyntax(): void
    {
        $template = $this->createTemplate('layout_sent_by', 'Envoyé par {{ site }}.');

        $this->migrate([$this->createConfig('email-text-sent-by', 'Cet email vous a été envoyé par %site%.')], ['layout_sent_by' => $template]);

        $this->assertSame('Cet email vous a été envoyé par {{ site }}.', $template->getBlocks()->first()->getContent());
    }

    // An entry nobody ever filled leaves the template with the wording the declaration gave it
    public function testAnEmptyEntryLeavesTheSeededWordingAlone(): void
    {
        $template = $this->createTemplate('layout_hello', 'Bonjour,');
        $config = $this->createConfig('email-text-hello', '   ');

        $this->migrate([$config], ['layout_hello' => $template]);

        $this->assertSame('Bonjour,', $template->getBlocks()->first()->getContent());
        $this->assertSame([$config], $this->removed);
    }

    // The one case where nothing may be deleted: the templates have not been seeded yet, so there is nowhere to put the text
    public function testATextWithNowhereToGoIsLeftInPlaceAndReported(): void
    {
        $config = $this->createConfig('email-text-hello', '<p>Salut !</p>');

        $tester = $this->migrate([$config], []);

        $this->assertSame([], $this->removed);
        $this->assertStringContainsString('email-text-hello', $tester->getDisplay());
        $this->assertStringContainsString('nowhere to go', $tester->getDisplay());
    }

    // Deployed twice, it writes once - the second run finds no entry left at all
    public function testASecondRunHasNothingToDo(): void
    {
        $tester = $this->migrate([], ['layout_hello' => $this->createTemplate('layout_hello', 'Bonjour,')]);

        $this->assertStringContainsString('Nothing to move', $tester->getDisplay());
    }

    // A template seeded without a block still receives the text rather than dropping it
    public function testATemplateWithNoBlockGainsOne(): void
    {
        $template = new EmailTemplate()->setName('layout_hello')->setLocale('fr');

        $this->migrate([$this->createConfig('email-text-hello', '<p>Salut !</p>')], ['layout_hello' => $template]);

        $this->assertCount(1, $template->getBlocks());
        $this->assertSame('<p>Salut !</p>', $template->getBlocks()->first()->getContent());
    }

    // The tagline is the one move that never strands: the menu it writes into is created when the site has none
    public function testTheTaglineBecomesTheFirstBlockOfTheBrandMenu(): void
    {
        $config = $this->createConfig('site-tagline', '<div>Des sites qui durent</div>');

        $this->migrate([$config], [], persisted: $persisted);

        $menu = $this->onlyPersisted($persisted, Menu::class);
        $this->assertSame('navbar-brand', $menu->getLocation());
        $this->assertCount(1, $menu->getBlocks());
        $this->assertSame('text_hook', $menu->getBlocks()->first()->getKind());
        $this->assertSame(['text' => '<div>Des sites qui durent</div>'], $menu->getBlocks()->first()->getData());
        $this->assertSame([$config], $this->removed);
    }

    // What an admin has already written under the site's name is not this command's to add to - and the entry stays
    // where it is, being the only copy of a wording that has gone nowhere
    public function testAMenuAlreadyWrittenIsLeftAlone(): void
    {
        $menu = new Menu()->setLocation(Menu::LOCATION_NAVBAR_BRAND)
            ->addBlock(new Block()->setKind('text_hook')->setPosition(1)->setData(['text' => 'Écrit à la main']));
        $config = $this->createConfig('site-tagline', '<div>Des sites qui durent</div>');

        $this->migrate([$config], [], $menu);

        $this->assertCount(1, $menu->getBlocks());
        $this->assertSame(['text' => 'Écrit à la main'], $menu->getBlocks()->first()->getData());
        $this->assertSame([], $this->removed);
    }

    // An entry nobody ever filled creates no menu at all
    public function testAnEmptyTaglineCreatesNothing(): void
    {
        $config = $this->createConfig('site-tagline', '  ');

        $this->migrate([$config], [], persisted: $persisted);

        $this->assertSame([], array_filter($persisted, static fn (object $e): bool => $e instanceof Menu));
        $this->assertSame([$config], $this->removed);
    }

    /**
     * @param list<Config>                 $configs
     * @param array<string, EmailTemplate> $templates name => the row in the site's own language
     * @param list<object>|null            $persisted filled with what the command handed to Doctrine
     */
    private function migrate(array $configs, array $templates, ?Menu $menu = null, ?array &$persisted = null): CommandTester
    {
        $persisted = [];
        $configRepository = $this->createStub(ConfigRepository::class);
        $configRepository->method('findOneBySlug')->willReturnCallback(
            static function (string $slug) use ($configs): ?Config {
                foreach ($configs as $config) {
                    if ($slug === $config->getSlug()) {
                        return $config;
                    }
                }

                return null;
            }
        );

        $emailTemplateRepository = $this->createStub(EmailTemplateRepository::class);
        $emailTemplateRepository->method('findOneBy')->willReturnCallback(
            static fn (array $criteria): ?EmailTemplate => $templates[$criteria['name']] ?? null
        );

        $menuRepository = $this->createStub(MenuRepository::class);
        $menuRepository->method('findOneByLocation')->willReturn($menu);

        $entityManager = $this->createStub(EntityManagerInterface::class);
        $entityManager->method('remove')->willReturnCallback(function (object $entity): void {
            $this->removed[] = $entity;
        });
        $entityManager->method('persist')->willReturnCallback(static function (object $entity) use (&$persisted): void {
            $persisted[] = $entity;
        });

        $tester = new CommandTester(new SiteContentAdoptConfigTextsCommand(
            $configRepository,
            $emailTemplateRepository,
            $menuRepository,
            $entityManager,
            'fr',
        ));
        $tester->execute([]);

        return $tester;
    }

    private function createTemplate(string $name, string $seeded): EmailTemplate
    {
        return new EmailTemplate()
            ->setName($name)
            ->setLocale('fr')
            ->addBlock(new EmailBlock()->setType(EmailBlock::TYPE_TEXT)->setPosition(0)->setContent($seeded));
    }

    /**
     * @param list<object> $persisted
     *
     * @template T of object
     *
     * @param class-string<T> $class
     *
     * @return T
     */
    private function onlyPersisted(array $persisted, string $class): object
    {
        $found = array_values(array_filter($persisted, static fn (object $entity): bool => $entity instanceof $class));
        $this->assertCount(1, $found);

        return $found[0];
    }

    private function createConfig(string $slug, ?string $value): Config
    {
        return new Config()->setSlug($slug)->setValue($value);
    }
}
