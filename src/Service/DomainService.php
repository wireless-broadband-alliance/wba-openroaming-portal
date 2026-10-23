<?php

declare(strict_types=1);

namespace App\Service;

use App\Enum\DomainMatchType;

readonly class DomainService
{
    public function normalize(string $domain): string
    {
        $domain = strtolower(trim($domain));

        // IDN → ASCII (safe)
        $ascii = idn_to_ascii(
            $domain,
            IDNA_DEFAULT,
            INTL_IDNA_VARIANT_UTS46
        );

        return $ascii ?: $domain;
    }

    public function isValidDomain(string $domain, bool $checkDns = true): bool
    {
        $isValidSyntax = (bool)filter_var(
            $domain,
            FILTER_VALIDATE_DOMAIN,
            FILTER_FLAG_HOSTNAME
        );

        if (!$isValidSyntax) {
            return false;
        }

        // Optional: Check if the domain has active DNS records (A, AAAA, CNAME, or MX)
        if ($checkDns) {
            return checkdnsrr($domain, 'A')
                || checkdnsrr($domain, 'AAAA')
                || checkdnsrr($domain, 'CNAME')
                || checkdnsrr($domain, 'MX');
        }

        return true;
    }

    public function detectMatchType(string $domain): DomainMatchType
    {
        return str_starts_with($domain, '*.')
            ? DomainMatchType::SUBDOMAIN
            : DomainMatchType::EXACT;
    }
}
