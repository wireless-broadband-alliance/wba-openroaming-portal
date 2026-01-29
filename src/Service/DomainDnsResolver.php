<?php

namespace App\Service;

class DomainDnsResolver
{
    public function resolver(string $domain): bool
    {
        try {
            $records = @dns_get_record($domain, DNS_A | DNS_AAAA | DNS_CNAME);
        } catch (\Throwable) {
            return false;
        }

        return !($records === false || empty($records));
    }
}
