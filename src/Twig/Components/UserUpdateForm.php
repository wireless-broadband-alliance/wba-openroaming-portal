<?php

namespace App\Twig\Components;

use App\DTO\UserUpdateDTO;
use App\Form\UserUpdateType;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormInterface;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\Attribute\LiveAction;
use Symfony\UX\LiveComponent\Attribute\LiveProp;
use Symfony\UX\LiveComponent\ComponentWithFormTrait;
use Symfony\UX\LiveComponent\DefaultActionTrait;
use Symfony\UX\LiveComponent\LiveCollectionTrait;

#[AsLiveComponent]
final class UserUpdateForm extends AbstractController
{
    use ComponentWithFormTrait;
    use DefaultActionTrait;
    use LiveCollectionTrait;

    #[LiveProp]
    public UserUpdateDTO|null $userUpdateDTO = null;

    /**
     * @return FormInterface<mixed>
     */
    #[\Override]
    protected function instantiateForm(): FormInterface
    {
        return $this->createForm(UserUpdateType::class, $this->userUpdateDTO);
    }

    #[LiveAction]
    public function validate(): void
    {
        // Rebuild the form with current DTO data
        $form = $this->createForm(UserUpdateType::class, $this->userUpdateDTO);

        // Submit the form data (simulate form submission) to trigger validation
        $form->submit([
            'uuid' => $this->userUpdateDTO->uuid,
            'email' => $this->userUpdateDTO->email,
            'firstName' => $this->userUpdateDTO->firstName,
            'lastName' => $this->userUpdateDTO->lastName,
            'phoneNumber' => $this->userUpdateDTO->phoneNumber,
            'isVerified' => $this->userUpdateDTO->isVerified,
            'banned' => $this->userUpdateDTO->banned,
        ], false);

        // Update form property with new form containing validation results
        $this->form = $form;
    }
}
