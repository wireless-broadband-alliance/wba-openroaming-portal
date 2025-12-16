<?php

namespace App\Twig\Components;

use App\DTO\UserUpdateDTO;
use App\Form\UserUpdateType;
use App\Security\Voter\UserAuthenticationVoter;
use libphonenumber\NumberParseException;
use libphonenumber\PhoneNumberUtil;
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
     * Store the raw phone number string separately
     */
    #[LiveProp]
    public string|null $rawPhoneNumber = null;

    /**
     * @return FormInterface<mixed>
     */
    #[\Override]
    protected function instantiateForm(): FormInterface
    {
        $canWrite = $this->isGranted(UserAuthenticationVoter::USERS_MANAGEMENT_WRITE);

        return $this->createForm(UserUpdateType::class, $this->userUpdateDTO, ['disabled' => !$canWrite]);
    }

    #[LiveAction]
    public function validate(): void
    {
        // Parse the raw phone number string into a PhoneNumber object
        if (!in_array($this->rawPhoneNumber, [null, '', '0'], true)) {
            try {
                $phoneUtil = PhoneNumberUtil::getInstance();
                $this->userUpdateDTO->phoneNumber = $phoneUtil->parse(
                    $this->rawPhoneNumber,
                    'US'
                );
            } catch (NumberParseException) {
                $this->userUpdateDTO->phoneNumber = null;
            }
        }
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
