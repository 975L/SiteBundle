<?php
/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\SiteBundle\Tests\Service;

use c975L\ConfigBundle\Service\ConfigServiceInterface;
use c975L\SiteBundle\Service\FormBotProtection;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;

class FormBotProtectionTest extends TestCase
{
    private function requestWithSession(): Request
    {
        $request = new Request();
        $request->setSession(new Session(new MockArraySessionStorage()));

        return $request;
    }

    private function formWithWebsiteField(string $websiteValue): FormInterface
    {
        $websiteField = $this->createStub(FormInterface::class);
        $websiteField->method('getData')->willReturn($websiteValue);

        $form = $this->createStub(FormInterface::class);
        $form->method('get')->willReturnMap([['website', $websiteField]]);

        return $form;
    }

    public function testAddHoneypotFieldAddsUnmappedOffscreenWebsiteField(): void
    {
        $builder = $this->createMock(FormBuilderInterface::class);
        $builder->expects($this->once())
            ->method('add')
            ->with(
                'website',
                null,
                $this->callback(static function (array $options): bool {
                    return false === $options['required']
                        && false === $options['mapped']
                        && '' === $options['data'];
                })
            )
            ->willReturn($builder);

        (new FormBotProtection($this->createStub(ConfigServiceInterface::class)))->addHoneypotField($builder);
    }

    public function testStartTimerOnlySetsTimestampOnce(): void
    {
        $botProtection = new FormBotProtection($this->createStub(ConfigServiceInterface::class));
        $request = $this->requestWithSession();

        $botProtection->startTimer($request, 'test_started_at');
        $firstTimestamp = $request->getSession()->get('test_started_at');

        $botProtection->startTimer($request, 'test_started_at');

        $this->assertSame($firstTimestamp, $request->getSession()->get('test_started_at'));
    }

    public function testIsSuspiciousWhenHoneypotFilled(): void
    {
        $configService = $this->createStub(ConfigServiceInterface::class);
        $configService->method('get')->willReturnMap([['site-form-delay', 3]]);

        $request = $this->requestWithSession();
        $request->getSession()->set('test_started_at', time() - 60);

        $botProtection = new FormBotProtection($configService);

        $this->assertTrue($botProtection->isSuspicious(
            $request,
            $this->formWithWebsiteField('https://spam.example'),
            'test_started_at'
        ));
    }

    public function testIsSuspiciousWhenSubmittedFasterThanDelay(): void
    {
        $configService = $this->createStub(ConfigServiceInterface::class);
        $configService->method('get')->willReturnMap([['site-form-delay', 60]]);

        $request = $this->requestWithSession();
        $request->getSession()->set('test_started_at', time());

        $botProtection = new FormBotProtection($configService);

        $this->assertTrue($botProtection->isSuspicious(
            $request,
            $this->formWithWebsiteField(''),
            'test_started_at'
        ));
    }

    public function testIsSuspiciousFalseForLegitimateSubmission(): void
    {
        $configService = $this->createStub(ConfigServiceInterface::class);
        $configService->method('get')->willReturnMap([['site-form-delay', 3]]);

        $request = $this->requestWithSession();
        $request->getSession()->set('test_started_at', time() - 60);

        $botProtection = new FormBotProtection($configService);

        $this->assertFalse($botProtection->isSuspicious(
            $request,
            $this->formWithWebsiteField(''),
            'test_started_at'
        ));
    }

    // "site-form-delay" isn't seeded when c975l/config-bundle hasn't loaded it yet - falls back to 7s
    public function testIsSuspiciousFallsBackTo7SecondsWhenDelayNotSeeded(): void
    {
        $configService = $this->createStub(ConfigServiceInterface::class);
        $configService->method('get')->willReturnMap([['site-form-delay', null]]);

        $request = $this->requestWithSession();
        $request->getSession()->set('test_started_at', time());

        $botProtection = new FormBotProtection($configService);

        $this->assertTrue($botProtection->isSuspicious(
            $request,
            $this->formWithWebsiteField(''),
            'test_started_at'
        ));
    }

    public function testIsSuspiciousRemovesTimestampFromSession(): void
    {
        $configService = $this->createStub(ConfigServiceInterface::class);
        $configService->method('get')->willReturnMap([['site-form-delay', 3]]);

        $request = $this->requestWithSession();
        $request->getSession()->set('test_started_at', time() - 60);

        $botProtection = new FormBotProtection($configService);
        $botProtection->isSuspicious($request, $this->formWithWebsiteField(''), 'test_started_at');

        $this->assertFalse($request->getSession()->has('test_started_at'));
    }
}
