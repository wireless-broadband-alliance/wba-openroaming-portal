<?php

namespace App\Twig\Components;

use App\DTO\UserUpdateDTO;
use App\Entity\User;
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
    public ?UserUpdateDTO $userUpdateDTO = null;

    #[LiveProp]
    public ?User $editedUser = null;

    #[LiveProp]
    public ?User $current_user = null;

    #[LiveProp]
    public ?string $rawPhoneNumber = null;

    #[LiveProp]
    public bool $isEditingSelf = false;

    /**
     * @return FormInterface<mixed>
     */
    protected function instantiateForm(): FormInterface
    {
        $canWrite = $this->isGranted(UserAuthenticationVoter::USERS_MANAGEMENT_WRITE) || $this->isGranted(UserAuthenticationVoter::ADMIN_MANAGEMENT_WRITE);

        return $this->createForm(
            UserUpdateType::class,
            $this->userUpdateDTO,
            [
                'disabled' => !$canWrite,
                'edited_user' => $this->editedUser,
            ]
        );
    }

    #[LiveAction]
    public function validate(): void
    {
        // Handle phone parsing
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

        $currentUser = $this->getUser();
        $isAdmin = $this->isGranted('ROLE_ADMIN');
        $isEditingSelf =
            $currentUser instanceof User
            && $this->editedUser instanceof User
            && $currentUser->getId() === $this->editedUser->getId();

        // Base form data
        $data = [
            'uuid' => $this->userUpdateDTO->uuid,
            'email' => $this->userUpdateDTO->email,
            'firstName' => $this->userUpdateDTO->firstName,
            'lastName' => $this->userUpdateDTO->lastName,
            'phoneNumber' => $this->userUpdateDTO->phoneNumber,
            'isVerified' => $this->userUpdateDTO->isVerified,
            'banned' => $this->userUpdateDTO->banned,
        ];

        // Submit permissions ONLY when allowed
        if ($isAdmin && !$isEditingSelf) {
            $data += [
                'userManagement' => $this->userUpdateDTO->userManagement,
                'platformStatus' => $this->userUpdateDTO->platformStatus,
                'landingPageConfig' => $this->userUpdateDTO->landingPageConfig,
                'userEngagement' => $this->userUpdateDTO->userEngagement,
                'termsPolicies' => $this->userUpdateDTO->termsPolicies,
                'cronSchedule' => $this->userUpdateDTO->cronSchedule,
                'authenticationMethods' => $this->userUpdateDTO->authenticationMethods,
                'twoFactorAuth' => $this->userUpdateDTO->twoFactorAuth,
                'ldapSynchronization' => $this->userUpdateDTO->ldapSynchronization,
                'radiusProfileConfig' => $this->userUpdateDTO->radiusProfileConfig,
                'smsConfig' => $this->userUpdateDTO->smsConfig,
                'portalStatistics' => $this->userUpdateDTO->portalStatistics,
                'connectivityStatistics' => $this->userUpdateDTO->connectivityStatistics,
            ];
        }

        // Rebuild & submit form
        $form = $this->createForm(
            UserUpdateType::class,
            $this->userUpdateDTO,
            [
                'edited_user' => $this->editedUser,
            ]
        );

        $form->submit($data, false);

        $this->form = $form;
    }
}
