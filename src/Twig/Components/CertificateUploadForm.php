<?php

namespace App\Twig\Components;

use App\DTO\CertificateUploadDTO;
use App\Form\CertificateUploadType;
use App\Service\CertificateService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;
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
    public function validate(): void
    {
        // Rebuild the form with current DTO data
        $form = $this->createForm(CertificateUploadType::class, $this->certificateUploadDTO);

        // Submit the form data (simulate form submission) to trigger validation
        $form->submit([
            'client' => $this->certificateUploadDTO->client,
            'key' => $this->certificateUploadDTO->key,
        ], false);

        // Update form property with new form containing validation results
        $this->form = $form;
    }
}
