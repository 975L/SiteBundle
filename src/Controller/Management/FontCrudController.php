<?php
/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\SiteBundle\Controller\Management;

use c975L\ConfigBundle\Management\EasyAdminActionHelper;
use c975L\ConfigBundle\Service\ConfigServiceInterface;
use c975L\ConfigBundle\Service\Export\ContentExporter;
use c975L\SiteBundle\Entity\Font;
use c975L\SiteBundle\Management\FontImportProvider;
use c975L\SiteBundle\Repository\FontRepository;
use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminRoute;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Dto\BatchActionDto;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\Field;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Validator\Constraints\File;
use Symfony\Contracts\Translation\TranslatorInterface;
use Vich\UploaderBundle\Form\Type\VichFileType;

use function Symfony\Component\Translation\t;

// Lets an admin upload their own font files (ttf/woff/woff2) rather than relying on a dev-declared @font-face in
// _fonts.css (see FontService) - FontCssListener compiles every row here into public/bundles/build/site-fonts-uploaded.css
class FontCrudController extends AbstractCrudController
{
    private const ALLOWED_EXTENSIONS = ['ttf', 'woff', 'woff2'];

    private const STYLE_CHOICES = [
        'label.font_style_normal' => 'normal',
        'label.font_style_italic' => 'italic',
    ];

    // Value => translation key, from the standard OpenType/CSS weight scale - displayed as "100 - Thin" so an admin
    // naming a file by its named weight (Medium, SemiBold...) doesn't have to know its numeric equivalent. The
    // Font::WEIGHT_VARIABLE entry covers variable font uploads (see Font::isVariable()) instead of a checkbox
    private const WEIGHT_NAMES = [
        Font::WEIGHT_VARIABLE => 'label.font_weight_variable',
        100 => 'label.font_weight_thin',
        200 => 'label.font_weight_extra_light',
        300 => 'label.font_weight_light',
        400 => 'label.font_weight_regular',
        500 => 'label.font_weight_medium',
        600 => 'label.font_weight_semibold',
        700 => 'label.font_weight_bold',
        800 => 'label.font_weight_extra_bold',
        900 => 'label.font_weight_black',
    ];

    public function __construct(
        private readonly ConfigServiceInterface $configService,
        private readonly TranslatorInterface $translator,
        private readonly FontRepository $fontRepository,
        private readonly AdminUrlGenerator $adminUrlGenerator,
        private readonly ContentExporter $contentExporter,
    ) {
    }

    public static function getEntityFqcn(): string
    {
        return Font::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular(t('label.font', [], 'site'))
            ->setEntityLabelInPlural(t('label.fonts', [], 'site'))
            ->setEntityPermission($this->configService->get('site-role-editor'))
            ->setDefaultSort(['name' => 'ASC', 'weight' => 'ASC'])
            ->showEntityActionsInlined()
            ->overrideTemplate('crud/index', '@c975LSite/management/font_crud_index.html.twig')
            ->overrideTemplate('crud/edit', '@c975LSite/management/font_crud_edit.html.twig')
            ->overrideTemplate('crud/new', '@c975LSite/management/font_crud_new.html.twig')
        ;
    }

    public function configureActions(Actions $actions): Actions
    {
        $role = $this->configService->get('site-role-editor');

        // Same "export selection" as PageCrudController::exportSelection() - see that method's own comment for why
        $actions->add(Crud::PAGE_INDEX, Action::new('exportSelection', t('action.export_selection', [], 'site'), 'fa fa-file-export')
            ->createAsBatchAction()
            ->linkToCrudAction('exportSelection'));
        $actions->setPermission('exportSelection', $this->configService->get('site-role-admin'));

        // Lets the admin back out of a create/edit without saving - mirrors EasyAdmin's own built-in actions (linkToCrudAction targeting INDEX, same as Action::INDEX itself)
        $cancelAction = Action::new('cancel', $this->translator->trans('action.cancel', [], 'EasyAdminBundle'), 'fa fa-times')
            ->linkToCrudAction(Action::INDEX)
            ->addCssClass('btn btn-secondary');

        return $actions
            ->add(Crud::PAGE_NEW, $cancelAction)
            ->add(Crud::PAGE_EDIT, $cancelAction)
            ->update(Crud::PAGE_INDEX, Action::EDIT, fn (Action $action) => EasyAdminActionHelper::toIconOnly(
                $action,
                $this->translator->trans('action.edit', [], 'EasyAdminBundle'),
            ))
            ->update(Crud::PAGE_INDEX, Action::DELETE, fn (Action $action) => EasyAdminActionHelper::toIconOnly(
                $action,
                $this->translator->trans('action.delete', [], 'EasyAdminBundle'),
            ))
            ->setPermission(Action::INDEX, $role)
            ->setPermission(Action::NEW, $role)
            ->setPermission(Action::EDIT, $role)
            ->setPermission(Action::DELETE, $role)
            // Detail adds no information beyond what edit already shows
            ->disable(Action::DETAIL)
        ;
    }

    // Exports the checked fonts (name/weight/style + the real file bundled in the archive) as a downloadable zip, meant to be re-uploaded elsewhere via ConfigBundle's ContentImportController (see FontImportProvider) - restricted to site-role-admin, see configureActions()
    #[AdminRoute]
    public function exportSelection(AdminContext $context, BatchActionDto $batchActionDto): Response
    {
        $this->denyAccessUnlessGranted($this->configService->get('site-role-admin'));

        if (Font::class !== $batchActionDto->getEntityFqcn()) {
            throw new BadRequestHttpException();
        }

        if (!$this->isCsrfTokenValid('ea-batch-action-exportSelection-' . $batchActionDto->getEntityFqcn(), $batchActionDto->getCsrfToken())) {
            return $this->redirect($this->adminUrlGenerator->setController(self::class)->setAction(Action::INDEX)->generateUrl());
        }

        $fonts = $this->fontRepository->findBy(['id' => $batchActionDto->getEntityIds()]);

        $files = [];
        $items = [];
        foreach ($fonts as $font) {
            $fontData = $this->exportFontData($font, $files);
            if (null !== $fontData) {
                $items[] = $fontData;
            }
        }

        return $this->contentExporter->export(FontImportProvider::KIND, $items, $files);
    }

    // Registers the font's physical file for the zip archive (&$files: archive-relative path => disk path), returning the metadata entry with a 'file' reference instead of embedding its bytes - same convention as PageCrudController::exportMediaData(). Returns null (skipped by the caller) when the file can't be read, rather than exporting a broken reference
    private function exportFontData(Font $font, array &$files): ?array
    {
        $filename = $font->getFilename();
        if (null === $filename) {
            return null;
        }

        $path = $this->getParameter('kernel.project_dir') . '/public/' . $filename;
        if (!is_file($path)) {
            return null;
        }

        $archivePath = 'files/' . bin2hex(random_bytes(8)) . '_' . basename($filename);
        $files[$archivePath] = $path;

        return [
            'name' => $font->getName(),
            'weight' => $font->getWeight(),
            'style' => $font->getStyle(),
            'originalFilename' => basename($filename),
            'file' => $archivePath,
        ];
    }

    public function configureFields(string $pageName): iterable
    {
        $isNew = Crud::PAGE_NEW === $pageName;
        $weightChoices = [];
        foreach (self::WEIGHT_NAMES as $value => $labelKey) {
            $label = Font::WEIGHT_VARIABLE === $value
                ? $this->translator->trans($labelKey, [], 'site')
                : sprintf('%d - %s', $value, $this->translator->trans($labelKey, [], 'site'));
            $weightChoices[$label] = $value;
        }

        return [
            IdField::new('id')->onlyOnIndex(),

            TextField::new('name')
                ->setLabel(t('label.font_name', [], 'site'))
                ->setRequired(true),

            // Entity defaults (weight=400, style='normal') already prefill a new row - no need for a form-level 'data' override, which would otherwise also force these values back on every edit
            ChoiceField::new('weight')
                ->setLabel(t('label.font_weight', [], 'site'))
                ->setChoices($weightChoices)
                ->setRequired(true),

            ChoiceField::new('style')
                ->setLabel(t('label.font_style', [], 'site'))
                ->setTranslatableChoices(array_combine(
                    self::STYLE_CHOICES,
                    array_map(fn (string $labelKey) => t($labelKey, [], 'site'), array_keys(self::STYLE_CHOICES)),
                ))
                ->setRequired(true),

            Field::new('file')
                ->setLabel(t('label.file', [], 'site'))
                ->setHelp(t('label.font_file_help', [], 'site'))
                ->setFormType(VichFileType::class)
                ->setFormTypeOptions([
                    'required' => $isNew,
                    'allow_delete' => true,
                    'download_uri' => true,
                    'asset_helper' => true,
                    'constraints' => [
                        new File(
                            maxSize: '5M',
                            extensions: self::ALLOWED_EXTENSIONS,
                            extensionsMessage: $this->translator->trans('label.font_file_invalid_extension', [], 'site'),
                        ),
                    ],
                ])
                ->onlyOnForms(),

            TextField::new('filename')
                ->setLabel(t('label.file', [], 'site'))
                ->onlyOnIndex(),
        ];
    }
}
