<?php

namespace App\DTO;

use App\Enum\CertificateFileName;
use DateTimeImmutable;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

class CertificateUploadDTO
{
    #[Assert\File(
        maxSize: '5M',
        mimeTypes: [
            'application/x-x509-ca-cert',
            'application/x-pem-file',
            'application/octet-stream',
            'text/plain',
        ],
        notFoundMessage: 'Please upload a client certificate file.',
        mimeTypesMessage: 'Invalid client certificate file type. Please upload a valid .pem certificate.'
    )]
    public ?UploadedFile $client = null;

    #[Assert\File(
        maxSize: '5M',
        mimeTypes: [
            'application/x-pem-file',
            'application/octet-stream',
            'text/plain',
        ],
        notFoundMessage: 'Please upload a private key file.',
        mimeTypesMessage: 'Invalid private key file type. Please upload a valid .pem key.'
    )]
    public ?UploadedFile $key = null;

    #[Assert\Callback]
    public function validatePemFiles(ExecutionContextInterface $context): void
    {
        // Validate client PEM
        if ($this->client instanceof UploadedFile) {
            $contents = @file_get_contents($this->client->getPathname());

            if (!$this->isValidPemCertificate($contents)) {
                $context->buildViolation('Client certificate must be a valid PEM-encoded X.509 certificate.')
                    ->atPath(CertificateFileName::CLIENT_PEM->value)
                    ->addViolation();
            } else {
                // Check if the certificate is expired
                $certResource = @openssl_x509_read($contents);
                $certInfo = openssl_x509_parse($certResource);

                if ($certInfo && isset($certInfo['validTo_time_t'])) {
                    $validTo = new DateTimeImmutable()->setTimestamp((int)$certInfo['validTo_time_t']);
                    if ($validTo < new DateTimeImmutable()) {
                        $context->buildViolation('Client certificate has expired.')
                            ->atPath(CertificateFileName::CLIENT_PEM->value)
                            ->addViolation();
                    }
                }
            }
        }

        // Validate private key PEM
        if ($this->key instanceof UploadedFile) {
            $contents = @file_get_contents($this->key->getPathname());
            if (!$this->isValidPemPrivateKey($contents)) {
                $context->buildViolation('Private key must be a valid PEM-encoded private key.')
                    ->atPath(CertificateFileName::KEY_PEM->value)
                    ->addViolation();
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
        if ($cert === false) {
            return false;
        }

        return true;
    }

    private function isValidPemPrivateKey(?string $contents): bool
    {
        if (!$contents) {
            return false;
        }

        // Parse the private key
        $key = @openssl_pkey_get_private($contents);
        if ($key === false) {
            return false;
        }

        return true;
    }
}
