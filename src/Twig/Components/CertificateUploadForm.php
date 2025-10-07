<?php

namespace App\Twig\Components;

use App\DTO\CertificateUploadDTO;
use App\Enum\CertificateFileName;
use App\Enum\CertificateMachineType;
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

        // Return the uploaded files from request
        $clientFile = $request->files->get('client');
        $keyFile = $request->files->get('key');

        $this->certificateUploadDTO ??= new CertificateUploadDTO();
        $this->certificateUploadDTO->client = $clientFile instanceof UploadedFile ? $clientFile : null;
        $this->certificateUploadDTO->key = $keyFile instanceof UploadedFile ? $keyFile : null;

        // Assign enums for each certificate scenario
        if ($this->certificateUploadDTO->client instanceof UploadedFile) {
            $this->certificateUploadDTO->name = CertificateFileName::CLIENT_PEM;
            $this->certificateUploadDTO->type = CertificateMachineType::RADSECPROXY;
        }
        if ($this->certificateUploadDTO->key instanceof UploadedFile) {
            $this->certificateUploadDTO->name = CertificateFileName::KEY_PEM;
            $this->certificateUploadDTO->type = CertificateMachineType::RADSECPROXY;
        }

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
            // TODO RECAP THIS TO CHECK IF THE FILE IS THE CORRECT TYPE
            if (strtolower($this->certificateUploadDTO->client->getClientOriginalExtension()) !== 'pem') {
                $form->addError(new FormError('Client certificate must be a .pem file.'));
            }
            if (strtolower($this->certificateUploadDTO->key->getClientOriginalExtension()) !== 'pem') {
                $form->addError(new FormError('Private key must be a .pem file.'));
            }

            // Validate certificate content
            // TODO RECAP THIS LOGIC TO CHECK IF THE CERTIFICATE IS STILL VALID
            $certContent = file_get_contents($this->certificateUploadDTO->client->getPathname());
            if (!$this->certificateService->isCertificateValidFromString($certContent)) {
                $form->addError(new FormError('Client certificate is expired or invalid.'));
            }

            // Validate key content
            // TODO RECAP THIS LOGIC ABOUT THE CERTIFICATE/PRIVATE KEY CHECK CONTENT IF THEY BELONG TO EACH OTHER
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
