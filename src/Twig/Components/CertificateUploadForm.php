<?php

namespace App\Twig\Components;

use App\DTO\CertificateUploadDTO;
use App\Enum\CertificateFileName;
use App\Enum\CertificateMachineType;
use App\Form\CertificateUploadType;
use App\Service\CertificateService;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\Attribute\LiveAction;
use Symfony\UX\LiveComponent\Attribute\LiveProp;
use Symfony\UX\LiveComponent\ComponentWithFormTrait;
use Symfony\UX\LiveComponent\DefaultActionTrait;
use Symfony\UX\LiveComponent\LiveCollectionTrait;

#[AsLiveComponent]
final class CertificateUploadForm
{
    use ComponentWithFormTrait;
    use DefaultActionTrait;
    use LiveCollectionTrait;

    #[LiveProp]
    public ?CertificateUploadDTO $certificateUploadDto = null;

    public function __construct(
        private readonly CertificateService $certificateService,
        private readonly FormFactoryInterface $formFactory
    ) {
        $this->certificateUploadDto = new CertificateUploadDTO();
    }

    /**
     * Instantiate the form using FormFactory
     */
    protected function instantiateForm(): FormInterface
    {
        return $this->formFactory->create(CertificateUploadType::class, $this->certificateUploadDto);
    }

    #[LiveAction]
    public function validate(): void
    {
        $form = $this->formFactory->create(CertificateUploadType::class, $this->certificateUploadDto);

        if ($this->certificateUploadDto->client instanceof UploadedFile) {
            $this->certificateUploadDto->name = CertificateFileName::CLIENT_PEM;
            $this->certificateUploadDto->type = CertificateMachineType::RADSECPROXY;
        }

        if ($this->certificateUploadDto->key instanceof UploadedFile) {
            $this->certificateUploadDto->name = CertificateFileName::KEY_PEM;
            $this->certificateUploadDto->type = CertificateMachineType::RADSECPROXY;
        }

        $form->submit([
            'client' => $this->certificateUploadDto->client,
            'key' => $this->certificateUploadDto->key,
        ], false);

        // Validate client certificate
        if ($this->certificateUploadDto->client instanceof UploadedFile) {
            $content = file_get_contents($this->certificateUploadDto->client->getPathname());
            if (!$this->certificateService->isCertificateValidFromString($content)) {
                $form->addError(new FormError('Client certificate is expired or invalid.'));
            }
        }

        // Validate private key
        if ($this->certificateUploadDto->key instanceof UploadedFile) {
            $keyContent = file_get_contents($this->certificateUploadDto->key->getPathname());
            $privateKey = openssl_pkey_get_private($keyContent);

            if ($privateKey === false) {
                $form->addError(new FormError('Private key is invalid.'));
            }

            if ($this->certificateUploadDto->client instanceof UploadedFile && $privateKey !== false) {
                $certContent = file_get_contents($this->certificateUploadDto->client->getPathname());
                $certResource = openssl_x509_read($certContent);
                $pubKey = openssl_pkey_get_details(openssl_pkey_get_public($certResource))['key'];
                $privateKeyPub = openssl_pkey_get_details($privateKey)['key'];

                if ($pubKey !== $privateKeyPub) {
                    $form->addError(new FormError('Private key does not match the client certificate.'));
                }
            }
        }

        $this->form = $form;
    }
}
