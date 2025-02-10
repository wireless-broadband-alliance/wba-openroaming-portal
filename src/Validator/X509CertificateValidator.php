<?php

namespace App\Validator;

use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

class X509CertificateValidator extends ConstraintValidator
{
    public function validate($value, Constraint $constraint)
    {
        if (!$constraint instanceof X509Certificate) {
            throw new UnexpectedTypeException($constraint, X509Certificate::class);
        }

        if (null === $value || '' === $value) {
            // Let NotBlank handle empty values.
            return;
        }

        if (!is_string($value)) {
            $this->context->buildViolation('The certificate must be a valid string.')
                ->addViolation();
            return;
        }

        // Add BEGIN and END tags if missing.
        if (!str_contains($value, '-----BEGIN CERTIFICATE-----')) {
            $value = "-----BEGIN CERTIFICATE-----\n" . chunk_split(
                trim($value),
                64,
                "\n"
            ) . "-----END CERTIFICATE-----";
        }

        // Validate the Base64-encoded content inside the tags.
        if (!preg_match('/-----BEGIN CERTIFICATE-----(.*)-----END CERTIFICATE-----/s', $value, $matches)) {
            $this->context->buildViolation($constraint->message)
                ->setParameter(
                    '{{ error }}',
                    'The certificate does not contain valid BEGIN/END markers or valid content.'
                )
                ->addViolation();
            return;
        }

        $base64Content = trim($matches[1]);
        if (!base64_decode($base64Content, true)) {
            $this->context->buildViolation($constraint->message)
                ->setParameter('{{ error }}', 'The certificate content is not valid Base64-encoded data.')
                ->addViolation();
            return;
        }

        // Verify that the certificate is a valid X.509 certificate.
        $parsedCert = @openssl_x509_read($value);
        if ($parsedCert === false) {
            $this->context->buildViolation($constraint->message)
                ->setParameter('{{ error }}', 'The certificate is not a valid X.509 certificate.')
                ->addViolation();
            return;
        }

        // Extract certificate details for further validation.
        $certInfo = openssl_x509_parse($value);
        openssl_x509_free($parsedCert); // Free the resource to prevent memory leaks.

        if (!$certInfo) {
            $this->context->buildViolation($constraint->message)
                ->setParameter('{{ error }}', 'Failed to parse the certificate.')
                ->addViolation();
            return;
        }

        // Validate certificate's validity dates.
        $currentTime = time();
        if (isset($certInfo['validFrom_time_t']) && $currentTime < $certInfo['validFrom_time_t']) {
            $this->context->buildViolation($constraint->message)
                ->setParameter(
                    '{{ error }}',
                    'The certificate is not valid yet. It is valid starting from ' . date(
                        'Y-m-d H:i:s',
                        $certInfo['validFrom_time_t']
                    ) . '.'
                )
                ->addViolation();
            return;
        }
        if (isset($certInfo['validTo_time_t']) && $currentTime > $certInfo['validTo_time_t']) {
            $this->context->buildViolation($constraint->message)
                ->setParameter(
                    '{{ error }}',
                    'The certificate has expired. It expired on ' . date(
                        'Y-m-d H:i:s',
                        $certInfo['validTo_time_t']
                    ) . '.'
                )
                ->addViolation();
            return;
        }

        // Check if the certificate contains a valid Common Name (CN).
        if (empty($certInfo['subject']['CN'])) {
            $this->context->buildViolation($constraint->message)
                ->setParameter(
                    '{{ error }}',
                    'The certificate does not contain a valid Common Name (CN) in its subject.'
                )
                ->addViolation();
        }
    }
}
