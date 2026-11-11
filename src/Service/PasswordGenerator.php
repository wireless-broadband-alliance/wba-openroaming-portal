<?php

declare(strict_types=1);

namespace App\Service;

use InvalidArgumentException;
use Random\RandomException;

/**
 * Generates the temporary passwords the platform creates on the user's side
 * (email/SMS registration, magic link account creation, SMS password reset).
 *
 * The output meets the same policy as user-chosen passwords (NewPasswordAccountDTO /
 * UserSMSRegistrationDTO) by construction, so it never needs to be validated at runtime:
 *  - Length: 16 characters (policy minimum is 16);
 *  - PasswordStrength (MEDIUM): at least one lowercase, one uppercase and one digit, and no repeated
 *    characters. Symfony's estimator scores unique characters, so 16 unique characters from the
 *    62-character pool always score ~95 bits (MEDIUM needs 80). With repeats allowed, about 1 in 450
 *    passwords would fall below MEDIUM;
 *  - NotCompromisedPassword: not checked on purpose. The value comes from a CSPRNG and has never existed
 *    before, so a Have I Been Pwned lookup cannot match it and would only add an outbound HTTP call
 *    to every registration and reset.
 *
 * Look-alike characters (0/O, 1/l/I) are left out because the password is typed from an SMS or email.
 */
final class PasswordGenerator
{
    public const int DEFAULT_LENGTH = 16;

    private const string LOWER = 'abcdefghijkmnopqrstuvwxyz';
    private const string UPPER = 'ABCDEFGHJKLMNPQRSTUVWXYZ';
    private const string DIGITS = '23456789';

    /**
     * @throws RandomException
     */
    public function generate(int $length = self::DEFAULT_LENGTH): string
    {
        $poolSize = strlen(self::LOWER . self::UPPER . self::DIGITS);

        if ($length < self::DEFAULT_LENGTH || $length > $poolSize) {
            throw new InvalidArgumentException(
                sprintf(
                    'Generated passwords must be between %d and %d characters long.',
                    self::DEFAULT_LENGTH,
                    $poolSize
                )
            );
        }

        $lower = str_split(self::LOWER);
        $upper = str_split(self::UPPER);
        $digits = str_split(self::DIGITS);

        // One character from each class, so the strength estimator always sees the full character pool
        $chars = [
            $this->take($lower),
            $this->take($upper),
            $this->take($digits),
        ];

        // The rest without replacement: no character is ever repeated
        $remaining = array_merge($lower, $upper, $digits);
        while (count($chars) < $length) {
            $chars[] = $this->take($remaining);
        }

        // Fisher-Yates with random_int, so the guaranteed characters are not always at the start
        for ($i = count($chars) - 1; $i > 0; --$i) {
            $j = random_int(0, $i);
            [$chars[$i], $chars[$j]] = [$chars[$j], $chars[$i]];
        }

        return implode('', $chars);
    }

    /**
     * Removes and returns a random character from the given pool.
     *
     * @param list<string> $pool
     *
     * @throws RandomException
     */
    private function take(array &$pool): string
    {
        $index = random_int(0, count($pool) - 1);
        $char = $pool[$index];
        array_splice($pool, $index, 1);

        return $char;
    }
}
