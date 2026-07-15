<?php

namespace App\Validator\Constraints;

use Egulias\EmailValidator\EmailValidator;
use Egulias\EmailValidator\Validation\DNSCheckValidation;
use Egulias\EmailValidator\Validation\RFCValidation;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

// Rejects addresses whose domain has no valid MX/A record (e.g. throwaway domains used by
// bots to farm confirmation emails), on top of Assert\Email's format-only check
class DnsEmailValidator extends ConstraintValidator
{
    // DNS records for a domain rarely change within a few hours - caching absorbs repeated
    // hits on the same domain (bot floods, or an admin re-saving the same user) without a live
    // lookup on every single request
    private const CACHE_TTL = 21600;

    public function __construct(
        #[Autowire(service: 'cache.app')]
        private readonly CacheInterface $cache,
    ) {
    }

    public function validate(mixed $value, Constraint $constraint): void
    {
        if (!$constraint instanceof DnsEmail) {
            throw new UnexpectedTypeException($constraint, DnsEmail::class);
        }

        if (null === $value || '' === $value) {
            return;
        }

        $value = (string) $value;
        $emailValidator = new EmailValidator();

        // Format check first, and never cached: it's cheap and specific to this exact value,
        // unlike the DNS check below which only depends on the domain part
        if (!$emailValidator->isValid($value, new RFCValidation())) {
            return;
        }

        $domain = substr($value, strrpos($value, '@') + 1);

        $domainAcceptsMail = $this->cache->get(
            'dns_email_' . md5($domain),
            static function (ItemInterface $item) use ($domain, $emailValidator): bool {
                $item->expiresAfter(self::CACHE_TTL);

                // DNSCheckValidation only ever inspects the domain part, so a synthetic local
                // part is fine here and lets the result be cached per domain, not per address
                return $emailValidator->isValid('postmaster@' . $domain, new DNSCheckValidation());
            }
        );

        if (!$domainAcceptsMail) {
            $this->context->buildViolation($constraint->message)
                ->addViolation();
        }
    }
}
