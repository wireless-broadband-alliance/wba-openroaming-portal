<?php

namespace App\Twig\Components;

use App\DTO\CertificateFreeradiusDomainDTO;
use App\Form\CertificateFreeradiusDomainType;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormInterface;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\Attribute\LiveProp;
use Symfony\UX\LiveComponent\ComponentWithFormTrait;
use Symfony\UX\LiveComponent\DefaultActionTrait;

#[AsLiveComponent]
final class CertificateFreeradiusDomainForm extends AbstractController
{
    use ComponentWithFormTrait;
    use DefaultActionTrait;

    #[LiveProp]
    public CertificateFreeradiusDomainDTO|null $certificateFreeradiusDomainDTO = null;

    /**
     * @return FormInterface<mixed>
     */
    protected function instantiateForm(): FormInterface
    {
        if (!$this->certificateFreeradiusDomainDTO instanceof CertificateFreeradiusDomainDTO) {
            $this->certificateFreeradiusDomainDTO = new CertificateFreeradiusDomainDTO();
        }

        return $this->createForm(CertificateFreeradiusDomainType::class, $this->certificateFreeradiusDomainDTO);
    }
}
