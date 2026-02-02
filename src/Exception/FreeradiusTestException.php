<?php

namespace App\Exception;

use RuntimeException;
use Throwable;

class FreeradiusTestException extends RuntimeException
{
    private array $context;
    private string $translationDomain = 'FreeradiusTestException';

    public function __construct(
        string $messageKey,
        array $context = [],
        int $code = 0,
        ?Throwable $previous = null
    ) {
        parent::__construct($messageKey, $code, $previous);
        $this->context = $context;
    }

    public function getContext(): array
    {
        return $this->context;
    }

    public function getTranslationDomain(): string
    {
        return $this->translationDomain;
    }


    public static function certificateExpired(string $subject, string $expiryDate): self
    {
        return new self(
            'certificate_expired',
            [
                'subject' => $subject,
                'expiryDate' => $expiryDate,
            ]
        );
    }

    public static function certificateNotYetValid(string $subject, string $validFrom): self
    {
        return new self(
            'certificate_not_yet_valid',
            [
                'subject' => $subject,
                'validFrom' => $validFrom,
            ]
        );
    }

    public static function noCertificateProvided(): self
    {
        return new self('no_certificate_provided');
    }

    public static function invalidCertificateChain(): self
    {
        return new self('invalid_certificate_chain');
    }

    public static function certificateMismatch(): self
    {
        return new self('certificate_mismatch');
    }

    public static function generic(string $messageKey, array $context = []): self
    {
        return new self($messageKey, $context);
    }
}
