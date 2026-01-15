<?php

namespace App\Service;

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

    public function isValidDomain(string $domain): bool
    {
        return (bool)filter_var(
            $domain,
            FILTER_VALIDATE_DOMAIN,
            FILTER_FLAG_HOSTNAME
        );
    }
}
