<?php

namespace App\Twig\Components;

use App\DTO\CertificateUploadDTO;
use App\Form\CertificateUploadType;
use App\Service\CertificateService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\Attribute\LiveAction;
use Symfony\UX\LiveComponent\Attribute\LiveProp;
use Symfony\UX\LiveComponent\ComponentWithFormTrait;
use Symfony\UX\LiveComponent\DefaultActionTrait;
use Symfony\UX\LiveComponent\LiveCollectionTrait;

#[AsLiveComponent]
final class CertificateUploadForm extends AbstractController
{
    use ComponentWithFormTrait;
    use DefaultActionTrait;
    use LiveCollectionTrait;

    public function __construct(
        private readonly CertificateService $certificateService
    ) {
    }

    #[LiveProp]
    public ?CertificateUploadDTO $certificateUploadDTO = null;

    #[LiveProp(writable: true)]
    public ?UploadedFile $client = null;

    #[LiveProp(writable: true)]
    public ?UploadedFile $key = null;

    protected function instantiateForm(): FormInterface
    {
        $this->certificateUploadDTO ??= new CertificateUploadDTO();
        return $this->createForm(CertificateUploadType::class, $this->certificateUploadDTO);
    }

    /**
     * Validate uploaded files sent via $request->files
     */
    #[LiveAction]
    public function validate(Request $request): void
    {
        $form = $this->getForm();

        // Retrieve uploaded files from Request
        $clientFile = $request->files->get('client');
        $keyFile = $request->files->get('key');

        $this->certificateUploadDTO ??= new CertificateUploadDTO();
        $this->certificateUploadDTO->client = $clientFile instanceof UploadedFile ? $clientFile : null;
        $this->certificateUploadDTO->key = $keyFile instanceof UploadedFile ? $keyFile : null;

        // Validate existence
        if (!$this->certificateUploadDTO->client) {
            $form->addError(new FormError('Please upload a client certificate file.'));
        }
        if (!$this->certificateUploadDTO->key) {
            $form->addError(new FormError('Please upload a private key file.'));
        }

        // Continue only if files exist
        if ($this->certificateUploadDTO->client && $this->certificateUploadDTO->key) {
            // Validate extensions
            if (strtolower($this->certificateUploadDTO->client->getClientOriginalExtension()) !== 'pem') {
                $form->addError(new FormError('Client certificate must be a .pem file.'));
            }
            if (strtolower($this->certificateUploadDTO->key->getClientOriginalExtension()) !== 'pem') {
                $form->addError(new FormError('Private key must be a .pem file.'));
            }

            // Validate certificate content
            $certContent = file_get_contents($this->certificateUploadDTO->client->getPathname());
            if (!$this->certificateService->isCertificateValidFromString($certContent)) {
                $form->addError(new FormError('Client certificate is expired or invalid.'));
            }

            // Validate key content
            $keyContent = file_get_contents($this->certificateUploadDTO->key->getPathname());
            $privateKey = openssl_pkey_get_private($keyContent);
            if ($privateKey === false) {
                $form->addError(new FormError('Private key is invalid.'));
            } else {
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
