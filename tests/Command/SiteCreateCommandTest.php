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
use c975L\ConfigBundle\Service\AdminUserCreator;
use c975L\ConfigBundle\Service\ConfigServiceInterface;
use c975L\ConfigBundle\Service\ScaffoldInstaller;
use c975L\ConfigBundle\Service\VaultEncryptor;
use c975L\SiteBundle\Command\SiteCreateCommand;
use c975L\SiteBundle\Repository\MenuRepository;
use c975L\SiteBundle\Repository\PageRepository;
use c975L\SiteBundle\Service\DefaultPagesImporter;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Console\Tester\CommandTester;
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
        foreach (['/src/Entity/User.php', '/.c975l-site-created', '/config/packages/security.yaml', '/.env.local'] as $file) {
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

    private function createCommand(?EntityManagerInterface $em = null, ?AdminUserCreator $adminUserCreator = null, ?ScaffoldInstaller $scaffoldInstaller = null): SiteCreateCommand
    {
        return new SiteCreateCommand(
            $scaffoldInstaller ?? $this->createStub(ScaffoldInstaller::class),
            $this->createStub(ConfigRepository::class),
            $this->createStub(ConfigServiceInterface::class),
            $this->createStub(VaultEncryptor::class),
            $em ?? $this->createStub(EntityManagerInterface::class),
            $adminUserCreator ?? $this->createStub(AdminUserCreator::class),
            $this->createStub(DefaultPagesImporter::class),
            $this->createStub(PageRepository::class),
            $this->createStub(MenuRepository::class),
            $this->createStub(LinkableRouteRegistry::class),
            $this->createStub(TranslatorInterface::class),
            $this->projectDir,
        );
    }

    // The guard must read the file on disk rather than call class_exists(): in a real app the class is autoloadable from the moment ConfigBundle's scaffold has been installed, and autoloading it here would freeze that pre-scaffold version in memory for the whole process
    public function testExecuteFailsWhenUserEntityFileIsMissing(): void
    {
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

    /*
     * The wizard requires App\Entity\User to exist, so "make:user" has always just written a src/Entity/User.php
     * and a src/Repository/UserRepository.php the scaffold ships its own version of. Left to its no-force default,
     * "c975l:scaffold:install" keeps any file its manifest does not vouch for - both of those - and the wizard
     * would go on to create the admin account against the bare skeleton User. A first install has nothing to
     * preserve: everything is delivered, and what was there goes to existingFiles/.
     */
    public function testExecuteInstallsTheScaffoldForcefully(): void
    {
        file_put_contents($this->projectDir . '/src/Entity/User.php', '<?php');

        $forced = null;
        $installer = $this->createStub(ScaffoldInstaller::class);
        $installer->method('install')->willReturnCallback(function (array $paths = [], bool $dryRun = false, bool $force = false) use (&$forced): array {
            $forced = $force;

            return ['copied' => 0, 'backedUp' => 0, 'skipped' => 0, 'files' => [], 'diverged' => [], 'unmatched' => []];
        });

        // The wizard goes on to prompt for the admin account, which is not what this locks - it stops on the unanswered prompt
        try {
            (new CommandTester($this->createCommand(scaffoldInstaller: $installer)))->execute([]);
        } catch (\Throwable) {
        }

        $this->assertTrue($forced, 'The site-creation wizard installed the scaffold without --force, leaving make:user\'s own User entity in place.');
    }

    // No confirmation prompt, the password being echoed; the stream's third answer must stay unread. What the account itself ends up holding (hashing, roles, verified/enabled) is ConfigBundle's AdminUserCreator's own business, covered by its own test
    public function testCreateAdminUserDoesNotAskForPasswordConfirmation(): void
    {
        $adminUserCreator = $this->createMock(AdminUserCreator::class);
        $adminUserCreator->method('exists')->willReturn(false);
        $adminUserCreator->expects($this->once())->method('create')->with('admin@example.com', 'secret1234');

        [$email, $password] = $this->callCreateAdminUser(
            $this->createCommand(null, $adminUserCreator),
            "admin@example.com\nsecret1234\nsecret1234\n",
            $display
        );

        $this->assertSame('admin@example.com', $email);
        $this->assertSame('secret1234', $password);
        $this->assertStringNotContainsString('Confirmer', $display);
    }

    // Existing email: no password is ever asked, the creation is skipped
    public function testCreateAdminUserSkipsCreationWhenTheEmailAlreadyExists(): void
    {
        $adminUserCreator = $this->createMock(AdminUserCreator::class);
        $adminUserCreator->method('exists')->willReturn(true);
        $adminUserCreator->expects($this->never())->method('create');

        [$email, $password] = $this->callCreateAdminUser($this->createCommand(null, $adminUserCreator), "admin@example.com\n", $display);

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
