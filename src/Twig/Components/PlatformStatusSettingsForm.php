<?php

namespace App\Twig\Components;

use App\DTO\PlatformStatusSettingsDTO;
use App\Enum\SettingName;
use App\Form\PlatformStatusSettingsType;
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
final class PlatformStatusSettingsForm extends AbstractController
{
    use ComponentWithFormTrait;
    use DefaultActionTrait;
    use LiveCollectionTrait;

    #[LiveProp]
    public PlatformStatusSettingsDTO|null $platformStatusSettingsDTO = null;

    /** @var array<string, array{value: ?string, description?: ?string}>|null */
    #[LiveProp]
    public ?array $data = null;

    /**
     * @return FormInterface<mixed>
     */
    #[\Override]
    protected function instantiateForm(): FormInterface
    {
        $canWrite = $this->isGranted(UserAuthenticationVoter::PLATFORM_STATUS_WRITE);

        return $this->createForm(
            PlatformStatusSettingsType::class,
            $this->platformStatusSettingsDTO,
            ['disabled' => !$canWrite]
        );
    }

    #[LiveAction]
    public function validate(): void
    {
        $form = $this->createForm(PlatformStatusSettingsType::class, $this->platformStatusSettingsDTO);

        $form->submit([
            SettingName::PLATFORM_MODE->value =>
                $this->platformStatusSettingsDTO->platformMode,
            SettingName::USER_VERIFICATION->value =>
                $this->platformStatusSettingsDTO->userVerification,
            SettingName::TURNSTILE_CHECKER->value =>
                $this->platformStatusSettingsDTO->turnstileChecker,
            SettingName::API_STATUS->value =>
                $this->platformStatusSettingsDTO->apiStatus,
            SettingName::USER_DELETE_TIME->value =>
                $this->platformStatusSettingsDTO->userDeleteTime,
            SettingName::TIME_INTERVAL_NOTIFICATION->value =>
                $this->platformStatusSettingsDTO->timeIntervalNotification,
        ], false);

        $this->form = $form;
    }
}
