<?php

declare(strict_types=1);

namespace App\Service;

use App\Exception\InvalidRadiusRealmException;
use Random\RandomException;

/**
 * Generates the RADIUS username and password for a new user profile.
 *
 * The username ("<prefix>@<realm>") is limited to MAX_USERNAME_LENGTH, so the random prefix shrinks as the
 * realm grows. The password has nothing to do with the realm and always has PASSWORD_LENGTH characters.
 */
final readonly class RadiusCredentialGenerator
{
    /** Maximum length of the full RADIUS username, "<prefix>@<realm>" */
    public const int MAX_USERNAME_LENGTH = 32;

    /** Shortest random prefix that still keeps usernames unique across users */
    public const int MIN_USERNAME_PREFIX_LENGTH = 8;

    /** Longest realm that still leaves room for the minimum prefix and the "@" */
    public const int MAX_REALM_LENGTH = self::MAX_USERNAME_LENGTH - 1 - self::MIN_USERNAME_PREFIX_LENGTH;

    /** Fixed password length, independent of the realm */
    public const int PASSWORD_LENGTH = 32;

    private const string ALPHABET = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';

    public static function isRealmSupported(string $realm): bool
    {
        return $realm !== '' && strlen($realm) <= self::MAX_REALM_LENGTH;
    }

    /**
     * @return array{username: string, password: string}
     *
     * @throws InvalidRadiusRealmException when the realm is empty or too long to build a valid username
     * @throws RandomException
     */
    public function generate(string $realm): array
    {
        $realm = trim($realm);

        if (!self::isRealmSupported($realm)) {
            throw new InvalidRadiusRealmException(
                sprintf(
                    'The RADIUS realm "%s" is invalid: it must be between 1 and %d characters long.',
                    $realm,
                    self::MAX_REALM_LENGTH
                )
            );
        }

        $prefixLength = self::MAX_USERNAME_LENGTH - strlen($realm) - 1;

        return [
            'username' => $this->randomString($prefixLength) . '@' . $realm,
            'password' => $this->randomString(self::PASSWORD_LENGTH),
        ];
    }

    /**
     * @throws RandomException
     */
    private function randomString(int $length): string
    {
        $max = strlen(self::ALPHABET) - 1;
        $result = '';

        for ($i = 0; $i < $length; ++$i) {
            $result .= self::ALPHABET[random_int(0, $max)];
        }

        return $result;
    }
}
