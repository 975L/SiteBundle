<?php

namespace App\Form;

use App\Entity\User;
use c975L\ConfigBundle\Service\ConfigServiceInterface;
use c975L\SiteBundle\Service\FormBotProtection;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\RepeatedType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\IsTrue;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Regex;

use function Symfony\Component\Translation\t;

class RegistrationFormType extends AbstractType
{
    public function __construct(
        private readonly ConfigServiceInterface $configService,
        private readonly FormBotProtection $botProtection,
    ) {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $touUrl = t('label.accept_tou', ['%touUrl%' => $this->configService->get('url-terms-of-use')], 'site');

        $this->botProtection->addHoneypotField($builder);

        $builder
            ->add('email', null, [
                'label' => 'label.email',
                'translation_domain' => 'site',
            ])
            ->add('plainPassword', RepeatedType::class, [
                'type' => PasswordType::class,
                'translation_domain' => 'site',
                'options' => [
                    'attr' => ['autocomplete' => 'new-password'],
                ],
                'first_name' => 'plainPassword',
                'second_name' => 'confirmPassword',
                'first_options' => [
                    'label' => 'label.password',
                    'help' => 'label.password_help',
                    'constraints' => [
                        new NotBlank(
                            message: 'text.password_required',
                        ),
                        new Length(
                            min: 8,
                            max: 25,
                            minMessage: 'text.password_min_length',
                            maxMessage: 'text.password_max_length',
                        ),
                        new Regex(
                            pattern: '/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[^A-Za-z0-9]).{8,}$/',
                            message: 'text.password_complexity',
                        ),
                    ],
                ],
                'second_options' => [
                    'label' => 'label.password_confirm',
                ],
                'invalid_message' => 'text.password_mismatch',
                // instead of being set onto the object directly,
                // this is read and encoded in the controller
                'mapped' => false,
            ])
            // Terms of use
            ->add('cgu', CheckboxType::class, [
                'label' => $touUrl,
                'label_html' => true,
                'required' => true,
                'mapped' => false
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
        $resolver->setDefaults([
            'data_class' => User::class,
        ]);
    }
}
