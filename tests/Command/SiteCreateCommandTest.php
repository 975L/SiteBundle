<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\SiteBundle\Tests\Command;

use c975L\ConfigBundle\Management\LinkableRouteRegistry;
use c975L\ConfigBundle\Repository\ConfigRepository;
use c975L\ConfigBundle\Service\ConfigServiceInterface;
use c975L\ConfigBundle\Service\VaultEncryptor;
use c975L\SiteBundle\Command\SiteCreateCommand;
use c975L\SiteBundle\Repository\MenuRepository;
use c975L\SiteBundle\Repository\PageRepository;
use c975L\SiteBundle\Service\DefaultPagesImporter;
use c975L\SiteBundle\Service\ScaffoldInstaller;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class SiteCreateCommandTest extends TestCase
{
    private string $projectDir;

    protected function setUp(): void
    {
        $this->projectDir = sys_get_temp_dir() . '/c975l-site-create-test-' . uniqid();
        mkdir($this->projectDir . '/src/Entity', 0775, true);
    }

    protected function tearDown(): void
    {
        // Only ever holds the few files each test writes below, so a flat cleanup is enough
        foreach (['/src/Entity/User.php', '/.c975l-site-created'] as $file) {
            if (is_file($this->projectDir . $file)) {
                unlink($this->projectDir . $file);
            }
        }
        rmdir($this->projectDir . '/src/Entity');
        rmdir($this->projectDir . '/src');
        rmdir($this->projectDir);
    }

    private function createCommand(?EntityManagerInterface $em = null, ?UserPasswordHasherInterface $passwordHasher = null): SiteCreateCommand
    {
        return new SiteCreateCommand(
            $this->createStub(ScaffoldInstaller::class),
            $this->createStub(ConfigRepository::class),
            $this->createStub(ConfigServiceInterface::class),
            $this->createStub(VaultEncryptor::class),
            $em ?? $this->createStub(EntityManagerInterface::class),
            $passwordHasher ?? $this->createStub(UserPasswordHasherInterface::class),
            $this->createStub(DefaultPagesImporter::class),
            $this->createStub(PageRepository::class),
            $this->createStub(MenuRepository::class),
            $this->createStub(LinkableRouteRegistry::class),
            $this->createStub(TranslatorInterface::class),
            $this->projectDir,
        );
    }

    // The guard must read the file on disk: App\Entity\User is autoloadable here, so class_exists() would wrongly pass
    public function testExecuteFailsWhenUserEntityFileIsMissingEvenThoughTheClassIsAutoloadable(): void
    {
        $this->assertTrue(class_exists(\App\Entity\User::class), 'Sanity check: App\Entity\User is autoloadable in this test suite');
        $tester = new CommandTester($this->createCommand());

        $statusCode = $tester->execute([]);

        $this->assertSame(Command::FAILURE, $statusCode);
        $this->assertStringContainsString('App\Entity\User introuvable', $tester->getDisplay());
    }

    // Lock file present: the wizard refuses to replay itself over an already-created site
    public function testExecuteFailsWhenSiteHasAlreadyBeenCreated(): void
    {
        file_put_contents($this->projectDir . '/src/Entity/User.php', '<?php');
        file_put_contents($this->projectDir . '/.c975l-site-created', '');
        $tester = new CommandTester($this->createCommand());

        $statusCode = $tester->execute([]);

        $this->assertSame(Command::FAILURE, $statusCode);
        $this->assertStringContainsString('Ce site a déjà été créé', $tester->getDisplay());
    }

    // No confirmation prompt, the password being echoed; the stream's third answer must stay unread
    public function testCreateAdminUserDoesNotAskForPasswordConfirmation(): void
    {
        $persisted = [];
        $userRepository = $this->createStub(EntityRepository::class);
        $userRepository->method('findOneBy')->willReturn(null);
        $em = $this->createStub(EntityManagerInterface::class);
        $em->method('getRepository')->willReturn($userRepository);
        $em->method('persist')->willReturnCallback(function (object $entity) use (&$persisted): void {
            $persisted[] = $entity;
        });
        $passwordHasher = $this->createStub(UserPasswordHasherInterface::class);
        $passwordHasher->method('hashPassword')->willReturn('hashed-password');

        [$email, $password] = $this->callCreateAdminUser(
            $this->createCommand($em, $passwordHasher),
            "admin@example.com\nsecret1234\nsecret1234\n",
            $display
        );

        $this->assertSame('admin@example.com', $email);
        $this->assertSame('secret1234', $password);
        $this->assertStringNotContainsString('Confirmer', $display);
        $this->assertCount(1, $persisted);
        $this->assertSame('hashed-password', $persisted[0]->getPassword());
        $this->assertTrue($persisted[0]->isVerified());
        $this->assertTrue($persisted[0]->isEnabled());
        // Every backoffice role, ROLE_EDITOR included: no role_hierarchy is shipped, so ROLE_ADMIN alone wouldn't pass the "site-role-editor" gated actions
        $this->assertSame(['ROLE_EDITOR', 'ROLE_ADMIN', 'ROLE_SUPER_ADMIN', 'ROLE_USER'], $persisted[0]->getRoles());
    }

    // Existing email: no password is ever asked, the creation is skipped
    public function testCreateAdminUserSkipsCreationWhenTheEmailAlreadyExists(): void
    {
        $userRepository = $this->createStub(EntityRepository::class);
        $userRepository->method('findOneBy')->willReturn(new \App\Entity\User());
        $em = $this->createStub(EntityManagerInterface::class);
        $em->method('getRepository')->willReturn($userRepository);

        [$email, $password] = $this->callCreateAdminUser($this->createCommand($em), "admin@example.com\n", $display);

        $this->assertSame('admin@example.com', $email);
        $this->assertSame('(compte déjà existant)', $password);
        $this->assertStringNotContainsString('Mot de passe', $display);
    }

    // Private and past the scaffold install, so it is driven directly rather than through CommandTester
    private function callCreateAdminUser(SiteCreateCommand $command, string $answers, ?string &$display): array
    {
        $stream = fopen('php://memory', 'r+');
        fwrite($stream, $answers);
        rewind($stream);

        $input = new ArrayInput([]);
        $input->setInteractive(true);
        $input->setStream($stream);
        $output = new BufferedOutput();

        $result = (new \ReflectionMethod($command, 'createAdminUser'))->invoke($command, new SymfonyStyle($input, $output));
        $display = $output->fetch();
        fclose($stream);

        return $result;
    }
}
