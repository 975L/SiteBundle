<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\SiteBundle\Tests\Form\Block;

use c975L\SiteBundle\Form\Block\TwigContentType;
use c975L\SiteBundle\Service\TwigContentTemplateChecker;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Form\Extension\Validator\ValidatorExtension;
use Symfony\Component\Form\Test\TypeTestCase;
use Symfony\Component\Validator\Constraints\Callback;
use Symfony\Component\Validator\Context\ExecutionContextInterface;
use Symfony\Component\Validator\Validation;
use Symfony\Component\Validator\Violation\ConstraintViolationBuilderInterface;

class TwigContentTypeTest extends TypeTestCase
{
    private TwigContentTemplateChecker $checker;

    protected function setUp(): void
    {
        $this->checker = $this->createStub(TwigContentTemplateChecker::class);

        // TypeTestCase would otherwise create a bare, unconfigured mock for this - PHPUnit 13 flags that as a notice ("no expectations configured"); a stub is the correct double for it anyway
        $this->dispatcher = $this->createStub(EventDispatcherInterface::class);

        parent::setUp();
    }

    #[\Override]
    protected function getTypes(): array
    {
        return [new TwigContentType($this->checker)];
    }

    // The "constraints" option only exists once the validator extension is loaded, which the framework does for the real form
    #[\Override]
    protected function getExtensions(): array
    {
        return [new ValidatorExtension(Validation::createValidator())];
    }

    // The refusal has to reach the screen that writes the path, not only the render that reads it back
    public function testTheFieldCarriesTheCallbackConstraint(): void
    {
        $constraints = $this->factory->create(TwigContentType::class, [])->get('templatePath')->getConfig()->getOption('constraints');

        $this->assertCount(1, $constraints);
        $this->assertInstanceOf(Callback::class, $constraints[0]);
    }

    public function testAnAllowedPathRaisesNoViolation(): void
    {
        $this->checker->method('isAllowed')->willReturn(true);

        $context = $this->createMock(ExecutionContextInterface::class);
        $context->expects($this->never())->method('buildViolation');

        new TwigContentType($this->checker)->validateTemplatePath('blocks/mine.html.twig', $context);
    }

    // A block not yet pointed anywhere is not a mistake to report, and the template skips it too
    public function testAnEmptyPathRaisesNoViolation(): void
    {
        $context = $this->createMock(ExecutionContextInterface::class);
        $context->expects($this->never())->method('buildViolation');

        $type = new TwigContentType($this->checker);
        $type->validateTemplatePath(null, $context);
        $type->validateTemplatePath('', $context);
        $type->validateTemplatePath('   ', $context);
    }

    public function testARefusedPathRaisesTheViolation(): void
    {
        $this->checker->method('isAllowed')->willReturn(false);

        $builder = $this->createMock(ConstraintViolationBuilderInterface::class);
        $builder->expects($this->once())->method('setTranslationDomain')->with('site')->willReturnSelf();
        $builder->expects($this->once())->method('addViolation');

        $context = $this->createMock(ExecutionContextInterface::class);
        $context->expects($this->once())
            ->method('buildViolation')
            ->with('label.template_path_refused')
            ->willReturn($builder);

        new TwigContentType($this->checker)->validateTemplatePath('@c975LUi/blocks/Card.html.twig', $context);
    }
}
