<?php

namespace App\Tests\Validator\Constraints;

use App\Validator\Constraints\DnsEmail;
use App\Validator\Constraints\DnsEmailValidator;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Validator\Test\ConstraintValidatorTestCase;

class DnsEmailValidatorTest extends ConstraintValidatorTestCase
{
    private ArrayAdapter $cache;

    protected function createValidator(): DnsEmailValidator
    {
        $this->cache = new ArrayAdapter();

        return new DnsEmailValidator($this->cache);
    }

    public function testNullIsValid(): void
    {
        $this->validator->validate(null, new DnsEmail());

        $this->assertNoViolation();
    }

    public function testEmptyStringIsValid(): void
    {
        $this->validator->validate('', new DnsEmail());

        $this->assertNoViolation();
    }

    // gmail.com has a stable MX record, so this exercises the "domain accepts mail" path
    // without depending on a domain we control
    public function testAddressWithResolvableDomainIsValid(): void
    {
        $this->validator->validate('someone@gmail.com', new DnsEmail());

        $this->assertNoViolation();
    }

    // ".invalid" is reserved by RFC 2606 to never resolve, so this is a deterministic,
    // non-flaky way to exercise the rejection path
    public function testAddressWithUnresolvableDomainRaisesViolation(): void
    {
        $constraint = new DnsEmail();

        $this->validator->validate('someone@definitely-not-a-real-domain-xyz123.invalid', $constraint);

        $this->buildViolation($constraint->message)
            ->assertRaised();
    }

    // The DNS check result is cached per domain (not per address), so a repeated hit on the
    // same domain (bot flood, or an admin re-saving the same user) skips the live lookup
    public function testUnresolvableDomainResultIsCachedPerDomain(): void
    {
        $this->validator->validate('someone@definitely-not-a-real-domain-xyz123.invalid', new DnsEmail());

        $this->assertTrue(
            $this->cache->getItem('dns_email_' . md5('definitely-not-a-real-domain-xyz123.invalid'))->isHit()
        );
    }
}
