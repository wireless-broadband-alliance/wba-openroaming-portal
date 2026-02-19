<?php

namespace App\Enum;

enum TrustedWBAFingerprints: string
{
    case OPENROAMING_ROOT_CA = 'fd94138accd8bbaadc09f2a076356a0a93fae5546f4010172858b106ee34b758';
    case OPENROAMING_ROOT_CA_2 = 'a8f187c5314876a35facc69ae2f7c6c33036ab1746763fd4accbe47f20e4d556';
    case OPENROAMING_ISSUING_CA = '105d92ab1cfd9b5a739737f598c12246dfc540cf42fd59c53daf7420966241dc';
    case OPENROAMING_ISSUING7_CA = 'b96f4baba116e608643bfadb82800049f350d71abdf35c1f0926f8d15813c33a';
    case OPENROAMING_POLICY_C7_CA = 'f0f56abf511f40c9b0931efdf983e53a1bca2bd27d8e8f021ae6b283f53f2843';
    case OPENROAMING_CISCO_POLICY_CA = '493670caabfa00055e579915382c83eae3a3a9bbd1d8ad95767275a6b2cd008f';

    /**
     * @return string[]
     */
    public static function all(): array
    {
        return array_map(static fn($c) => $c->value, self::cases());
    }
}
