<?php

namespace App\Form;

use c975L\ConfigBundle\Service\ConfigServiceInterface;
use c975L\SiteBundle\Service\FormBotProtection;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\IsTrue;
use Symfony\Component\Validator\Constraints\NotBlank;

class ResetPasswordRequestFormType extends AbstractType
{
    public function __construct(
        private readonly ConfigServiceInterface $configService,
        private readonly FormBotProtection $botProtection,
    ) {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $this->botProtection->addHoneypotField($builder);

        $builder
            ->add('email', EmailType::class, [
                'label' => 'label.email',
                'translation_domain' => 'site',
                'attr' => ['autocomplete' => 'email'],
                'constraints' => [
                    new NotBlank(
                        message: 'text.email_required',
                    ),
                ],
            ])
        ;

        if ($this->configService->get('site-form-gdpr')) {
            $builder->add('gdpr', CheckboxType::class, [
                'label' => 'text.gdpr',
                'translation_domain' => 'site',
                'required' => true,
                'mapped' => false,
                // 'required' alone is HTML5-only - this is what actually rejects an unchecked box server-side
                'constraints' => [
                    new IsTrue(message: 'text.gdpr_required'),
                ],
            ]);
        }
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([]);
    }
}
