<?php

namespace App\Service;

class DomainService
{
    // Validate domain names and check if they resolve to an IP address
    // Validation comes from here: https://www.php.net/manual/en/function.dns-get-record.php
    /**
     * Check whether a given domain name is syntactically valid and resolvable.
     *
     * @param non-empty-string $domain
     */
    public function isValidDomain(string $domain): bool
    {
        if (!filter_var($domain, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME)) {
            return false;
        }

        $dnsRecords = @dns_get_record($domain, DNS_A + DNS_AAAA);

        return $dnsRecords !== false && $dnsRecords !== [];
    }
}
