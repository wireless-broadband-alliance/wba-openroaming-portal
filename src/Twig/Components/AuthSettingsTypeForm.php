<?php

namespace App\Twig\Components;

use App\DTO\AuthSettingsTypeDTO;
use App\Enum\SettingName;
use App\Form\AuthSettingsType;
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
        return $this->createForm(AuthSettingsType::class, $this->authSettingsTypeDTO);
    }

    #[LiveAction]
    public function validate(): void
    {
        $form = $this->createForm(AuthSettingsType::class, $this->authSettingsTypeDTO);

        // Submit the current DTO values
        $form->submit([
            SettingName::AUTH_METHOD_SAML_ENABLED->value => $this->authSettingsTypeDTO->AUTH_METHOD_SAML_ENABLED,
            SettingName::AUTH_METHOD_SAML_LABEL->value => $this->authSettingsTypeDTO->AUTH_METHOD_SAML_LABEL,
            SettingName::AUTH_METHOD_SAML_DESCRIPTION->value => $this->authSettingsTypeDTO->AUTH_METHOD_SAML_DESCRIPTION,
            SettingName::PROFILE_LIMIT_DATE_SAML->value => $this->authSettingsTypeDTO->PROFILE_LIMIT_DATE_SAML,

            SettingName::AUTH_METHOD_GOOGLE_LOGIN_ENABLED->value =>
                $this->authSettingsTypeDTO->AUTH_METHOD_GOOGLE_LOGIN_ENABLED,
            SettingName::AUTH_METHOD_GOOGLE_LOGIN_LABEL->value => $this->authSettingsTypeDTO->AUTH_METHOD_GOOGLE_LOGIN_LABEL,
            SettingName::AUTH_METHOD_GOOGLE_LOGIN_DESCRIPTION->value =>
                $this->authSettingsTypeDTO->AUTH_METHOD_GOOGLE_LOGIN_DESCRIPTION,
            SettingName::VALID_DOMAINS_GOOGLE_LOGIN->value => $this->authSettingsTypeDTO->VALID_DOMAINS_GOOGLE_LOGIN,
            SettingName::PROFILE_LIMIT_DATE_GOOGLE->value => $this->authSettingsTypeDTO->PROFILE_LIMIT_DATE_GOOGLE,

            SettingName::AUTH_METHOD_MICROSOFT_LOGIN_ENABLED->value =>
                $this->authSettingsTypeDTO->AUTH_METHOD_MICROSOFT_LOGIN_ENABLED,
            SettingName::AUTH_METHOD_MICROSOFT_LOGIN_LABEL->value =>
                $this->authSettingsTypeDTO->AUTH_METHOD_MICROSOFT_LOGIN_LABEL,
            SettingName::AUTH_METHOD_MICROSOFT_LOGIN_DESCRIPTION->value => $this->authSettingsTypeDTO->AUTH_METHOD_MICROSOFT_LOGIN_DESCRIPTION,
            SettingName::VALID_DOMAINS_MICROSOFT_LOGIN->value => $this->authSettingsTypeDTO->VALID_DOMAINS_MICROSOFT_LOGIN,
            SettingName::PROFILE_LIMIT_DATE_MICROSOFT->value => $this->authSettingsTypeDTO->PROFILE_LIMIT_DATE_MICROSOFT,

            SettingName::AUTH_METHOD_REGISTER_ENABLED->value => $this->authSettingsTypeDTO->AUTH_METHOD_REGISTER_ENABLED,
            SettingName::AUTH_METHOD_REGISTER_LABEL->value => $this->authSettingsTypeDTO->AUTH_METHOD_REGISTER_LABEL,
            SettingName::AUTH_METHOD_REGISTER_DESCRIPTION->value =>
                $this->authSettingsTypeDTO->AUTH_METHOD_REGISTER_DESCRIPTION,
            SettingName::PROFILE_LIMIT_DATE_EMAIL->value => $this->authSettingsTypeDTO->PROFILE_LIMIT_DATE_EMAIL,
            SettingName::EMAIL_TIMER_RESEND->value => $this->authSettingsTypeDTO->EMAIL_TIMER_RESEND,
            SettingName::LINK_VALIDITY->value => $this->authSettingsTypeDTO->LINK_VALIDITY,

            SettingName::AUTH_METHOD_LOGIN_TRADITIONAL_ENABLED->value =>
                $this->authSettingsTypeDTO->AUTH_METHOD_LOGIN_TRADITIONAL_ENABLED,
            SettingName::AUTH_METHOD_LOGIN_TRADITIONAL_LABEL->value =>
                $this->authSettingsTypeDTO->AUTH_METHOD_LOGIN_TRADITIONAL_LABEL,
            SettingName::AUTH_METHOD_LOGIN_TRADITIONAL_DESCRIPTION->value =>
                $this->authSettingsTypeDTO->AUTH_METHOD_LOGIN_TRADITIONAL_DESCRIPTION,

            SettingName::LOGIN_WITH_UUID_ONLY->value => $this->authSettingsTypeDTO->LOGIN_WITH_UUID_ONLY,

            SettingName::AUTH_METHOD_SMS_REGISTER_ENABLED->value =>
                $this->authSettingsTypeDTO->AUTH_METHOD_SMS_REGISTER_ENABLED,
            SettingName::AUTH_METHOD_SMS_REGISTER_LABEL->value => $this->authSettingsTypeDTO->AUTH_METHOD_SMS_REGISTER_LABEL,
            SettingName::AUTH_METHOD_SMS_REGISTER_DESCRIPTION->value =>
                $this->authSettingsTypeDTO->AUTH_METHOD_SMS_REGISTER_DESCRIPTION,
            SettingName::PROFILE_LIMIT_DATE_SMS->value => $this->authSettingsTypeDTO->PROFILE_LIMIT_DATE_SMS,
        ], false);

        $this->form = $form;
    }
}
