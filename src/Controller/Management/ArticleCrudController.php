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
use c975L\SiteBundle\Entity\Article;
use c975L\SiteBundle\Form\ArticleMediaType;
use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\CollectionField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\FormField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\SlugField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextEditorField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Translation\TranslatableMessage;

#[IsGranted('ROLE_ADMIN')]
class ArticleCrudController extends AbstractCrudController
{
    public function __construct(
        private readonly Security $security,
        private readonly ConfigServiceInterface $configService,
    ) {
    }


    public static function getEntityFqcn(): string
    {
        return Article::class;
    }

    public function configureFields(string $pageName): iterable
    {
        return [
            IdField::new('id')
                ->onlyOnIndex(),
            TextField::new('title')
                ->setLabel(new TranslatableMessage('label.title', [], 'site'))
                ->setRequired(true),
            SlugField::new('slug')
                ->setLabel(new TranslatableMessage('label.slug', [], 'site'))
                ->setTargetFieldName('title')
                ->setRequired(true),

            // Media management
            FormField::addFieldset(new TranslatableMessage('label.media', [], 'site'))
                ->hideOnIndex(),
            CollectionField::new('medias')
                ->hideOnIndex()
                ->setEntryType(ArticleMediaType::class),

            // Content
            TextEditorField::new('description')
                ->setLabel(new TranslatableMessage('label.description', [], 'site'))
                ->hideOnIndex(),
            TextEditorField::new('detail')
                ->setLabel(new TranslatableMessage('label.detail', [], 'site'))
                ->hideOnIndex(),

            // Page association and publication
            AssociationField::new('page')
                ->setLabel(new TranslatableMessage('label.page', [], 'site')),
            IntegerField::new('position')
                ->setLabel(new TranslatableMessage('label.position', [], 'site'))
                ->setHelp(new TranslatableMessage('label.position_help', [], 'site')),
            BooleanField::new('isPublished')
                ->setLabel(new TranslatableMessage('label.is_published', [], 'site')),

            // Dates
            DateTimeField::new('creation')
                ->setLabel(new TranslatableMessage('label.creation', [], 'site'))
                ->setFormTypeOption('disabled', 'disabled')
                ->onlyOnDetail(),
            DateTimeField::new('modification')
                ->setLabel(new TranslatableMessage('label.modification', [], 'site'))
                ->setFormTypeOption('disabled', 'disabled')
                ->onlyOnDetail(),
        ];
    }

    public function configureActions(Actions $actions): Actions
    {
        return $actions
            ->add(Crud::PAGE_INDEX, Action::DETAIL)
            ->setPermission(Action::NEW, $this->configService->get('site-role-needed'))
        ;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->showEntityActionsInlined()
            ->setEntityPermission($this->configService->get('site-role-needed'))
            ->setDefaultSort(['position' => 'ASC'])
        ;
    }

    public function configureFilters(Filters $filters): Filters
    {
        return $filters
            ->add('title')
        ;
    }

    // New article
    public function persistEntity(EntityManagerInterface $entityManager, mixed $article): void
    {
        $article->setCreation(new \DateTime());
        $article->setModification(new \DateTime());
        $this->setUser($article);

        parent::persistEntity($entityManager, $article);
    }

    // Updated article - Invalidate cache
    public function updateEntity(EntityManagerInterface $entityManager, mixed $article): void
    {
        $article->setModification(new \DateTime());
        $this->setUser($article);

        parent::updateEntity($entityManager, $article);
    }

    // Defines the user for the article
    private function setUser(Article $article): void
    {
        $user = $this->security->getUser();
        if (null !== $user) {
            $article->setUser($user);
        }
    }
}
