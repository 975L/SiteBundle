<?php
/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\SiteBundle\Service;

use c975L\UiBundle\Model\EmailSendRequest;
use c975L\UiBundle\Service\EmailService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\User\UserInterface;
use SymfonyCasts\Bundle\VerifyEmail\VerifyEmailHelperInterface;

// Moved from the app-copied scaffold (previously App\Security\EmailVerifier) so it's shared, tested bundle code instead of duplicated per app - see UPGRADE.md. Only relies on UserInterface::getUserIdentifier() (guaranteed) plus method_exists() duck-typing for getId()/setIsVerified()/setIsEnabled(), which aren't part of any Security interface - App\Entity\User (app-space, this bundle can't reference it directly) is expected to expose them.
class EmailVerifier
{
    public function __construct(
        private readonly VerifyEmailHelperInterface $verifyEmailHelper,
        private readonly EmailService $emailService,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    // Sent through EmailService (not MailerInterface directly) so registration confirmation gets the same
    // ROLE_SUPER_ADMIN "email-debug" preview as every other c975L email, instead of always really sending
    public function sendEmailConfirmation(string $verifyEmailRouteName, UserInterface $user, string $subject, string $template, string $to): bool
    {
        $signatureComponents = $this->verifyEmailHelper->generateSignature(
            $verifyEmailRouteName,
            (string) $this->getUserId($user),
            $user->getUserIdentifier(),
            ['id' => $this->getUserId($user)]
        );

        return $this->emailService->send(new EmailSendRequest(
            subject: $subject,
            context: [
                'signedUrl' => $signatureComponents->getSignedUrl(),
                'expiresAtMessageKey' => $signatureComponents->getExpirationMessageKey(),
                'expiresAtMessageData' => $signatureComponents->getExpirationMessageData(),
            ],
            template: $template,
            to: $to,
        ));
    }

    public function handleEmailConfirmation(Request $request, UserInterface $user): void
    {
        $this->verifyEmailHelper->validateEmailConfirmationFromRequest($request, (string) $this->getUserId($user), $user->getUserIdentifier());

        if (method_exists($user, 'setIsVerified')) {
            $user->setIsVerified(true);
        }
        if (method_exists($user, 'setIsEnabled')) {
            $user->setIsEnabled(true);
        }

        $this->entityManager->persist($user);
        $this->entityManager->flush();
    }

    // App\Entity\User (app-space, this bundle can't reference it directly) is expected to expose getId() - duck-typed since it's not part of any Security interface
    private function getUserId(UserInterface $user): ?int
    {
        return method_exists($user, 'getId') ? $user->getId() : null;
    }
}
