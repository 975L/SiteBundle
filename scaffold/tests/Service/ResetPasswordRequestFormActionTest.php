<?php

namespace App\Tests\Service;

use App\Entity\User;
use App\Service\ResetPasswordRequestFormAction;
use c975L\UiBundle\Entity\Form;
use c975L\UiBundle\Service\EmailService;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use PHPUnit\Framework\TestCase;
use Symfony\Contracts\Translation\TranslatorInterface;
use SymfonyCasts\Bundle\ResetPassword\Exception\TooManyPasswordRequestsException;
use SymfonyCasts\Bundle\ResetPassword\Model\ResetPasswordToken;
use SymfonyCasts\Bundle\ResetPassword\ResetPasswordHelperInterface;

class ResetPasswordRequestFormActionTest extends TestCase
{
    private function createEntityManager(?User $user): EntityManagerInterface
    {
        $repository = $this->createStub(EntityRepository::class);
        $repository->method('findOneBy')->willReturn($user);

        $entityManager = $this->createStub(EntityManagerInterface::class);
        $entityManager->method('getRepository')->willReturnMap([
            [User::class, $repository],
        ]);

        return $entityManager;
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
            $this->createStub(EmailService::class),
            $this->createTranslator(),
        );

        $this->assertSame('reset_password_request', $action->getKey());
    }

    // Never reveals whether an account exists - same generic "form_submitted" flash either way
    public function testHandleReturnsTrueWithoutSendingWhenUserIsNotFound(): void
    {
        $emailService = $this->createMock(EmailService::class);
        $emailService->expects($this->never())->method('send');

        $action = new ResetPasswordRequestFormAction(
            $this->createEntityManager(null),
            $this->createStub(ResetPasswordHelperInterface::class),
            $emailService,
            $this->createTranslator(),
        );

        $this->assertTrue($action->handle(new Form(), ['email' => 'unknown@example.test']));
    }

    public function testHandleReturnsTrueWithoutSendingWhenTokenGenerationFails(): void
    {
        $resetPasswordHelper = $this->createStub(ResetPasswordHelperInterface::class);
        $resetPasswordHelper->method('generateResetToken')->willThrowException(new TooManyPasswordRequestsException(new \DateTime()));

        $emailService = $this->createMock(EmailService::class);
        $emailService->expects($this->never())->method('send');

        $action = new ResetPasswordRequestFormAction(
            $this->createEntityManager(new User()),
            $resetPasswordHelper,
            $emailService,
            $this->createTranslator(),
        );

        $this->assertTrue($action->handle(new Form(), ['email' => 'someone@example.test']));
    }

    public function testHandleSendsResetEmailAndReturnsTrueWhenUserIsFound(): void
    {
        $resetPasswordHelper = $this->createStub(ResetPasswordHelperInterface::class);
        $resetPasswordHelper->method('generateResetToken')->willReturn(new ResetPasswordToken('token', new \DateTime('+1 hour'), time()));

        $emailService = $this->createMock(EmailService::class);
        $emailService->expects($this->once())->method('send');

        $action = new ResetPasswordRequestFormAction(
            $this->createEntityManager((new User())->setEmail('someone@example.test')),
            $resetPasswordHelper,
            $emailService,
            $this->createTranslator(),
        );

        $this->assertTrue($action->handle(new Form(), ['email' => 'someone@example.test']));
    }
}
