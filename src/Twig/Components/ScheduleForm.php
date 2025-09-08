<?php

namespace App\Twig\Components;

use App\DTO\ScheduleDTO;
use App\DTO\ScheduleSettingDTO;
use App\Enum\OperationMode;
use App\Enum\SettingName;
use App\Form\ScheduleType;
use App\Repository\SettingRepository;
use App\Service\CronExpressionHelperService;
use App\Service\SchedulerService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormInterface;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\Attribute\LiveAction;
use Symfony\UX\LiveComponent\Attribute\LiveProp;
use Symfony\UX\LiveComponent\ComponentWithFormTrait;
use Symfony\UX\LiveComponent\DefaultActionTrait;
use Symfony\UX\LiveComponent\LiveCollectionTrait;

#[AsLiveComponent]
final class ScheduleForm extends AbstractController
{
    use ComponentWithFormTrait;
    use DefaultActionTrait;
    use LiveCollectionTrait;

    #[LiveProp]
    public ScheduleDTO|null $scheduleDTO = null;

    #[LiveProp]
    public bool|null $deleteUnconfirmedWarning = null;

    #[LiveProp]
    public bool|null $profileExpiredWarning = null;

    #[LiveProp]
    public bool|null $ldapCronWarning = null;

    #[LiveProp]
    public bool|null $freeradiusLastConnectionWarning = null;

    #[LiveProp]
    public bool|null $default_use_advanced_mode = false;

    public function __construct(
        private readonly SettingRepository $settingRepository,
        private readonly CronExpressionHelperService $cronHelper,
        private readonly SchedulerService $schedulerService
    ) {
        $cronAdvanceStatus = $this->settingRepository->findOneBy(["name" => SettingName::CRON_ADVANCED_STATUS->value]);
        if (!is_null($cronAdvanceStatus)) {
            $this->default_use_advanced_mode = $cronAdvanceStatus->getValue() === OperationMode::ON->value;
        }
    }

    /**
     * @return FormInterface<mixed>
     */
    #[\Override]
    protected function instantiateForm(): FormInterface
    {
        return $this->createForm(ScheduleType::class, $this->scheduleDTO);
    }

    #[LiveAction]
    public function toggleMode(): void
    {
        if (!$this->scheduleDTO instanceof ScheduleDTO) {
            return;
        }

        $this->default_use_advanced_mode = !$this->default_use_advanced_mode;
        $this->scheduleDTO->use_advanced_mode = $this->default_use_advanced_mode;

        if ($this->default_use_advanced_mode) {
            // Switching to advanced mode → regenerate cron strings
            $this->scheduleDTO->delete_unconfirmed_users_cron->advanced =
                $this->scheduleDTO->delete_unconfirmed_users_cron->toCronExpression(false, $this->cronHelper);

            $this->scheduleDTO->users_when_profile_expires_cron->advanced =
                $this->scheduleDTO->users_when_profile_expires_cron->toCronExpression(false, $this->cronHelper);

            $this->scheduleDTO->ldap_sync_cron->advanced =
                $this->scheduleDTO->ldap_sync_cron->toCronExpression(false, $this->cronHelper);
        } else {
            // Switching to simple mode → recreate DTOs from cron expression values
            $this->scheduleDTO->delete_unconfirmed_users_cron =
                new ScheduleSettingDTO(
                    SettingName::DELETE_UNCONFIRMED_USERS_CRON->value,
                    $this->settingRepository,
                    $this->cronHelper,
                    $this->scheduleDTO->delete_unconfirmed_users_cron->advanced
                );

            $this->scheduleDTO->users_when_profile_expires_cron =
                new ScheduleSettingDTO(
                    SettingName::USERS_WHEN_PROFILE_EXPIRES_CRON->value,
                    $this->settingRepository,
                    $this->cronHelper,
                    $this->scheduleDTO->users_when_profile_expires_cron->advanced
                );

            $this->scheduleDTO->ldap_sync_cron =
                new ScheduleSettingDTO(
                    SettingName::LDAP_SYNC_CRON->value,
                    $this->settingRepository,
                    $this->cronHelper,
                    $this->scheduleDTO->ldap_sync_cron->advanced
                );

            $this->scheduleDTO->freeradius_last_connection_cron =
                new ScheduleSettingDTO(
                    SettingName::FREERADIUS_LAST_CONNECTION_CRON->value,
                    $this->settingRepository,
                    $this->cronHelper,
                    $this->scheduleDTO->freeradius_last_connection_cron->advanced
                );
        }

        $this->resetForm();
    }
}
