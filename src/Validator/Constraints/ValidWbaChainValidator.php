<?php

namespace App\Validator\Constraints;

use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\HttpFoundation\File\UploadedFile;

/**
 * Validates whether an uploaded certificate (.pem) belongs
 * to the Wireless Broadband Alliance (WBA) PKI chain.
 */
class ValidWbaChainValidator extends ConstraintValidator
{
    /**
     * WBA root SHA-256 fingerprints (from https://wballiance.com/openroaming/pki-repository/)
     */
    private const WBA_ROOTS_SHA256 = [
        // signed by wba-policy0
        'C62494336C88718F99CBBC7A7670489656B827DBB67A4FB4320757043A2C3918',
        // signed by wba-policy0a
        '69312FC22D66D963532FC0736AAE2530D3F400C1A25E7784F64D2D4003218A78',
        // signed by wba-policy1
        'B96F4BABA116E608643BFADB82800049F350D71ABDF35C1F0926F8D15813C33A',
        '8AF1D1502976CE4950AE1F4A60E963A2841EDB84EAAD3CC370D00DCF6591ED89',
        'F5038C550461E38A232863C25F6C530CC149DFDDB69EE2B50F9C60B4A82875F5',
    ];

    /**
     * WBA-related identifiers used to recognize organization names, CNs, and OUs
     * if fingerprint verification does not match.
     */
    private const POSSIBLE_INDICATORS = [
        'WIRELESS BROADBAND ALLIANCE',
        'WBA',
        'OPENROAMING',
        'WBAPORTAL',
        'WRIX',
    ];

    public function validate($value, Constraint $constraint): void
    {
        if (!$constraint instanceof ValidWbaChain) {
            return;
        }

        if (!$value instanceof UploadedFile) {
            return; // Let Symfony’s built-in File constraint handle this
        }

        // Read PEM content
        $content = @file_get_contents($value->getPathname());
        if (!$content) {
            $this->context->buildViolation('Cannot read certificate file.')->addViolation();
            return;
        }

        // Try to load the certificate
        $cert = @openssl_x509_read($content);
        if (!$cert) {
            $this->context->buildViolation('Invalid PEM certificate.')->addViolation();
            return;
        }

        // Compute SHA-256 fingerprint
        $fingerprint = @openssl_x509_fingerprint($cert, 'sha256', false);

        if (!$fingerprint) {
            $this->context->buildViolation('Unable to compute certificate fingerprint.')->addViolation();
            return;
        }

        // Normalize fingerprint
        $fingerprint = strtoupper(str_replace([':', ' '], '', $fingerprint));

        // Direct fingerprint match
        if (in_array($fingerprint, self::WBA_ROOTS_SHA256, true)) {
            return; // Valid — fingerprint matches a trusted WBA root
        }

        // Parse issuer/subject details for fallback check
        $certInfo = @openssl_x509_parse($cert);
        if (!$certInfo) {
            $this->context->buildViolation('Cannot parse certificate details.')->addViolation();
            return;
        }

        $issuer = strtoupper(json_encode($certInfo['issuer'] ?? []));
        $subject = strtoupper(json_encode($certInfo['subject'] ?? []));

        $isValid = array_any(
            self::POSSIBLE_INDICATORS,
            fn($indicator) => str_contains(
                $issuer,
                (string)$indicator
            ) ||
                str_contains(
                    $subject,
                    (string)$indicator
                )
        );

        if (!$isValid) {
            $this->context->buildViolation(
                $constraint->message ?? 'This certificate does not belong to the trusted WBA PKI chain.'
            )->addViolation();
        }
    }
}
