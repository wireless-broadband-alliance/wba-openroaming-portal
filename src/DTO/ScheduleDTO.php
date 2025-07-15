<?php

namespace App\DTO;

use App\Enum\OperationMode;
use App\Repository\SettingRepository;
use App\Service\CronExpressionHelperService;
use Symfony\Component\Validator\Constraints as Assert;
use App\Validator as AcmeAssert;

class ScheduleDTO
{
    #[Assert\NotNull]
    public ?bool $use_advanced_mode = false;

    #[Assert\Valid]
    #[Assert\When(
        expression: "this.use_advanced_mode != null and this.use_advanced_mode",
        constraints: [
            new AcmeAssert\CronNotEmpty()
        ],
    )]
    public ?ScheduleSettingDTO $delete_unconfirmed_users_cron = null;

    #[Assert\Valid]
    #[Assert\When(
        expression: "this.use_advanced_mode != null and this.use_advanced_mode",
        constraints: [
            new AcmeAssert\CronNotEmpty()
        ],
    )]
    public ?ScheduleSettingDTO $users_when_profile_expires_cron = null;

    #[Assert\Valid]
    #[Assert\When(
        expression: "this.use_advanced_mode != null and this.use_advanced_mode",
        constraints: [
            new AcmeAssert\CronNotEmpty()
        ],
    )]
    public ?ScheduleSettingDTO $ldap_sync_cron = null;

    #[Assert\Valid]
    #[Assert\When(
        expression: "this.use_advanced_mode != null and this.use_advanced_mode",
        constraints: [
            new AcmeAssert\CronNotEmpty()
        ],
    )]
    public ?ScheduleSettingDTO $freeradius_last_connection_cron = null;

    public function __construct(
        ?SettingRepository $settingRepository = null,
        ?CronExpressionHelperService $cronExpressionHelperService = null
    ) {
        if (!is_null($settingRepository)) {
            $cronAdvanceStatus = $settingRepository->findOneBy(["name" => "CRON_ADVANCED_STATUS"]);
            if (!is_null($cronAdvanceStatus)) {
                $this->use_advanced_mode = $cronAdvanceStatus->getValue() === OperationMode::ON->value;
            }
        }

        $this->delete_unconfirmed_users_cron = new ScheduleSettingDTO(
            "DELETE_UNCONFIRMED_USERS_CRON",
            $settingRepository,
            $cronExpressionHelperService
        );

        $this->users_when_profile_expires_cron = new ScheduleSettingDTO(
            "USERS_WHEN_PROFILE_EXPIRES_CRON",
            $settingRepository,
            $cronExpressionHelperService
        );

        $this->ldap_sync_cron = new ScheduleSettingDTO(
            "LDAP_SYNC_CRON",
            $settingRepository,
            $cronExpressionHelperService
        );

        $this->freeradius_last_connection_cron = new ScheduleSettingDTO(
            "FREERADIUS_LAST_CONNECTION_CRON",
            $settingRepository,
            $cronExpressionHelperService
        );
    }

    public function toCronExpressions(CronExpressionHelperService $cronExpressionHelperService): array
    {
        return [
            "DELETE_UNCONFIRMED_USERS_CRON" => $this->delete_unconfirmed_users_cron->toCronExpression(
                $this->use_advanced_mode,
                $cronExpressionHelperService
            ),
            "USERS_WHEN_PROFILE_EXPIRES_CRON" => $this->users_when_profile_expires_cron->toCronExpression(
                $this->use_advanced_mode,
                $cronExpressionHelperService
            ),
            "LDAP_SYNC_CRON" => $this->ldap_sync_cron->toCronExpression(
                $this->use_advanced_mode,
                $cronExpressionHelperService
            ),
            "FREERADIUS_LAST_CONNECTION_CRON" => $this->ldap_sync_cron->toCronExpression(
                $this->use_advanced_mode,
                $cronExpressionHelperService
    )
        ];
    }
}
