<?php

namespace App\Twig\Components;

use App\DTO\TwoFASettingsDTO;
use App\Enum\SettingName;
use App\Form\TwoFASettingsType;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormInterface;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\Attribute\LiveAction;
use Symfony\UX\LiveComponent\Attribute\LiveProp;
use Symfony\UX\LiveComponent\ComponentWithFormTrait;
use Symfony\UX\LiveComponent\DefaultActionTrait;
use Symfony\UX\LiveComponent\LiveCollectionTrait;

#[AsLiveComponent]
final class TwoFASettingsForm extends AbstractController
{
    use ComponentWithFormTrait;
    use DefaultActionTrait;
    use LiveCollectionTrait;

    #[LiveProp]
    public TwoFASettingsDTO|null $twoFASettingsDTO = null;

    /** @var array<string, array{value: ?string, description?: ?string}>|null */
    #[LiveProp]
    public ?array $data = null;

    /**
     * @return FormInterface<mixed>
     */
    #[\Override]
    protected function instantiateForm(): FormInterface
    {
        return $this->createForm(TwoFASettingsType::class, $this->twoFASettingsDTO);
    }

    #[LiveAction]
    public function validate(): void
    {
        $form = $this->createForm(TwoFASettingsType::class, $this->twoFASettingsDTO);

        $form->submit([
            SettingName::TWO_FACTOR_AUTH_STATUS->value =>
                $this->twoFASettingsDTO->twoFaStatus,
            SettingName::TWO_FACTOR_AUTH_APP_LABEL->value =>
                $this->twoFASettingsDTO->twoFaAppLabel,
            SettingName::TWO_FACTOR_AUTH_APP_ISSUER->value =>
                $this->twoFASettingsDTO->twoFaAppIssuer,
            SettingName::TWO_FACTOR_AUTH_CODE_EXPIRATION_TIME->value =>
                $this->twoFASettingsDTO->twoFaCodeExpirationTime,
            SettingName::TWO_FACTOR_AUTH_ATTEMPTS_NUMBER_RESEND_CODE->value =>
                $this->twoFASettingsDTO->twoFaAttemptsNumberResendCode,
            SettingName::TWO_FACTOR_AUTH_TIME_RESET_ATTEMPTS->value =>
                $this->twoFASettingsDTO->twoFaTimeResetAttempts,
            SettingName::TWO_FACTOR_AUTH_RESEND_INTERVAL->value =>
                $this->twoFASettingsDTO->twoFaResendInterval,
        ], false);

        $this->form = $form;
    }
}
