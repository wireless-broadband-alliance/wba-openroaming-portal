<?php

namespace App\Twig\Components;

use App\DTO\SMSSettingsDTO;
use App\Enum\SettingName;
use App\Form\SMSSettingsType;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormInterface;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\Attribute\LiveAction;
use Symfony\UX\LiveComponent\Attribute\LiveProp;
use Symfony\UX\LiveComponent\ComponentWithFormTrait;
use Symfony\UX\LiveComponent\DefaultActionTrait;
use Symfony\UX\LiveComponent\LiveCollectionTrait;

#[AsLiveComponent]
final class SMSSettingsForm extends AbstractController
{
    use ComponentWithFormTrait;
    use DefaultActionTrait;
    use LiveCollectionTrait;

    #[LiveProp]
    public ?SMSSettingsDTO $SMSSettingsDTO = null;

    /**
     * Raw settings data fetched from database or service.
     * @var array<string, array{value: ?string, description?: ?string}>|null
     */
    #[LiveProp]
    public ?array $data = null;

    /**
     * @return FormInterface<mixed>
     */
    #[\Override]
    protected function instantiateForm(): FormInterface
    {
        return $this->createForm(SMSSettingsType::class, $this->SMSSettingsDTO);
    }

    #[LiveAction]
    public function validate(): void
    {
        // Create form manually to trigger validation
        $form = $this->createForm(SMSSettingsType::class, $this->SMSSettingsDTO);

        // Normalize array inputs (convert non-array to array safely)
        /** @var string[]|string $regions */
        $regions = $this->SMSSettingsDTO->defaultRegionPhoneInputs;

        if (!is_array($regions)) {
            $regions = !empty($regions)
                ? array_map('trim', explode(',', (string)$regions))
                : [];
        }

        // Submit current DTO data (manual mapping)
        $form->submit([
            SettingName::SMS_USERNAME->value => $this->SMSSettingsDTO?->smsUsername,
            SettingName::SMS_USER_ID->value => $this->SMSSettingsDTO?->smsUserId,
            SettingName::SMS_HANDLE->value => $this->SMSSettingsDTO?->smsHandle,
            SettingName::SMS_FROM->value => $this->SMSSettingsDTO?->smsFrom,
            SettingName::SMS_TIMER_RESEND->value => $this->SMSSettingsDTO?->smsTimerResend,
            SettingName::DEFAULT_REGION_PHONE_INPUTS->value => $regions,
        ], false);

        // Store validated form
        $this->form = $form;
    }
}
