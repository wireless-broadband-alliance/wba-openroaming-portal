<?php

namespace App\Service;

class HashArgon2idService
{
    /**
     * Generates an irreversible Argon2id hash for validation-only secrets.
     */
    public function hash(string $plainText): string
    {
        return password_hash($plainText, PASSWORD_ARGON2ID);
    }

    /**
     * Verifies if a plain text value matches an Argon2id hash.
     */
    public function verifyHash(string $plainText, string $hash): bool
    {
        return password_verify($plainText, $hash);
    }

}