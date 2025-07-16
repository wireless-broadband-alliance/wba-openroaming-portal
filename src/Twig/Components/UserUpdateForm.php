<?php

namespace App\Twig\Components;

use App\DTO\UserUpdateDTO;
use App\Form\UserUpdateType;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\Attribute\LiveAction;
use Symfony\UX\LiveComponent\Attribute\LiveProp;
use Symfony\UX\LiveComponent\ComponentWithFormTrait;
use Symfony\UX\LiveComponent\DefaultActionTrait;

#[AsLiveComponent]
class UserUpdateForm extends AbstractController
{
    use ComponentWithFormTrait;
    use DefaultActionTrait;

    #[LiveProp(writable: true)]
    public ?UserUpdateDTO $userDTO = null;

    // Holds the Symfony FormInterface instance with errors & data
    protected FormInterface $form;

    public function __construct(
        private readonly ValidatorInterface $validator
    ) {
    }

    /**
     * Initialize or instantiate the form bound to the DTO.
     */
    protected function instantiateForm(): FormInterface
    {
        if (!$this->userDTO) {
            $this->userDTO = new UserUpdateDTO();
        }

        return $this->createForm(UserUpdateType::class, $this->userDTO);
    }

    public function mount(?UserUpdateDTO $userDTO = null): void
    {
        $this->userDTO = $userDTO ?? new UserUpdateDTO();
        $this->form = $this->instantiateForm();
    }

    #[LiveAction]
    public function validate(): void
    {
        // Rebuild the form with current DTO data
        $form = $this->createForm(UserUpdateType::class, $this->userDTO);

        // Submit the form data (simulate form submission) to trigger validation
        $form->submit([
            'uuid' => $this->userDTO->uuid,
            'email' => $this->userDTO->email,
            'firstName' => $this->userDTO->firstName,
            'lastName' => $this->userDTO->lastName,
            'phoneNumber' => $this->userDTO->phoneNumber,
            'isVerified' => $this->userDTO->isVerified,
            'banned' => $this->userDTO->banned,
        ], false);

        // Update form property with new form containing validation results
        $this->form = $form;
    }
}
