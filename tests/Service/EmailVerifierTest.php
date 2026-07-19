<?php
/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\SiteBundle\Tests\Service;

use c975L\SiteBundle\Service\EmailVerifier;
use c975L\SiteBundle\Tests\Fixtures\UserStub;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\TestCase;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Mailer\MailerInterface;
use SymfonyCasts\Bundle\VerifyEmail\Model\VerifyEmailSignatureComponents;
use SymfonyCasts\Bundle\VerifyEmail\VerifyEmailHelperInterface;

// Moved from the app-copied scaffold (previously App\Tests\Security\EmailVerifierTest) alongside the class it tests - see UPGRADE.md
class EmailVerifierTest extends TestCase
{
    public function testSendEmailConfirmationSignsAndSendsEmail(): void
    {
        $user = (new UserStub('user@example.test'))->withId(42);

        $signature = new VerifyEmailSignatureComponents(
            new \DateTimeImmutable('+1 hour'),
            'https://example.test/verification/email?signed=1',
            time()
        );

        $verifyEmailHelper = $this->createMock(VerifyEmailHelperInterface::class);
        $verifyEmailHelper->expects($this->once())
            ->method('generateSignature')
            ->with('app_verify_email', '42', 'user@example.test', ['id' => 42])
            ->willReturn($signature);

        $mailer = $this->createMock(MailerInterface::class);
        $mailer->expects($this->once())
            ->method('send')
            ->with($this->callback(function (TemplatedEmail $email) use ($signature) {
                $context = $email->getContext();

                return $context['signedUrl'] === $signature->getSignedUrl()
                    && $context['expiresAtMessageKey'] === $signature->getExpirationMessageKey()
                    && $context['expiresAtMessageData'] === $signature->getExpirationMessageData();
            }));

        $entityManager = $this->createStub(EntityManagerInterface::class);

        $emailVerifier = new EmailVerifier($verifyEmailHelper, $mailer, $entityManager);
        $emailVerifier->sendEmailConfirmation('app_verify_email', $user, new TemplatedEmail());
    }

    public function testHandleEmailConfirmationMarksUserAsVerifiedAndEnabled(): void
    {
        $user = new UserStub('user@example.test');
        $request = Request::create('/verification/email?id=42');

        // validateEmailConfirmationFromRequest() is only documented via @method on the interface (it lives on the final VerifyEmailHelper class), so PHPUnit's mock generator can't stub it from the interface alone. A hand-written stub can.
        $verifyEmailHelper = new class($request, $user) implements VerifyEmailHelperInterface {
            public int $calls = 0;

            public function __construct(
                private readonly Request $expectedRequest,
                private readonly UserStub $expectedUser,
            ) {
            }

            /** @param array<string, mixed> $extraParams */
            public function generateSignature(string $routeName, string $userId, string $userEmail, array $extraParams = []): VerifyEmailSignatureComponents
            {
                throw new \LogicException('Not expected to be called.');
            }

            public function validateEmailConfirmation(string $signedUrl, string $userId, string $userEmail): void
            {
                throw new \LogicException('Not expected to be called.');
            }

            public function validateEmailConfirmationFromRequest(Request $request, string $userId, string $userEmail): void
            {
                Assert::assertSame($this->expectedRequest, $request);
                Assert::assertSame('', $userId);
                Assert::assertSame($this->expectedUser->getUserIdentifier(), $userEmail);
                ++$this->calls;
            }
        };

        $mailer = $this->createStub(MailerInterface::class);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects($this->once())->method('persist')->with($user);
        $entityManager->expects($this->once())->method('flush');

        $emailVerifier = new EmailVerifier($verifyEmailHelper, $mailer, $entityManager);
        $emailVerifier->handleEmailConfirmation($request, $user);

        $this->assertSame(1, $verifyEmailHelper->calls);
        $this->assertTrue($user->isVerified());
        $this->assertTrue($user->isEnabled());
    }
}
