<?php

namespace App\Twig\Components;

use App\DTO\LoginChoiceDTO;
use App\Form\LoginUUIDType;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormInterface;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\Attribute\LiveAction;
use Symfony\UX\LiveComponent\Attribute\LiveProp;
use Symfony\UX\LiveComponent\ComponentWithFormTrait;
use Symfony\UX\LiveComponent\DefaultActionTrait;
use Symfony\UX\LiveComponent\LiveCollectionTrait;

#[AsLiveComponent]
final class LoginUUIDForm extends AbstractController
{
    use ComponentWithFormTrait;
    use DefaultActionTrait;
    use LiveCollectionTrait;

    #[LiveProp]
    public LoginChoiceDTO|null $loginChoiceDTO = null;

    #[LiveProp]
    public ?string $email = null;

    #[LiveProp]
    public ?string $phoneNumber = null;

    public function __construct()
    {

    }
    #[\Override]
    protected function instantiateForm(): FormInterface
    {
        if (!$this->loginChoiceDTO) {
            $this->loginChoiceDTO = new LoginChoiceDTO();
        }

        $form = $this->createForm(LoginUUIDType::class, $this->loginChoiceDTO);

        $this->email = $this->loginChoiceDTO->email;
        $this->phoneNumber = $this->loginChoiceDTO->phoneNumber;

        return $form;
    }

    #[LiveAction]
    public function validate(): void
    {
        $form = $this->createForm(LoginUUIDType::class, $this->loginChoiceDTO);

        $form->submit([
            'email' => $this->email,
            'phoneNumber' => $this->phoneNumber,
        ], false);

        $this->form = $form;

    }

}