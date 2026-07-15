<?php

namespace App\Tests\Form;

use App\Form\ResetPasswordRequestFormType;
use c975L\ConfigBundle\Service\ConfigServiceInterface;
use c975L\SiteBundle\Service\FormBotProtection;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\MockObject\Stub;
use Symfony\Component\Form\Extension\Validator\ValidatorExtension;
use Symfony\Component\Form\Forms;
use Symfony\Component\Form\PreloadedExtension;
use Symfony\Component\Form\Test\TypeTestCase;
use Symfony\Component\Validator\Validation;

// TypeTestCase::setUp() creates an EventDispatcherInterface mock without
// expectations; opt out of PHPUnit's stub-instead-of-mock notice for it.
#[AllowMockObjectsWithoutExpectations]
class ResetPasswordRequestFormTypeTest extends TypeTestCase
{
    private ConfigServiceInterface&Stub $configService;

    protected function setUp(): void
    {
        $this->configService = $this->createStub(ConfigServiceInterface::class);
        $this->configService->method('get')->willReturnMap([
            ['site-form-gdpr', false],
        ]);

        parent::setUp();
    }

    // Uses a real validator so the NotBlank constraint is actually exercised.
    protected function getExtensions(): array
    {
        return [
            new ValidatorExtension(Validation::createValidator()),
            new PreloadedExtension([new ResetPasswordRequestFormType($this->configService, new FormBotProtection($this->configService))], []),
        ];
    }

    public function testSubmitValidEmail(): void
    {
        $form = $this->factory->create(ResetPasswordRequestFormType::class);
        $form->submit(['email' => 'user@example.test']);

        $this->assertTrue($form->isSynchronized());
        $this->assertTrue($form->isValid());
        $this->assertSame('user@example.test', $form->getData()['email']);
    }

    public function testSubmitInvalidWhenEmailIsBlank(): void
    {
        $form = $this->factory->create(ResetPasswordRequestFormType::class);
        $form->submit(['email' => '']);

        $this->assertFalse($form->isValid());
    }

    public function testHoneypotFieldIsNotMappedAndDefaultsToEmpty(): void
    {
        $form = $this->factory->create(ResetPasswordRequestFormType::class);

        $this->assertFalse($form->get('website')->getConfig()->getMapped());
        $this->assertSame('', $form->get('website')->getData());
    }

    // 'required' => true on the gdpr CheckboxType is HTML5-only and enforces nothing server-side
    // on its own - only the explicit IsTrue constraint does, this exercises that it's really there
    public function testGdprCheckboxIsRequiredServerSideWhenEnabled(): void
    {
        $configService = $this->createStub(ConfigServiceInterface::class);
        $configService->method('get')->willReturnMap([
            ['site-form-gdpr', true],
        ]);

        $factory = Forms::createFormFactoryBuilder()
            ->addExtension(new ValidatorExtension(Validation::createValidator()))
            ->addType(new ResetPasswordRequestFormType($configService, new FormBotProtection($configService)))
            ->getFormFactory();

        $form = $factory->create(ResetPasswordRequestFormType::class);
        $form->submit([
            'email' => 'user@example.test',
            // gdpr intentionally left unchecked
        ]);

        $this->assertFalse($form->isValid());
    }
}
