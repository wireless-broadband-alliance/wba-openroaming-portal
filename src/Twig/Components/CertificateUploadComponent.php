<?php

namespace App\Twig\Components;

use App\DTO\CertificateUploadDto;
use App\Form\CertificateUploadType;
use App\Service\CertificateService;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\DefaultActionTrait;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[AsLiveComponent('certificate_upload')]
class CertificateUploadComponent
{
    use DefaultActionTrait;

    public CertificateUploadDto $dto;

    public ?FormInterface $form = null;

    public function __construct(
        private readonly FormFactoryInterface $formFactory,
        private readonly ValidatorInterface $validator,
        private readonly CertificateService $certificateService
    ) {
        $this->dto = new CertificateUploadDto();
    }

    public function mount(): void
    {
        $this->form = $this->formFactory->create(CertificateUploadType::class, $this->dto);
    }

    public function updated($name, $value): void
    {
        $errors = $this->validator->validate($this->dto);

        // Validate client certificate
        if ($this->dto->client instanceof UploadedFile) {
            $clientContent = file_get_contents($this->dto->client->getPathname());

            if (!$this->certificateService->isCertificateValidFromString($clientContent)) {
                $errors->add(new ConstraintViolation(
                    'Client certificate is expired or invalid.',
                    '',
                    [],
                    $this->dto,
                    'client',
                    $value
                ));
            }
        }

        // Validate private key
        if ($this->dto->key instanceof UploadedFile) {
            $keyContent = file_get_contents($this->dto->key->getPathname());
            $privateKey = openssl_pkey_get_private($keyContent);

            if ($privateKey === false) {
                $errors->add(new ConstraintViolation(
                    'Private key is invalid.',
                    '',
                    [],
                    $this->dto,
                    'key',
                    $value
                ));
            }

            // Check if key matches client certificate
            if ($this->dto->client instanceof UploadedFile && $privateKey !== false) {
                $certContent = file_get_contents($this->dto->client->getPathname());
                $certResource = openssl_x509_read($certContent);
                $pubKey = openssl_pkey_get_details(openssl_pkey_get_public($certResource))['key'];

                $privateKeyPub = openssl_pkey_get_details($privateKey)['key'];

                if ($pubKey !== $privateKeyPub) {
                    $errors->add(new ConstraintViolation(
                        'Private key does not match the client certificate.',
                        '',
                        [],
                        $this->dto,
                        'key',
                        $value
                    ));
                }
            }
        }

        $this->form->submit([
            'name' => $this->dto->name,
            'client' => $this->dto->client,
            'key' => $this->dto->key,
        ]);
    }
}
