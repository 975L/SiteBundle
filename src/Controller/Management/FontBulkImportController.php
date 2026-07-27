<?php
/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\SiteBundle\Controller\Management;

use c975L\ConfigBundle\Service\ConfigServiceInterface;
use c975L\SiteBundle\Entity\Font;
use c975L\SiteBundle\Service\FontFilenameParser;
use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminRoute;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Contracts\Translation\TranslatorInterface;

// Lets an admin upload several font files (ttf/woff/woff2) at once instead of one FontCrudController row at a
// time - name/weight/style are guessed from each file's own filename (see FontFilenameParser), then each file
// goes through the very same Font entity/FontCssListener pipeline as a single upload. A wrong guess is fixed
// afterward on that row's own FontCrudController edit screen, same as manually correcting a typo
class FontBulkImportController extends AbstractController
{
    private const ALLOWED_EXTENSIONS = ['ttf', 'woff', 'woff2'];
    private const MAX_FILE_SIZE = 5 * 1024 * 1024;

    // EasyAdmin prefixes this with the Dashboard's own route name, giving management_site_font_bulk_import
    public const ROUTE = 'management_site_font_bulk_import';

    public function __construct(
        private readonly ConfigServiceInterface $configService,
        private readonly FontFilenameParser $fontFilenameParser,
        private readonly EntityManagerInterface $entityManager,
        private readonly AdminUrlGenerator $adminUrlGenerator,
        private readonly TranslatorInterface $translator,
    ) {
    }

    #[AdminRoute(path: '/site/font-bulk-import', name: 'site_font_bulk_import', options: ['methods' => ['GET', 'POST']])]
    public function index(Request $request): Response
    {
        $this->denyAccessUnlessGranted($this->configService->get('site-role-editor'));

        if ($request->isMethod('POST')) {
            return $this->handleImport($request);
        }

        return $this->render('@c975LSite/management/font_bulk_import.html.twig');
    }

    private function handleImport(Request $request): Response
    {
        if (!$this->isCsrfTokenValid(self::ROUTE, $request->request->get('_token'))) {
            $this->addFlash('danger', $this->translator->trans('flash.font_bulk_import_invalid_token', [], 'site'));

            return $this->redirectToRoute(self::ROUTE);
        }

        $files = $request->files->get('files') ?? [];
        if ([] === $files) {
            $this->addFlash('danger', $this->translator->trans('flash.font_bulk_import_no_files', [], 'site'));

            return $this->redirectToRoute(self::ROUTE);
        }

        $created = 0;
        $skipped = [];
        foreach ($files as $file) {
            $font = $this->buildFont($file);
            if (null === $font) {
                $skipped[] = $file instanceof UploadedFile ? $file->getClientOriginalName() : '?';

                continue;
            }

            $this->entityManager->persist($font);
            ++$created;
        }

        if ([] !== $skipped) {
            $this->addFlash('warning', $this->translator->trans('flash.font_bulk_import_skipped', ['%files%' => implode(', ', $skipped)], 'site'));
        }

        if (0 === $created) {
            return $this->redirectToRoute(self::ROUTE);
        }

        $this->entityManager->flush();
        $this->addFlash('success', $this->translator->trans('flash.font_bulk_import_success', ['%count%' => $created], 'site'));

        return $this->redirect($this->adminUrlGenerator->setController(FontCrudController::class)->setAction(Action::INDEX)->generateUrl());
    }

    // Name/weight/style guessed from the filename (see FontFilenameParser), all three still editable afterwards - null for anything that isn't a usable font upload, which the caller reports back as skipped rather than failing the whole batch
    private function buildFont(mixed $file): ?Font
    {
        if (!$file instanceof UploadedFile || !$file->isValid() || !$this->isValidFontFile($file)) {
            return null;
        }

        $guess = $this->fontFilenameParser->parse($file->getClientOriginalName());

        return (new Font())
            ->setName($guess['name'])
            ->setWeight($guess['weight'])
            ->setStyle($guess['style'])
            ->setFile($file);
    }

    private function isValidFontFile(UploadedFile $file): bool
    {
        $extension = strtolower($file->getClientOriginalExtension());

        return \in_array($extension, self::ALLOWED_EXTENSIONS, true) && $file->getSize() <= self::MAX_FILE_SIZE;
    }
}
