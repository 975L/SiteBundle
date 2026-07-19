<?php

namespace App\Tests\Service;

use App\Entity\User;
use App\Service\ResetPasswordRequestFormAction;
use c975L\ConfigBundle\Service\ConfigServiceInterface;
use c975L\UiBundle\Entity\Form;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ObjectRepository;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use SymfonyCasts\Bundle\ResetPassword\Exception\TooManyPasswordRequestsException;
use SymfonyCasts\Bundle\ResetPassword\Model\ResetPasswordToken;
use SymfonyCasts\Bundle\ResetPassword\ResetPasswordHelperInterface;

class ResetPasswordRequestFormActionTest extends TestCase
{
    private function createEntityManager(?User $user): EntityManagerInterface
    {
        $repository = $this->createStub(ObjectRepository::class);
        $repository->method('findOneBy')->willReturn($user);

        $entityManager = $this->createStub(EntityManagerInterface::class);
        $entityManager->method('getRepository')->with(User::class)->willReturn($repository);

        return $entityManager;
    }

    private function createConfigService(): ConfigServiceInterface
    {
        $configService = $this->createStub(ConfigServiceInterface::class);
        $configService->method('get')->willReturnCallback(
            static fn (string $key) => match ($key) {
                'email-from' => 'noreply@example.test',
                'email-from-name' => 'Example',
                default => null,
            }
        );

        return $configService;
    }

    private function createTranslator(): TranslatorInterface
    {
        $translator = $this->createStub(TranslatorInterface::class);
        $translator->method('trans')->willReturnArgument(0);

        return $translator;
    }

    public function testGetKeyReturnsResetPasswordRequest(): void
    {
        $action = new ResetPasswordRequestFormAction(
            $this->createEntityManager(null),
            $this->createStub(ResetPasswordHelperInterface::class),
            $this->createConfigService(),
            $this->createStub(MailerInterface::class),
            $this->createTranslator(),
        );

        $this->assertSame('reset_password_request', $action->getKey());
    }

    // Never reveals whether an account exists - same generic "form_submitted" flash either way
    public function testHandleReturnsTrueWithoutSendingWhenUserIsNotFound(): void
    {
        $mailer = $this->createMock(MailerInterface::class);
        $mailer->expects($this->never())->method('send');

        $action = new ResetPasswordRequestFormAction(
            $this->createEntityManager(null),
            $this->createStub(ResetPasswordHelperInterface::class),
            $this->createConfigService(),
            $mailer,
            $this->createTranslator(),
        );

        $this->assertTrue($action->handle(new Form(), ['email' => 'unknown@example.test']));
    }

    public function testHandleReturnsTrueWithoutSendingWhenTokenGenerationFails(): void
    {
        $resetPasswordHelper = $this->createStub(ResetPasswordHelperInterface::class);
        $resetPasswordHelper->method('generateResetToken')->willThrowException(new TooManyPasswordRequestsException(new \DateTime()));

        $mailer = $this->createMock(MailerInterface::class);
        $mailer->expects($this->never())->method('send');

        $action = new ResetPasswordRequestFormAction(
            $this->createEntityManager(new User()),
            $resetPasswordHelper,
            $this->createConfigService(),
            $mailer,
            $this->createTranslator(),
        );

        $this->assertTrue($action->handle(new Form(), ['email' => 'someone@example.test']));
    }

    public function testHandleSendsResetEmailAndReturnsTrueWhenUserIsFound(): void
    {
        $resetPasswordHelper = $this->createStub(ResetPasswordHelperInterface::class);
        $resetPasswordHelper->method('generateResetToken')->willReturn(new ResetPasswordToken('token', new \DateTime('+1 hour')));

        $mailer = $this->createMock(MailerInterface::class);
        $mailer->expects($this->once())->method('send');

        $action = new ResetPasswordRequestFormAction(
            $this->createEntityManager((new User())->setEmail('someone@example.test')),
            $resetPasswordHelper,
            $this->createConfigService(),
            $mailer,
            $this->createTranslator(),
        );

        $this->assertTrue($action->handle(new Form(), ['email' => 'someone@example.test']));
    }
}
