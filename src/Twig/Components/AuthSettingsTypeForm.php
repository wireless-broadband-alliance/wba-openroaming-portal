<?php

namespace App\Twig\Components;

use App\DTO\AuthSettingsTypeDTO;
use App\Enum\SettingName;
use App\Form\AuthSettingsType;
use App\Security\Voter\UserAuthenticationVoter;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormInterface;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\Attribute\LiveAction;
use Symfony\UX\LiveComponent\Attribute\LiveProp;
use Symfony\UX\LiveComponent\ComponentWithFormTrait;
use Symfony\UX\LiveComponent\DefaultActionTrait;
use Symfony\UX\LiveComponent\LiveCollectionTrait;

#[AsLiveComponent]
final class AuthSettingsTypeForm extends AbstractController
{
    use ComponentWithFormTrait;
    use DefaultActionTrait;
    use LiveCollectionTrait;

    #[LiveProp]
    public AuthSettingsTypeDTO|null $authSettingsTypeDTO = null;

    /** @var array<string, array{value: ?string, description?: ?string}>|null */
    #[LiveProp]
    public ?array $data = null;

    #[LiveProp]
    public ?int $profileLimitDate = null;

    /**
     * @return FormInterface<mixed>
     */
    #[\Override]
    protected function instantiateForm(): FormInterface
    {
        $canWrite = $this->isGranted(UserAuthenticationVoter::AUTHENTICATION_METHODS_WRITE);

        return $this->createForm(AuthSettingsType::class, $this->authSettingsTypeDTO, ['disabled' => !$canWrite]);
    }

    #[LiveAction]
    public function validate(): void
    {
        $form = $this->createForm(AuthSettingsType::class, $this->authSettingsTypeDTO);

        // Submit the current DTO values
        $form->submit([
            'authMethodSamlEnabled' => $this->authSettingsTypeDTO->authMethodSamlEnabled,
            'authMethodSamlLabel' => $this->authSettingsTypeDTO->authMethodSamlLabel,
            'authMethodSamlDescription' => $this->authSettingsTypeDTO->authMethodSamlDescription,
            'profileLimitDateSaml' => $this->authSettingsTypeDTO->profileLimitDateSaml,

            'authMethodGOOGLELoginEnabled' => $this->authSettingsTypeDTO->authMethodGOOGLELoginEnabled,
            'authMethodGOOGLELoginLabel' => $this->authSettingsTypeDTO->authMethodGOOGLELoginLabel,
            'authMethodGOOGLELoginDescription' => $this->authSettingsTypeDTO->authMethodGOOGLELoginDescription,
            'validDomainsGOOGLELogin' => $this->authSettingsTypeDTO->validDomainsGOOGLELogin,
            'profileLimitDateGOOGLE' => $this->authSettingsTypeDTO->profileLimitDateGOOGLE,

            'authMethodMICROSOFTLoginEnabled' => $this->authSettingsTypeDTO->authMethodMICROSOFTLoginEnabled,
            'authMethodMICROSOFTLoginLabel' => $this->authSettingsTypeDTO->authMethodMICROSOFTLoginLabel,
            'authMethodMICROSOFTLoginDescription' => $this->authSettingsTypeDTO->authMethodMICROSOFTLoginDescription,
            'validDomainsMICROSOFTLogin' => $this->authSettingsTypeDTO->validDomainsMICROSOFTLogin,
            'profileLimitDateMICROSOFT' => $this->authSettingsTypeDTO->profileLimitDateMICROSOFT,

            'authMethodRegisterEnabled' => $this->authSettingsTypeDTO->authMethodRegisterEnabled,
            'authMethodRegisterLabel' => $this->authSettingsTypeDTO->authMethodRegisterLabel,
            'authMethodRegisterDescription' => $this->authSettingsTypeDTO->authMethodRegisterDescription,
            'profileLimitDateEmail' => $this->authSettingsTypeDTO->profileLimitDateEmail,
            'emailTimerResend' => $this->authSettingsTypeDTO->emailTimerResend,
            'linkValidity' => $this->authSettingsTypeDTO->linkValidity,

            'authMethodLoginTraditionalEnabled' => $this->authSettingsTypeDTO->authMethodLoginTraditionalEnabled,
            'authMethodLoginTraditionalLabel' => $this->authSettingsTypeDTO->authMethodLoginTraditionalLabel,
            'authMethodLoginTraditionalDescription' =>
                $this->authSettingsTypeDTO->authMethodLoginTraditionalDescription,

            'loginWithUUIDOnly' => $this->authSettingsTypeDTO->loginWithUUIDOnly,

            'authMethodSMSRegisterEnabled' => $this->authSettingsTypeDTO->authMethodSMSRegisterEnabled,
            'authMethodSMSRegisterLabel' => $this->authSettingsTypeDTO->authMethodSMSRegisterLabel,
            'authMethodSMSRegisterDescription' => $this->authSettingsTypeDTO->authMethodSMSRegisterDescription,
            'profileLimitDateSMS' => $this->authSettingsTypeDTO->profileLimitDateSMS,
        ], false);

        $this->form = $form;
    }
}
