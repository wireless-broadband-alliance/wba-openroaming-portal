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
        return (bool) filter_var(
            $domain,
            FILTER_VALIDATE_DOMAIN,
            FILTER_FLAG_HOSTNAME
        );
    }

    /**
     * @return iterable<string>
     */
    public function extract(string $content): iterable
    {
        // Try JSON
        try {
            $data = json_decode($content, true, flags: JSON_THROW_ON_ERROR);

            if (is_array($data)) {
                foreach ($data as $value) {
                    if (is_string($value)) {
                        yield $value;
                    }
                }
                return;
            }
        } catch (\JsonException) {
            // Not JSON
        }

        // Plain text
        foreach (preg_split('/\r\n|\r|\n/', $content) as $line) {
            $line = trim($line);

            if ($line !== '') {
                yield $line;
            }
        }
    }
}
