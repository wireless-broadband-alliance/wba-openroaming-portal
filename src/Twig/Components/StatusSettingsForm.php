<?php

namespace App\Twig\Components;

use App\DTO\StatusSettingsDTO;
use App\Enum\SettingName;
use App\Form\StatusType;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormInterface;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\Attribute\LiveAction;
use Symfony\UX\LiveComponent\Attribute\LiveProp;
use Symfony\UX\LiveComponent\ComponentWithFormTrait;
use Symfony\UX\LiveComponent\DefaultActionTrait;
use Symfony\UX\LiveComponent\LiveCollectionTrait;

#[AsLiveComponent]
final class StatusSettingsForm extends AbstractController
{
    use ComponentWithFormTrait;
    use DefaultActionTrait;
    use LiveCollectionTrait;

    #[LiveProp]
    public StatusSettingsDTO|null $statusSettingsDTO = null;

    /** @var array<string, array{value: ?string, description?: ?string}>|null */
    #[LiveProp]
    public ?array $data = null;

    /**
     * @return FormInterface<mixed>
     */
    #[\Override]
    protected function instantiateForm(): FormInterface
    {
        return $this->createForm(StatusType::class, $this->statusSettingsDTO);
    }

    #[LiveAction]
    public function validate(): void
    {
        $form = $this->createForm(StatusType::class, $this->statusSettingsDTO);

        $form->submit([
            SettingName::PLATFORM_MODE->value =>
                $this->statusSettingsDTO->platformMode,
            SettingName::USER_VERIFICATION->value =>
                $this->statusSettingsDTO->userVerification,
            SettingName::TURNSTILE_CHECKER->value =>
                $this->statusSettingsDTO->turnstileChecker,
            SettingName::API_STATUS->value =>
                $this->statusSettingsDTO->apiStatus,
            SettingName::USER_DELETE_TIME->value =>
                $this->statusSettingsDTO->userDeleteTime,
            SettingName::TIME_INTERVAL_NOTIFICATION->value =>
                $this->statusSettingsDTO->timeIntervalNotification,
        ], false);

        $this->form = $form;
    }
}
