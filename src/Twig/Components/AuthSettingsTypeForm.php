<?php

namespace App\Twig\Components;

use App\DTO\AuthSettingsTypeDTO;
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
            'AUTH_METHOD_SAML_ENABLED' => $this->authSettingsTypeDTO?->AUTH_METHOD_SAML_ENABLED,
            'AUTH_METHOD_SAML_LABEL' => $this->authSettingsTypeDTO?->AUTH_METHOD_SAML_LABEL,
            'AUTH_METHOD_SAML_DESCRIPTION' => $this->authSettingsTypeDTO?->AUTH_METHOD_SAML_DESCRIPTION,
            'PROFILE_LIMIT_DATE_SAML' => $this->authSettingsTypeDTO?->PROFILE_LIMIT_DATE_SAML,

            'AUTH_METHOD_GOOGLE_LOGIN_ENABLED' =>
                $this->authSettingsTypeDTO?->AUTH_METHOD_GOOGLE_LOGIN_ENABLED,
            'AUTH_METHOD_GOOGLE_LOGIN_LABEL' => $this->authSettingsTypeDTO?->AUTH_METHOD_GOOGLE_LOGIN_LABEL,
            'AUTH_METHOD_GOOGLE_LOGIN_DESCRIPTION' =>
                $this->authSettingsTypeDTO?->AUTH_METHOD_GOOGLE_LOGIN_DESCRIPTION,
            'VALID_DOMAINS_GOOGLE_LOGIN' => $this->authSettingsTypeDTO?->VALID_DOMAINS_GOOGLE_LOGIN,
            'PROFILE_LIMIT_DATE_GOOGLE' => $this->authSettingsTypeDTO?->PROFILE_LIMIT_DATE_GOOGLE,

            'AUTH_METHOD_MICROSOFT_LOGIN_ENABLED' =>
                $this->authSettingsTypeDTO?->AUTH_METHOD_MICROSOFT_LOGIN_ENABLED,
            'AUTH_METHOD_MICROSOFT_LOGIN_LABEL' =>
                $this->authSettingsTypeDTO?->AUTH_METHOD_MICROSOFT_LOGIN_LABEL,
            'AUTH_METHOD_MICROSOFT_LOGIN_DESCRIPTION' => $this->
            authSettingsTypeDTO?->
            AUTH_METHOD_MICROSOFT_LOGIN_DESCRIPTION,
            'VALID_DOMAINS_MICROSOFT_LOGIN' => $this->authSettingsTypeDTO?->VALID_DOMAINS_MICROSOFT_LOGIN,
            'PROFILE_LIMIT_DATE_MICROSOFT' => $this->authSettingsTypeDTO?->PROFILE_LIMIT_DATE_MICROSOFT,

            'AUTH_METHOD_REGISTER_ENABLED' => $this->authSettingsTypeDTO?->AUTH_METHOD_REGISTER_ENABLED,
            'AUTH_METHOD_REGISTER_LABEL' => $this->authSettingsTypeDTO?->AUTH_METHOD_REGISTER_LABEL,
            'AUTH_METHOD_REGISTER_DESCRIPTION' =>
                $this->authSettingsTypeDTO?->AUTH_METHOD_REGISTER_DESCRIPTION,
            'PROFILE_LIMIT_DATE_EMAIL' => $this->authSettingsTypeDTO?->PROFILE_LIMIT_DATE_EMAIL,
            'EMAIL_TIMER_RESEND' => $this->authSettingsTypeDTO?->EMAIL_TIMER_RESEND,
            'LINK_VALIDITY' => $this->authSettingsTypeDTO?->LINK_VALIDITY,

            'AUTH_METHOD_LOGIN_TRADITIONAL_ENABLED' =>
                $this->authSettingsTypeDTO?->AUTH_METHOD_LOGIN_TRADITIONAL_ENABLED,
            'AUTH_METHOD_LOGIN_TRADITIONAL_LABEL' =>
                $this->authSettingsTypeDTO?->AUTH_METHOD_LOGIN_TRADITIONAL_LABEL,
            'AUTH_METHOD_LOGIN_TRADITIONAL_DESCRIPTION' =>
                $this->authSettingsTypeDTO?->AUTH_METHOD_LOGIN_TRADITIONAL_DESCRIPTION,

            'LOGIN_WITH_UUID_ONLY' => $this->authSettingsTypeDTO?->LOGIN_WITH_UUID_ONLY,

            'AUTH_METHOD_SMS_REGISTER_ENABLED' =>
                $this->authSettingsTypeDTO?->AUTH_METHOD_SMS_REGISTER_ENABLED,
            'AUTH_METHOD_SMS_REGISTER_LABEL' => $this->authSettingsTypeDTO?->AUTH_METHOD_SMS_REGISTER_LABEL,
            'AUTH_METHOD_SMS_REGISTER_DESCRIPTION' =>
                $this->authSettingsTypeDTO?->AUTH_METHOD_SMS_REGISTER_DESCRIPTION,
            'PROFILE_LIMIT_DATE_SMS' => $this->authSettingsTypeDTO?->PROFILE_LIMIT_DATE_SMS,
        ], false);

        $this->form = $form;
    }
}
