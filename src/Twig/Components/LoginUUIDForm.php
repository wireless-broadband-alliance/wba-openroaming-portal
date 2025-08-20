<?php

namespace App\Twig\Components;

use App\DTO\MagicLinkDTO;
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
    public MagicLinkDTO|null $magicLinkDTO = null;

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
        if (!$this->magicLinkDTO) {
            $this->magicLinkDTO = new MagicLinkDTO();
        }

        $form = $this->createForm(LoginUUIDType::class, $this->magicLinkDTO);

        $this->email = $this->magicLinkDTO->email;
        $this->phoneNumber = $this->magicLinkDTO->phoneNumber;

        return $form;
    }

    #[LiveAction]
    public function validate(): void
    {
        $form = $this->createForm(LoginUUIDType::class, $this->magicLinkDTO);

        $form->submit([
            'email' => $this->email,
            'phoneNumber' => $this->phoneNumber,
        ], false);

        $this->form = $form;

    }

}