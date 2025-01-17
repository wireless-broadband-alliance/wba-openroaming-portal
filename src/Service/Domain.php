<?php

namespace App\Service;

class Domain
{
    // Validate domain names and check if they resolve to an IP address
    // Validation comes from here: https://www.php.net/manual/en/function.dns-get-record.php
    public function isValidDomain($domain)
    {
        if (!filter_var($domain, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME)) {
            return false;
        }
        $dnsRecords = @dns_get_record($domain, DNS_A + DNS_AAAA);
        return !($dnsRecords === false || empty($dnsRecords));
    }
}
