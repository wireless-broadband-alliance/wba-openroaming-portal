<?php

namespace App\DTO;

use App\Enum\CertificateFileName;
use DateTimeImmutable;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

class CertificateRadSecUploadDTO
{
    #[Assert\File(
        maxSize: '5M',
        mimeTypes: [
            'application/x-x509-ca-cert',
            'application/x-pem-file',
            'application/octet-stream',
            'text/plain',
        ],
        notFoundMessage: 'nullCertificate',
        mimeTypesMessage: 'invalidFileTypeCert'
    )]
    public ?UploadedFile $client = null;

    #[Assert\File(
        maxSize: '5M',
        mimeTypes: [
            'application/x-pem-file',
            'application/octet-stream',
            'text/plain',
        ],
        notFoundMessage: 'nullKey',
        mimeTypesMessage: 'invalidFileTypeKey'
    )]
    public ?UploadedFile $key = null;

    #[Assert\Callback]
    public function validatePemFiles(ExecutionContextInterface $context): void
    {
        $certResource = null;
        $privateKeyResource = null;

        // Validate client PEM
        if ($this->client instanceof UploadedFile) {
            $certContents = @file_get_contents($this->client->getPathname());

            if (!$this->isValidPemCertificate($certContents)) {
                $context->buildViolation('mustBeValidCertPEMX509')
                    ->atPath(CertificateFileName::CLIENT_PEM->value)
                    ->addViolation();
            } else {
                $certResource = @openssl_x509_read($certContents);

                // Check expiration
                $certInfo = openssl_x509_parse($certResource);
                if ($certInfo && isset($certInfo['validTo_time_t'])) {
                    $validTo = new DateTimeImmutable()->setTimestamp((int)$certInfo['validTo_time_t']);
                    if ($validTo < new DateTimeImmutable()) {
                        $context->buildViolation('certPEMX509Expired')
                            ->atPath(CertificateFileName::CLIENT_PEM->value)
                            ->addViolation();
                    }
                }
            }
        }

        // Validate private key PEM
        if ($this->key instanceof UploadedFile) {
            $keyContents = @file_get_contents($this->key->getPathname());
            if (!$this->isValidPemPrivateKey($keyContents)) {
                $context->buildViolation('mustBeValidKeyPEMX509')
                    ->atPath(CertificateFileName::KEY_PEM->value)
                    ->addViolation();
            } else {
                $privateKeyResource = @openssl_pkey_get_private($keyContents);
            }
        }

        // Validate that key matches certificate
        if ($certResource && $privateKeyResource) {
            $publicKey = openssl_pkey_get_public($certResource);
            if ($publicKey) {
                $certDetails = openssl_pkey_get_details($publicKey);
                $keyDetails = openssl_pkey_get_details($privateKeyResource);
                if (!$certDetails || !$keyDetails || $certDetails['key'] !== $keyDetails['key']) {
                    $context->buildViolation('privateKeyDoesntMatchCertificate')
                        ->atPath(CertificateFileName::KEY_PEM->value)
                        ->addViolation();
                }
            }
        }
    }

    private function isValidPemCertificate(?string $contents): bool
    {
        if (!$contents) {
            return false;
        }

        // Parse the certificate
        $cert = @openssl_x509_read($contents);
        return $cert !== false;
    }

    private function isValidPemPrivateKey(?string $contents): bool
    {
        if (!$contents) {
            return false;
        }

        // Parse the private key
        $key = @openssl_pkey_get_private($contents);
        return $key !== false;
    }
}
