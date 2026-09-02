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
use c975L\ConfigBundle\Repository\ConfigRepository;
use c975L\SiteBundle\Entity\Menu;
use c975L\SiteBundle\Repository\MenuRepository;
use c975L\UiBundle\Entity\Block;
use c975L\UiBundle\Entity\EmailBlock;
use c975L\UiBundle\Entity\EmailTemplate;
use c975L\UiBundle\Repository\EmailTemplateRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Moves the prose this bundle used to keep in configuration entries into the things that now hold it.
 *
 * A config entry carries no language, so a bilingual site said all of this in one language to everyone. Two moves,
 * one command - they are the same job, they run at the same moment of a deployment, and they are taken out together:
 *
 *   - the four sentences wrapping every e-mail become one EmailTemplate apiece, one row per language
 *     (see EmailLayoutTemplateProvider), filled here with what the site had written;
 *   - the tagline under the site's name becomes a block of the "navbar-brand" menu, translated like every block.
 *
 * The e-mail half runs after c975l:ui:email-templates:ensure has created those rows and overwrites the wording it
 * seeded: the declaration is what a site starts from, what an admin already wrote is what it keeps.
 *
 * A one-off, meant to be taken out with its line in the deployment once the whole fleet has run it.
 */
#[AsCommand(
    name: 'c975l:site:content:adopt-config-texts',
    description: 'Moves the "email-text-*" config entries into the e-mail layout templates'
)]
class SiteContentAdoptConfigTextsCommand extends Command
{
    // The config entry each template took its wording from
    private const array MOVES = [
        'layout_no_spam' => 'email-text-no-spam',
        'layout_hello' => 'email-text-hello',
        'layout_closing' => 'email-text-closing',
        'layout_sent_by' => 'email-text-sent-by',
    ];

    // The block kind a tagline is written as: one line of rich text, translated like every block (see BlockRegistry)
    private const string TAGLINE_KIND = 'text_hook';

    public function __construct(
        private readonly ConfigRepository $configRepository,
        private readonly EmailTemplateRepository $emailTemplateRepository,
        private readonly MenuRepository $menuRepository,
        private readonly EntityManagerInterface $entityManager,
        #[Autowire(param: 'kernel.default_locale')]
        private readonly string $defaultLocale,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $moved = [];
        $stranded = [];

        foreach (self::MOVES as $name => $slug) {
            // Read from the repository and not through ConfigService: the entry is no longer declared by any
            // configs.json, so the service does not know it - the row itself is still there, c975l:config:load-all
            // taking nothing away and c975l:config:prune not being part of a deployment
            $config = $this->configRepository->findOneBySlug($slug);
            $content = trim((string) $config?->getValue());

            // An entry nobody ever filled has no wording to save, and the template keeps the one it was seeded with
            if (null === $config || '' === $content) {
                if ($config instanceof Config) {
                    $this->entityManager->remove($config);
                }

                continue;
            }

            $emailTemplate = $this->emailTemplateRepository->findOneBy(['name' => $name, 'locale' => $this->defaultLocale]);

            // Nothing to write it into: c975l:ui:email-templates:ensure has not run, or ran on a core-bundle that
            // did not declare these yet. The row is left where it is rather than deleted, this being the only copy
            if (!$emailTemplate instanceof EmailTemplate) {
                $stranded[] = sprintf('%s (no "%s" template in "%s")', $slug, $name, $this->defaultLocale);

                continue;
            }

            if ($this->write($emailTemplate, $content)) {
                $moved[] = sprintf('%s -> %s (%s)', $slug, $name, $this->defaultLocale);
            }

            $this->entityManager->remove($config);
        }

        $this->moveTagline($moved, $stranded);

        $this->entityManager->flush();

        if ([] !== $stranded) {
            $io->warning('Left in place, having nowhere to go - run c975l:ui:email-templates:ensure first, then this again:');
            $io->listing($stranded);
        }

        if ([] === $moved) {
            $io->success('Nothing to move.');

            return Command::SUCCESS;
        }

        $io->listing($moved);
        $io->success(sprintf('%d text(s) moved out of the configuration.', \count($moved)));

        return Command::SUCCESS;
    }

    /**
     * The tagline becomes the first block of the "navbar-brand" menu, the menu being created when the site has none.
     *
     * Stranded like the e-mail half when that menu already carries blocks: the configuration row is the only copy of
     * the wording, so it stays where it is rather than being deleted with nowhere to have gone.
     *
     * @param list<string> $moved
     * @param list<string> $stranded
     */
    private function moveTagline(array &$moved, array &$stranded): void
    {
        $config = $this->configRepository->findOneBySlug('site-tagline');
        $content = trim((string) $config?->getValue());

        if (null === $config) {
            return;
        }

        if ('' !== $content) {
            $menu = $this->menuRepository->findOneByLocation(Menu::LOCATION_NAVBAR_BRAND);

            if (!$menu instanceof Menu) {
                $menu = new Menu()->setLocation(Menu::LOCATION_NAVBAR_BRAND);
                $this->entityManager->persist($menu);
            }

            // Only ever into an empty menu: one already carrying blocks has been written in the back-office since,
            // and what an admin put under the site's name is not this command's to add to
            if (!$menu->getBlocks()->isEmpty()) {
                $stranded[] = sprintf('site-tagline (the "%s" menu already carries blocks)', Menu::LOCATION_NAVBAR_BRAND);

                return;
            }

            $block = new Block()
                ->setKind(self::TAGLINE_KIND)
                ->setPosition(1)
                ->setData(['text' => $content]);
            $this->entityManager->persist($block);
            $menu->addBlock($block);

            $moved[] = sprintf('site-tagline -> "%s" menu', Menu::LOCATION_NAVBAR_BRAND);
        }

        $this->entityManager->remove($config);
    }

    /**
     * Puts the site's own wording in the template, in place of the one the declaration seeded.
     *
     * The placeholder changes syntax on the way: the layout used to substitute "%site%" itself, where a template
     * resolves "{{ site }}" at render time (see EmailTemplateRenderer::renderBody).
     *
     * Into an html block, the entries being rich text written in Trix: a text block escapes what it is given, which
     * would have shown "<p>" to every recipient of a site that had filled them in.
     */
    private function write(EmailTemplate $emailTemplate, string $content): bool
    {
        $content = str_replace('%site%', '{{ site }}', $content);
        $block = $emailTemplate->getBlocks()->first();

        if (!$block instanceof EmailBlock) {
            $block = new EmailBlock()->setType(EmailBlock::TYPE_HTML)->setPosition(0);
            $emailTemplate->addBlock($block);
        }

        // A block seeded as text carries wording of ours, and is now taking the site's own markup
        $block->setType(EmailBlock::TYPE_HTML);

        // Already carrying it: a second run finds the same text and writes nothing
        if ($content === $block->getContent()) {
            return false;
        }

        $block->setContent($content);
        $this->entityManager->persist($emailTemplate);

        return true;
    }
}
