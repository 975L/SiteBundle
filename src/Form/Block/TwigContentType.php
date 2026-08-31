<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\SiteBundle\Form\Block;

use c975L\SiteBundle\Service\TwigContentTemplateChecker;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Callback;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

class TwigContentType extends AbstractType
{
    public function __construct(private readonly TwigContentTemplateChecker $checker)
    {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            // A Twig template path/name (e.g. "block_showcase/bundle.html.twig"), one this site wrote for itself - the installed bundles' own templates are refused (see TwigContentTemplateChecker). Not limited to a collection's detail Page: any Page can use this block. When it does sit on a detail Page, "collectionItem" (see TwigContent.html.twig, PageController::resolveCollectionDetail()) is passed to it - see SiteBundle's README ("Item detail pages", under "Collection entries") for that recipe.
            ->add('templatePath', TextType::class, [
                'label' => 'label.template_path',
                'help' => 'label.template_path_help',
                // Said here as well as guarded at render time: refused on the screen that writes it, the mistake is seen and fixed, where a block rendering nothing leaves the editor looking for what it broke
                'constraints' => [
                    new Callback($this->validateTemplatePath(...)),
                ],
            ])
        ;
    }

    // An empty path is what a block not yet pointed anywhere carries, and the template already skips it
    public function validateTemplatePath(?string $templatePath, ExecutionContextInterface $context): void
    {
        if (null === $templatePath || '' === trim($templatePath) || $this->checker->isAllowed($templatePath)) {
            return;
        }

        $context->buildViolation('label.template_path_refused')
            ->setTranslationDomain('site')
            ->addViolation()
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => null,
            'translation_domain' => 'site',
        ]);
    }
}
