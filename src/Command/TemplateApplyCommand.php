<?php
/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\SiteBundle\Command;

use c975L\SiteBundle\Entity\Page;
use c975L\SiteBundle\Management\TemplateApplier;
use c975L\SiteBundle\Management\TemplateRegistry;
use c975L\SiteBundle\Repository\PageRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

// Creates or updates a page from a template (config/templates/*.json, or an arbitrary JSON file with
// the same {"label", "blocks":[{"kind","data"}]} shape) - the CLI counterpart of
// PageCrudController::applyTemplate(), for scripted use when redesigning several pages/sites at once
// (see TemplateApplier, shared by both). The page is left unpublished unless --publish is passed, so
// it can be previewed first (see PageController::preview()).
#[AsCommand(
    name: 'c975l:site:templates:apply',
    description: 'Creates or updates a page from a template (config/templates/*.json or a JSON file path)'
)]
class TemplateApplyCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly PageRepository $pageRepository,
        private readonly TemplateRegistry $templateRegistry,
        private readonly TemplateApplier $templateApplier,
        private readonly Security $security,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument(
                'template',
                InputArgument::REQUIRED,
                'Template slug (config/templates/<slug>.json) or a path to a JSON file with the same shape'
            )
            ->addArgument('page', InputArgument::REQUIRED, 'Slug of the page to create or update')
            ->addOption('title', null, InputOption::VALUE_REQUIRED, "Title for the page, required only if it doesn't exist yet")
            ->addOption('replace', null, InputOption::VALUE_NONE, "Remove the page's existing blocks before adding the template's")
            ->addOption('publish', null, InputOption::VALUE_NONE, 'Publish the page (left unpublished by default, so it can be previewed first)')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $templateArg = (string) $input->getArgument('template');
        $template = $this->templateRegistry->get($templateArg) ?? $this->loadTemplateFromFile($templateArg);
        if (null === $template) {
            $io->error(sprintf(
                'No template found for "%s" (not a known slug in config/templates/, nor a valid JSON file).',
                $templateArg
            ));

            return Command::FAILURE;
        }

        // Regardless of publish status: this command routinely targets unpublished preview pages
        $slug = (string) $input->getArgument('page');
        $page = $this->pageRepository->findOneBySlugForDisplay($slug);
        $isNew = null === $page;

        if ($isNew) {
            $title = $input->getOption('title');
            if (null === $title) {
                $io->error(sprintf('Page "%s" does not exist yet: pass --title to create it.', $slug));

                return Command::FAILURE;
            }

            $page = (new Page())
                ->setTitle($title)
                ->setSlug($slug)
                ->setIsPublished((bool) $input->getOption('publish'))
                ->setPriority(5)
                ->setChangeFrequency('monthly')
                ->setCreation(new \DateTime())
                ->setModification(new \DateTime());
            $this->entityManager->persist($page);
        } elseif ($input->getOption('replace')) {
            foreach ($page->getBlocks()->toArray() as $block) {
                $page->removeBlock($block);
                $this->entityManager->remove($block);
            }
        }

        $user = $this->security->getUser();
        if ($isNew && null !== $user) {
            $page->setUser($user);
        }

        $count = $this->templateApplier->apply($page, $template, $user);
        $this->entityManager->flush();

        $io->success(sprintf(
            '%s page "%s": %d block(s) added from "%s".',
            $isNew ? 'Created' : 'Updated',
            $slug,
            $count,
            $templateArg
        ));

        return Command::SUCCESS;
    }

    // Falls back to an arbitrary JSON file when $templateArg isn't a known config/templates/ slug -
    // lets a one-off Claude design be applied without shipping it as a permanent bundle asset
    private function loadTemplateFromFile(string $path): ?array
    {
        if (!is_file($path)) {
            return null;
        }

        $data = json_decode((string) file_get_contents($path), true);

        return is_array($data) && isset($data['blocks']) ? $data : null;
    }
}
