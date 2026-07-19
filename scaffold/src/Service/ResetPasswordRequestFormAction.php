<?php

namespace App\Service;

use App\Entity\User;
use c975L\ConfigBundle\Service\ConfigServiceInterface;
use c975L\UiBundle\Contract\FormActionInterface;
use c975L\UiBundle\Contract\RequiresAnonymousInterface;
use c975L\UiBundle\Entity\Form;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Contracts\Translation\TranslatorInterface;
use SymfonyCasts\Bundle\ResetPassword\Exception\ResetPasswordExceptionInterface;
use SymfonyCasts\Bundle\ResetPassword\ResetPasswordHelperInterface;

// FormActionInterface provider (key "reset_password_request") for the generic "form" Block/c975L\UiBundle\Controller\FormController - see RegisterFormAction for the equivalent register-side rationale; the Form itself is seeded by DefaultPagesImporter. Also implements RequiresAnonymousInterface: an already-authenticated visitor gets an "already logged in" notice instead of the form.
class ResetPasswordRequestFormAction implements FormActionInterface, RequiresAnonymousInterface
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly ResetPasswordHelperInterface $resetPasswordHelper,
        private readonly ConfigServiceInterface $configService,
        private readonly MailerInterface $mailer,
        private readonly TranslatorInterface $translator,
    ) {
    }

    public function getKey(): string
    {
        return 'reset_password_request';
    }

    // Always returns true, whether or not the submitted email matches an account - never reveals which, same generic "form_submitted" flash either way (see c975L\UiBundle\Controller\FormController::submit())
    public function handle(Form $form, array $submittedData): bool
    {
        $user = $this->entityManager->getRepository(User::class)->findOneBy(['email' => $submittedData['email']]);
        if (null === $user) {
            return true;
        }

        try {
            $resetToken = $this->resetPasswordHelper->generateResetToken($user);
        } catch (ResetPasswordExceptionInterface) {
            return true;
        }

        $email = new TemplatedEmail()
            ->from(new Address($this->configService->get('email-from'), $this->configService->get('email-from-name')))
            ->to((string) $user->getEmail())
            ->subject($this->translator->trans('label.password_reset_request', [], 'site'))
            ->htmlTemplate('@c975LSite/emails/reset_password_email.html.twig')
            ->context([
                'resetToken' => $resetToken,
            ])
        ;

        $this->mailer->send($email);

        return true;
    }
}
