<?php

namespace App\Twig\Components;

use App\DTO\UserAddDTO;
use App\Form\UserAddType;
use App\Service\UserCreationService;
use Random\RandomException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormInterface;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\Attribute\LiveAction;
use Symfony\UX\LiveComponent\Attribute\LiveProp;
use Symfony\UX\LiveComponent\ComponentWithFormTrait;
use Symfony\UX\LiveComponent\DefaultActionTrait;

#[AsLiveComponent]
final class UserAddForm extends AbstractController
{
    use ComponentWithFormTrait;
    use DefaultActionTrait;

    #[LiveProp]
    public ?UserAddDTO $userAddDTO = null;

    #[LiveProp]
    public bool $isEditingSelf = false;

    public function __construct(
        private readonly UserCreationService $userCreationService
    ) {
    }

    protected function instantiateForm(): FormInterface
    {
        return $this->createForm(
            UserAddType::class,
            $this->userAddDTO ?? new UserAddDTO()
        );
    }

    /**
     * @throws RandomException
     */
    #[LiveAction]
    public function save(): void
    {
        $formData = $this->form->getData();

        // Copy all fields except password/confirmPassword
        $this->userAddDTO->accountType = $formData->accountType;
        $this->userAddDTO->email = $formData->email;
        $this->userAddDTO->phoneNumber = $formData->phoneNumber;
        $this->userAddDTO->firstName = $formData->firstName;
        $this->userAddDTO->lastName = $formData->lastName;

        // Assign password manually from LiveProp
        $this->userAddDTO->password = $this->password;
        $this->userAddDTO->confirmPassword = $this->confirmPassword;

        $this->userCreationService->createAdminUser($this->userAddDTO);

        // Reset DTO and passwords
        $this->userAddDTO = new UserAddDTO();
    }
}
