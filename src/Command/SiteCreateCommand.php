<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\SiteBundle\Command;

use c975L\ConfigBundle\Entity\Config;
use c975L\ConfigBundle\Management\LinkableRouteRegistry;
use c975L\ConfigBundle\Repository\ConfigRepository;
use c975L\ConfigBundle\Service\ConfigServiceInterface;
use c975L\ConfigBundle\Service\VaultEncryptor;
use c975L\SiteBundle\Entity\Menu;
use c975L\SiteBundle\Entity\MenuItem;
use c975L\SiteBundle\Repository\MenuRepository;
use c975L\SiteBundle\Repository\PageRepository;
use c975L\SiteBundle\Service\DefaultPagesImporter;
use c975L\SiteBundle\Service\ScaffoldInstaller;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Interactive wizard that bootstraps a new c975L site: installs every installed bundle's
 * scaffold (backing up files it would overwrite), loads the default config, creates the
 * admin user, asks for the essential config values, and imports the default pages.
 *
 * Replaces the scaffold-copy loop and the manual config:load-all/pages:import-defaults
 * steps previously run by hand from SymfonyNewProject.sh.
 *
 * @author Laurent Marquet <laurent.marquet@laposte.net>
 * @copyright 2026 975L <contact@975l.com>
 */
#[AsCommand(
    name: 'c975l:site:create',
    description: 'Interactive wizard to bootstrap a new c975L site (scaffold, admin user, config, default pages)'
)]
class SiteCreateCommand extends Command
{
    private const QUESTIONS_FILE = __DIR__ . '/../../config/site-create-questions.json';
    // Committed to the site's own repo (not gitignored): the guard must survive git clone/deploy,
    // not just protect the machine it was first run on.
    private const LOCK_FILE = '.c975l-site-created';

    public function __construct(
        private readonly ScaffoldInstaller $scaffoldInstaller,
        private readonly ConfigRepository $configRepository,
        private readonly ConfigServiceInterface $configService,
        private readonly VaultEncryptor $vaultEncryptor,
        private readonly EntityManagerInterface $em,
        private readonly UserPasswordHasherInterface $passwordHasher,
        private readonly DefaultPagesImporter $defaultPagesImporter,
        private readonly PageRepository $pageRepository,
        private readonly MenuRepository $menuRepository,
        private readonly LinkableRouteRegistry $linkableRouteRegistry,
        private readonly TranslatorInterface $translator,
        #[Autowire('%kernel.project_dir%')]
        private readonly string $projectDir,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        if (!class_exists(\App\Entity\User::class)) {
            $io->error('App\\Entity\\User introuvable. Lance d\'abord "php bin/console make:user", puis relance cette commande.');

            return Command::FAILURE;
        }

        if (is_file($this->projectDir . '/' . self::LOCK_FILE)) {
            $io->error('Ce site a déjà été créé (fichier "' . self::LOCK_FILE . '" présent à la racine). Supprime-le volontairement si tu veux vraiment relancer l\'assistant.');

            return Command::FAILURE;
        }

        $this->banner($io);

        $io->section('1/7 — Installation du scaffold');
        $scaffold = $this->scaffoldInstaller->install();
        $io->text(sprintf('  ✓ %d fichier(s) copié(s), %d sauvegardé(s) dans existingFiles/', $scaffold['copied'], $scaffold['backedUp']));
        $this->ensureUserChecker($io);

        $io->section('2/7 — Configuration par défaut');
        $this->getApplication()?->find('c975l:config:load-all')->run(new ArrayInput([]), $output);

        $vaultEncryptor = $this->ensureVaultKey($io);

        $io->section('3/7 — Compte administrateur');
        [$email] = $this->createAdminUser($io);

        $io->section('4/7 — Valeurs de configuration');
        $this->collectConfigValues($io, $vaultEncryptor);
        $this->configService->invalidateCache();
        $io->text('  ✓ Cache des configs invalidé');

        $io->section('5/7 — Pages par défaut');
        $this->importPages($io);

        $io->section('6/7 — Menu du footer');
        $this->buildFooterMenu($io);

        $io->section('7/7 — Terminé');
        $this->lockSite();
        $this->printSummary($io, $email);

        return Command::SUCCESS;
    }

    private function banner(SymfonyStyle $io): void
    {
        $io->newLine();
        $io->block(['', '  c975L — Création interactive du site  ', ''], null, 'fg=white;bg=blue;options=bold', '  ', true);
    }

    // Regenerates C975L_VAULT_KEY into .env.local if missing. The VaultEncryptor injected by
    // the container already froze the (empty) env value at boot, so a freshly-built instance
    // is needed to actually use the new key within this same run.
    private function ensureVaultKey(SymfonyStyle $io): VaultEncryptor
    {
        if ($this->vaultEncryptor->isKeyDefined()) {
            return $this->vaultEncryptor;
        }

        $key = bin2hex(random_bytes(32));
        $envLocal = $this->projectDir . '/.env.local';
        $content = is_file($envLocal) ? file_get_contents($envLocal) : '';
        file_put_contents($envLocal, rtrim($content) . "\nC975L_VAULT_KEY={$key}\n");

        $io->text('  ✓ C975L_VAULT_KEY générée et ajoutée à .env.local');

        return new VaultEncryptor($key);
    }

    // Wires the scaffolded App\Security\UserChecker (refuses login while User::isEnabled is
    // false, see README "Account activation") onto the "main" firewall. Edits the file as plain
    // text rather than through the Yaml component, so existing comments/formatting survive.
    private function ensureUserChecker(SymfonyStyle $io): void
    {
        $path = $this->projectDir . '/config/packages/security.yaml';
        if (!is_file($path)) {
            $io->text('  ⚠ config/packages/security.yaml introuvable, ajoute "user_checker: App\\Security\\UserChecker" au firewall "main" toi-même.');

            return;
        }

        $content = file_get_contents($path);
        if (str_contains($content, 'user_checker:')) {
            return;
        }

        $lines = explode("\n", $content);
        foreach ($lines as $i => $line) {
            if (!preg_match('/^(\s*)main:\s*$/', $line, $m)) {
                continue;
            }

            // Matches the indentation of "main:"'s existing children, rather than assuming 4 spaces
            $childIndent = preg_match('/^(\s*)\S/', $lines[$i + 1] ?? '', $ci) ? $ci[1] : $m[1] . '    ';
            array_splice($lines, $i + 1, 0, [$childIndent . 'user_checker: App\\Security\\UserChecker']);
            file_put_contents($path, implode("\n", $lines));
            $io->text('  ✓ user_checker enregistré sur le firewall "main" dans security.yaml');

            return;
        }

        $io->text('  ⚠ Firewall "main" introuvable dans security.yaml, ajoute "user_checker: App\\Security\\UserChecker" toi-même.');
    }

    // Returns [email, password] (password is intentionally shown in clear text: this account
    // is created outside any email-verification flow, so echoing it avoids losing it)
    private function createAdminUser(SymfonyStyle $io): array
    {
        $email = $io->ask('Email de l\'administrateur', null, function (?string $answer): string {
            if (!$answer || !filter_var($answer, FILTER_VALIDATE_EMAIL)) {
                throw new \RuntimeException('Adresse email invalide.');
            }

            return $answer;
        });

        $userRepository = $this->em->getRepository(\App\Entity\User::class);
        if (null !== $userRepository->findOneBy(['email' => $email])) {
            $io->warning(sprintf('Un utilisateur avec l\'email "%s" existe déjà, création ignorée.', $email));

            return [$email, '(compte déjà existant)'];
        }

        do {
            $password = $io->ask('Mot de passe (8 caractères minimum, affiché en clair)', null, function (?string $answer): string {
                if (!$answer || \strlen($answer) < 8) {
                    throw new \RuntimeException('Le mot de passe doit contenir au moins 8 caractères.');
                }

                return $answer;
            });
            $confirmation = $io->ask('Confirmer le mot de passe');
            if ($confirmation !== $password) {
                $io->error('Les mots de passe ne correspondent pas, recommence.');
            }
        } while ($confirmation !== $password);

        $now = new \DateTime();
        $user = new \App\Entity\User();
        $user->setEmail($email);
        $user->setPassword($this->passwordHasher->hashPassword($user, $password));
        // Bootstrap user is the site's owner (producer or self-hoster), so it also gets ROLE_SUPER_ADMIN,
        // the only role allowed to see/edit the "backup" config group (DB credentials, see ConfigCrudController)
        $user->setRoles(['ROLE_ADMIN', 'ROLE_SUPER_ADMIN']);
        $user->setIsVerified(true);
        $user->setIsEnabled(true);
        $user->setCreation($now);
        $user->setModification($now);
        $this->em->persist($user);
        $this->em->flush();

        $io->text('  ✓ Utilisateur admin créé');

        return [$email, $password];
    }

    private function collectConfigValues(SymfonyStyle $io, VaultEncryptor $vaultEncryptor): void
    {
        $slugs = json_decode(file_get_contents(self::QUESTIONS_FILE), true) ?? [];

        foreach ($slugs as $slug) {
            $config = $this->configRepository->findOneBySlug($slug);
            if (null === $config) {
                $io->text(sprintf('  ⚠ %s introuvable (bundle non installé ?), ignoré', $slug));

                continue;
            }

            $value = $this->askConfigValue($io, $config);

            if ($config->getIsSensitive() && '' !== $value && $vaultEncryptor->isKeyDefined()) {
                $value = $vaultEncryptor->encrypt($value);
            }

            $config->setValue($value);
            $config->setModification(new \DateTime());
            $this->em->persist($config);
            $this->em->flush();
        }
    }

    private function askConfigValue(SymfonyStyle $io, Config $config): string
    {
        $kind = $config->getKind();
        $currentValue = $this->configService->get($config->getSlug());
        // Safe even if the description isn't a translation key yet (bundle not migrated):
        // Symfony returns the id unchanged when no translation is found for it
        $question = sprintf(
            '%s <fg=gray>(%s) [%s]</>',
            $this->translator->trans($config->getDescription() ?? $config->getLabel(), [], 'site_config'),
            $config->getSlug(),
            $kind
        );

        if (Config::TYPE_BOOL === $kind) {
            return $io->confirm($question, (bool) $currentValue) ? 'true' : 'false';
        }

        $default = match (true) {
            is_array($currentValue) => json_encode($currentValue),
            null === $currentValue => null,
            default => (string) $currentValue,
        };

        return (string) $io->ask($question, $default, fn (?string $answer) => $this->validateKind($kind, $answer));
    }

    private function validateKind(string $kind, ?string $answer): ?string
    {
        if (null === $answer || '' === $answer) {
            return $answer;
        }

        return match ($kind) {
            Config::TYPE_INT  => is_numeric($answer) ? $answer : throw new \RuntimeException('Cette valeur doit être un nombre.'),
            Config::TYPE_DATE => false !== \DateTime::createFromFormat('Y-m-d', $answer) ? $answer : throw new \RuntimeException('Cette valeur doit être une date au format AAAA-MM-JJ.'),
            Config::TYPE_JSON => null !== json_decode($answer) ? $answer : throw new \RuntimeException('Cette valeur doit être un JSON valide.'),
            default => $answer,
        };
    }

    private function importPages(SymfonyStyle $io): void
    {
        $result = $this->defaultPagesImporter->import(function (array $def) use ($io): array {
            $import = $io->confirm(sprintf('Importer la page "%s" (%s) ?', $def['title'], $def['slug']), true);
            if (!$import) {
                return ['import' => false, 'isPublished' => false];
            }

            return ['import' => true, 'isPublished' => $io->confirm('Publier immédiatement ?', $def['isPublished'])];
        });

        $io->text(sprintf('  ✓ %d page(s) créée(s), %d déjà existante(s) ignorée(s)', $result['created'], $result['skipped']));
    }

    // Offers every bundle-contributed route (e.g. ContactFormBundle's "contact" page, only present if
    // that bundle is installed - see LinkableRouteRegistry) plus the legal pages just imported, in the
    // fixed order expected in a footer (mentions légales, confidentialité, CGU, CGV, cookies, copyright).
    // Re-running the command never creates duplicate items, since existing page/route items are skipped.
    private function buildFooterMenu(SymfonyStyle $io): void
    {
        $menu = $this->menuRepository->findOneByLocation(Menu::LOCATION_FOOTER) ?? (new Menu())->setLocation(Menu::LOCATION_FOOTER);
        $existingPageIds = array_map(static fn (MenuItem $item) => $item->getPage()?->getId(), $menu->getItems()->toArray());
        $existingRoutes = array_map(static fn (MenuItem $item) => $item->getRoute(), $menu->getItems()->toArray());
        $position = $menu->getItems()->count();

        foreach ($this->linkableRouteRegistry->all() as $routeName => $meta) {
            if (\in_array($routeName, $existingRoutes, true)) {
                continue;
            }

            $label = $this->translator->trans($meta['label'], [], $meta['translation_domain']);
            if ($io->confirm(sprintf('Ajouter "%s" au menu du footer ?', $label), true)) {
                $menu->addItem((new MenuItem())->setRoute($routeName)->setPosition($position++));
            }
        }

        foreach ($this->defaultPagesImporter->getLegalPageSlugsByModel() as $slug) {
            $page = $this->pageRepository->findOneBy(['slug' => $slug]);
            if (null === $page || \in_array($page->getId(), $existingPageIds, true)) {
                continue;
            }

            if ($io->confirm(sprintf('Ajouter la page "%s" au menu du footer ?', $page->getTitle()), true)) {
                $menu->addItem((new MenuItem())->setPage($page)->setPosition($position++));
            }
        }

        $this->em->persist($menu);
        $this->em->flush();

        $io->text(sprintf('  ✓ %d élément(s) dans le menu du footer', $menu->getItems()->count()));
    }

    // Writes the marker checked at the top of execute(): committed to the repo so the guard
    // survives git clone/deploy, not just a re-run on the same machine.
    private function lockSite(): void
    {
        file_put_contents(
            $this->projectDir . '/' . self::LOCK_FILE,
            sprintf("Site créé le %s via c975l:site:create.\nSupprime ce fichier pour autoriser une relance volontaire de la commande.\n", (new \DateTime())->format('Y-m-d H:i:s'))
        );
    }

    private function printSummary(SymfonyStyle $io, string $email): void
    {
        $managementUrl = 'http://127.0.0.1:8000/management';

        $io->newLine();
        $io->block([
            'Site créé avec succès !',
            '',
            'Lancer le site      : symfony serve',
            'Continuer la config : ' . $managementUrl,
            'Identifiants admin  : ' . $email . ' (mot de passe défini lors de la saisie)',
        ], null, 'fg=black;bg=green;options=bold', '  ', true);
    }
}
