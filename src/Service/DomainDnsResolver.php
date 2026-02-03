<?php

namespace App\Service;

class DomainDnsResolver
{
    /**
     * Check if a domain has DNS records of the given types.
     *
     * @param string $domain The domain to check
     * @param int|int[] $types One or more DNS_* constants (e.g., DNS_A, DNS_MX, DNS_SOA)
     *
     * @return bool True if at least one record exists for any of the types
     */
    public function resolver(string $domain, int|array $types = DNS_A): bool
    {
        try {
            if (is_array($types)) {
                $combined = 0;
                foreach ($types as $type) {
                    $combined |= $type;
                }
            } else {
                $combined = $types;
            }

            $records = @dns_get_record($domain, $combined);
        } catch (\Throwable) {
            return false;
        }

        return $records !== [] && $records !== false;
    }
}
