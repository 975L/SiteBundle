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
        foreach (['/src/Entity/User.php', '/.c975l-site-created', '/config/packages/security.yaml'] as $file) {
            if (is_file($this->projectDir . $file)) {
                unlink($this->projectDir . $file);
            }
        }

        // Deepest first, and only the ones a test actually created
        foreach (['/config/packages', '/config', '/src/Entity', '/src', ''] as $dir) {
            if (is_dir($this->projectDir . $dir)) {
                rmdir($this->projectDir . $dir);
            }
        }
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

    // The skeleton ships "access_control:" with every rule commented out, so the rule has to land on a key with no child to copy the indentation from
    public function testEnsureManagementAccessControlAddsTheRuleUnderAnEmptyAccessControl(): void
    {
        $path = $this->writeSecurityYaml("security:\n    access_control:\n");

        $display = $this->callEnsureManagementAccessControl();

        $this->assertStringContainsString('        - { path: ^/management, roles: IS_AUTHENTICATED_FULLY }', file_get_contents($path));
        $this->assertStringContainsString('access_control', $display);
    }

    // Existing rules give the indentation to match, rather than assuming one level below "access_control:"
    public function testEnsureManagementAccessControlMatchesTheExistingRulesIndentation(): void
    {
        $path = $this->writeSecurityYaml("security:\n    access_control:\n      - { path: ^/gestion, roles: ROLE_ADMIN }\n");

        $this->callEnsureManagementAccessControl();

        $this->assertStringContainsString("\n      - { path: ^/management, roles: IS_AUTHENTICATED_FULLY }", file_get_contents($path));
    }

    // Re-running the command on a site already created must not stack a second rule
    public function testEnsureManagementAccessControlLeavesAnExistingRuleAlone(): void
    {
        $yaml = "security:\n    access_control:\n        - { path: ^/management, roles: ROLE_ADMIN }\n";
        $path = $this->writeSecurityYaml($yaml);

        $this->callEnsureManagementAccessControl();

        $this->assertSame($yaml, file_get_contents($path));
    }

    // No firewall to edit: the command reports it instead of writing a broken file
    public function testEnsureManagementAccessControlReportsAMissingAccessControl(): void
    {
        $path = $this->writeSecurityYaml("security:\n    firewalls:\n        main:\n            lazy: true\n");

        $display = $this->callEnsureManagementAccessControl();

        $this->assertStringNotContainsString('^/management', file_get_contents($path));
        $this->assertStringContainsString('⚠', $display);
    }

    private function writeSecurityYaml(string $content): string
    {
        $path = $this->projectDir . '/config/packages/security.yaml';
        if (!is_dir(dirname($path))) {
            mkdir(dirname($path), 0775, true);
        }
        file_put_contents($path, $content);

        return $path;
    }

    // Private and only reached mid-command, so it is driven directly rather than through CommandTester
    private function callEnsureManagementAccessControl(): string
    {
        $command = $this->createCommand();
        $output = new BufferedOutput();
        (new \ReflectionMethod($command, 'ensureManagementAccessControl'))
            ->invoke($command, new SymfonyStyle(new ArrayInput([]), $output));

        return $output->fetch();
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
