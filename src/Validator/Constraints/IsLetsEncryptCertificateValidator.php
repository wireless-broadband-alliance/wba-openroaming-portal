<?php

namespace App\Validator\Constraints;

use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use App\DTO\CertificateFreeradiusUploadDTO;

class IsLetsEncryptCertificateValidator extends ConstraintValidator
{
    private array $knownIssuers = [
        "Let's Encrypt",
        'R3',
        'E1',
        'E5',
        'ISRG Root X1',
        'ISRG Root X2',
    ];

    public function validate($value, Constraint $constraint): void
    {
        if (!$value instanceof UploadedFile) {
            return;
        }

        $content = @file_get_contents($value->getRealPath());
        if (!$content) {
            return;
        }

        $cert = @openssl_x509_read($content);
        if (!$cert) {
            return;
        }

        $parsed = openssl_x509_parse($cert);
        if (!$parsed || !isset($parsed['issuer'])) {
            return;
        }

        $issuerDict = $parsed['issuer'];

        // Convert issuer dict to string: "CN=R3, O=Let's Encrypt, C=US"
        $issuerString = implode(', ', array_map(
            static fn($k, $v) => "$k=$v",
            array_keys($issuerDict),
            $issuerDict
        ));

        // Match known Let's Encrypt issuers
        $isLetsEncrypt = false;
        foreach ($this->knownIssuers as $issuerName) {
            if (stripos($issuerString, $issuerName) !== false) {
                $isLetsEncrypt = true;
                break;
            }
        }

        // If certificate **is** from Let's Encrypt → add notice warning
        if ($isLetsEncrypt && $this->context->getObject() instanceof CertificateFreeradiusUploadDTO) {
            /** @var CertificateFreeradiusUploadDTO $dto */
            $dto = $this->context->getObject();
            $dto->notices[] = 'CERTIFICATE_LETS_ENCRYPT_WARNING';
        }
    }
}
