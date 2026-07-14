<?php

namespace App\Tests\Form;

use App\Form\ResetPasswordRequestFormType;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use Symfony\Component\Form\Extension\Validator\ValidatorExtension;
use Symfony\Component\Form\PreloadedExtension;
use Symfony\Component\Form\Test\TypeTestCase;
use Symfony\Component\Validator\Validation;

// TypeTestCase::setUp() creates an EventDispatcherInterface mock without
// expectations; opt out of PHPUnit's stub-instead-of-mock notice for it.
#[AllowMockObjectsWithoutExpectations]
class ResetPasswordRequestFormTypeTest extends TypeTestCase
{
    // Uses a real validator so the NotBlank constraint is actually exercised.
    protected function getExtensions(): array
    {
        return [
            new ValidatorExtension(Validation::createValidator()),
            new PreloadedExtension([new ResetPasswordRequestFormType()], []),
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
}
