<?php

namespace App\Tests\Form;

use App\Entity\User;
use App\Form\RegistrationFormType;
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
class RegistrationFormTypeTest extends TypeTestCase
{
    private ConfigServiceInterface&Stub $configService;

    protected function setUp(): void
    {
        $this->configService = $this->createStub(ConfigServiceInterface::class);
        $this->configService->method('get')->willReturnMap([
            ['url-terms-of-use', 'https://example.test/terms-of-use'],
        ]);

        parent::setUp();
    }

    // Uses a real validator (instead of TypeTestCase's mocked one) so the
    // password Length/Regex constraints are actually exercised.
    protected function getExtensions(): array
    {
        return [
            new ValidatorExtension(Validation::createValidator()),
            new PreloadedExtension([new RegistrationFormType($this->configService, new FormBotProtection($this->configService))], []),
        ];
    }

    public function testSubmitValidDataPopulatesUser(): void
    {
        $formData = [
            'email' => 'user@example.test',
            'plainPassword' => [
                'plainPassword' => 'Str0ng!Password',
                'confirmPassword' => 'Str0ng!Password',
            ],
            'cgu' => true,
        ];

        $user = new User();
        $form = $this->factory->create(RegistrationFormType::class, $user);
        $form->submit($formData);

        $this->assertTrue($form->isSynchronized());
        $this->assertTrue($form->isValid());
        $this->assertSame('user@example.test', $user->getEmail());
        $this->assertSame('Str0ng!Password', $form->get('plainPassword')->getData());
    }

    public function testSubmitInvalidWhenPasswordConfirmationMismatches(): void
    {
        $formData = [
            'email' => 'user@example.test',
            'plainPassword' => [
                'plainPassword' => 'Str0ng!Password',
                'confirmPassword' => 'Different!Password1',
            ],
            'cgu' => true,
        ];

        $form = $this->factory->create(RegistrationFormType::class, new User());
        $form->submit($formData);

        $this->assertFalse($form->isValid());
    }

    public function testSubmitInvalidWhenPasswordTooShort(): void
    {
        $formData = [
            'email' => 'user@example.test',
            'plainPassword' => [
                'plainPassword' => 'Aa1!',
                'confirmPassword' => 'Aa1!',
            ],
            'cgu' => true,
        ];

        $form = $this->factory->create(RegistrationFormType::class, new User());
        $form->submit($formData);

        $this->assertFalse($form->isValid());
    }

    public function testSubmitInvalidWhenPasswordLacksComplexity(): void
    {
        $formData = [
            'email' => 'user@example.test',
            'plainPassword' => [
                'plainPassword' => 'onlylowercase',
                'confirmPassword' => 'onlylowercase',
            ],
            'cgu' => true,
        ];

        $form = $this->factory->create(RegistrationFormType::class, new User());
        $form->submit($formData);

        $this->assertFalse($form->isValid());
    }

    public function testPlainPasswordAndCguAreNotMappedOntoUser(): void
    {
        $form = $this->factory->create(RegistrationFormType::class, new User());

        $this->assertFalse($form->get('plainPassword')->getConfig()->getMapped());
        $this->assertFalse($form->get('cgu')->getConfig()->getMapped());
    }

    public function testHoneypotFieldIsNotMappedAndDefaultsToEmpty(): void
    {
        $form = $this->factory->create(RegistrationFormType::class, new User());

        $this->assertFalse($form->get('website')->getConfig()->getMapped());
        $this->assertSame('', $form->get('website')->getData());
    }

    // 'required' => true on the gdpr CheckboxType is HTML5-only and enforces nothing server-side
    // on its own - only the explicit IsTrue constraint does, this exercises that it's really there
    public function testGdprCheckboxIsRequiredServerSideWhenEnabled(): void
    {
        $configService = $this->createStub(ConfigServiceInterface::class);
        $configService->method('get')->willReturnMap([
            ['url-terms-of-use', 'https://example.test/terms-of-use'],
            ['site-form-gdpr', true],
        ]);

        $factory = Forms::createFormFactoryBuilder()
            ->addExtension(new ValidatorExtension(Validation::createValidator()))
            ->addType(new RegistrationFormType($configService, new FormBotProtection($configService)))
            ->getFormFactory();

        $form = $factory->create(RegistrationFormType::class, new User());
        $form->submit([
            'email' => 'user@example.test',
            'plainPassword' => [
                'plainPassword' => 'Str0ng!Password',
                'confirmPassword' => 'Str0ng!Password',
            ],
            'cgu' => true,
            // gdpr intentionally left unchecked
        ]);

        $this->assertFalse($form->isValid());
    }
}
