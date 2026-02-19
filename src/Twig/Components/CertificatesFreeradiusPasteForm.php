<?php

namespace App\Twig\Components;

use App\DTO\CertificatesFreeradiusPasteDTO;
use App\Form\CertificatesFreeradiusPasteType;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormInterface;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\Attribute\LiveAction;
use Symfony\UX\LiveComponent\Attribute\LiveProp;
use Symfony\UX\LiveComponent\ComponentWithFormTrait;
use Symfony\UX\LiveComponent\DefaultActionTrait;
use Symfony\UX\LiveComponent\LiveCollectionTrait;

#[AsLiveComponent]
class CertificatesFreeradiusPasteForm extends AbstractController
{
    use ComponentWithFormTrait;
    use DefaultActionTrait;
    use LiveCollectionTrait;

    #[LiveProp]
    public CertificatesFreeradiusPasteDTO|null $certificatesFreeradiusPasteDTO = null;

    #[LiveProp]
    public bool $httpChallengeTestCompleted = false;

    #[LiveProp]
    public bool $httpChallengeTestFailed = false;

    /**
     * @return FormInterface<mixed>
     */
    #[\Override]
    protected function instantiateForm(): FormInterface
    {
        return $this->createForm(CertificatesFreeradiusPasteType::class, $this->certificatesFreeradiusPasteDTO);
    }

    #[LiveAction]
    public function validate(): void
    {
        if (!$this->certificatesFreeradiusPasteDTO instanceof CertificatesFreeradiusPasteDTO) {
            $this->certificatesFreeradiusPasteDTO = new CertificatesFreeradiusPasteDTO();
        }

        $this->form = $this->createForm(CertificatesFreeradiusPasteType::class, $this->certificatesFreeradiusPasteDTO);
    }
}
