<?php

namespace App\Twig\Components;

use App\DTO\SecurityTxtDTO;
use App\Form\SecurityTxtType;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormInterface;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\Attribute\LiveAction;
use Symfony\UX\LiveComponent\Attribute\LiveProp;
use Symfony\UX\LiveComponent\ComponentWithFormTrait;
use Symfony\UX\LiveComponent\DefaultActionTrait;
use Symfony\UX\LiveComponent\LiveCollectionTrait;

#[AsLiveComponent]
class SecurityTxtForm extends AbstractController
{
    use ComponentWithFormTrait;
    use DefaultActionTrait;
    use LiveCollectionTrait;

    #[LiveProp]
    public ?SecurityTxtDTO $securityTxtDTO = null;

    /**
     * @return FormInterface<mixed>
     */
    #[\Override]
    protected function instantiateForm(): FormInterface
    {
        return $this->createForm(SecurityTxtType::class, $this->securityTxtDTO);
    }

    #[LiveAction]
    public function validate(): void
    {
        if (!$this->securityTxtDTO instanceof SecurityTxtDTO) {
            $this->securityTxtDTO = new SecurityTxtDTO();
        }

        $this->form = $this->createForm(SecurityTxtType::class, $this->securityTxtDTO);
    }
}
