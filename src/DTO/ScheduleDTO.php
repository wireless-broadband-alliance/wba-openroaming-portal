<?php

namespace App\DTO;

use App\Enum\OperationMode;
use App\Enum\SettingName;
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
    public ?ScheduleSettingDTO $domain_blacklist_import_cron = null;

    #[Assert\Valid]
    #[Assert\When(
        expression: "this.use_advanced_mode != null and this.use_advanced_mode",
        constraints: [
            new AcmeAssert\CronNotEmpty()
        ],
    )]
    public ?ScheduleSettingDTO $cleanup_expired_data_cron = null;

    public ?bool $delete_unconfirmed_users_enabled = true;
    public ?bool $users_when_profile_expires_enabled = true;
    public ?bool $ldap_sync_enabled = true;
    public ?bool $domain_blacklist_import_enabled = true;
    public ?bool $cleanup_expired_data_enabled = true;

    public function __construct(
        ?SettingRepository $settingRepository = null,
        ?CronExpressionHelperService $cronExpressionHelperService = null
    ) {
        if (!is_null($settingRepository)) {
            $cronAdvanceStatus = $settingRepository->findOneBy(["name" => SettingName::CRON_ADVANCED_STATUS->value]);
            if (!is_null($cronAdvanceStatus)) {
                $this->use_advanced_mode = $cronAdvanceStatus->getValue() === OperationMode::ON->value;
            }
        }

        $this->delete_unconfirmed_users_cron = new ScheduleSettingDTO(
            SettingName::DELETE_UNCONFIRMED_USERS_CRON->value,
            $settingRepository,
            $cronExpressionHelperService
        );

        $this->users_when_profile_expires_cron = new ScheduleSettingDTO(
            SettingName::USERS_WHEN_PROFILE_EXPIRES_CRON->value,
            $settingRepository,
            $cronExpressionHelperService
        );

        $this->ldap_sync_cron = new ScheduleSettingDTO(
            SettingName::LDAP_SYNC_CRON->value,
            $settingRepository,
            $cronExpressionHelperService
        );

        $this->domain_blacklist_import_cron = new ScheduleSettingDTO(
            SettingName::DOMAIN_BLACKLIST_IMPORT_CRON->value,
            $settingRepository,
            $cronExpressionHelperService
        );

        $this->cleanup_expired_data_cron = new ScheduleSettingDTO(
            SettingName::CLEANUP_EXPIRED_DATA_CRON->value,
            $settingRepository,
            $cronExpressionHelperService
        );

        if (!is_null($settingRepository)) {
            $cronAdvanceStatus = $settingRepository->findOneBy(["name" => SettingName::CRON_ADVANCED_STATUS->value]);
            if (!is_null($cronAdvanceStatus)) {
                $this->use_advanced_mode = $cronAdvanceStatus->getValue() === OperationMode::ON->value;
            }

            $this->delete_unconfirmed_users_enabled = $settingRepository
                    ->findOneBy(['name' => SettingName::DELETE_UNCONFIRMED_USERS_CRON_ENABLED->value])
                    ?->getValue() !== OperationMode::OFF->value;

            $this->users_when_profile_expires_enabled = $settingRepository
                    ->findOneBy(['name' => SettingName::USERS_WHEN_PROFILE_EXPIRES_CRON_ENABLED->value])
                    ?->getValue() !== OperationMode::OFF->value;

            $this->ldap_sync_enabled = $settingRepository
                    ->findOneBy(['name' => SettingName::LDAP_SYNC_CRON_ENABLED->value])
                    ?->getValue() !== OperationMode::OFF->value;

            $this->domain_blacklist_import_enabled = $settingRepository
                    ->findOneBy(['name' => SettingName::DOMAIN_BLACKLIST_IMPORT_CRON_ENABLED->value])
                    ?->getValue() !== OperationMode::OFF->value;

            $this->cleanup_expired_data_enabled = $settingRepository
                    ->findOneBy(['name' => SettingName::CLEANUP_EXPIRED_DATA_CRON_ENABLED->value])
                    ?->getValue() !== OperationMode::OFF->value;
        }
    }

    /**
     * @return array<string, string>
     */
    public function toCronExpressions(CronExpressionHelperService $cronExpressionHelperService): array
    {
        return [
            SettingName::DELETE_UNCONFIRMED_USERS_CRON->value =>
                $this->delete_unconfirmed_users_cron->toCronExpression(
                    $this->use_advanced_mode,
                    $cronExpressionHelperService
                ),
            SettingName::USERS_WHEN_PROFILE_EXPIRES_CRON->value =>
                $this->users_when_profile_expires_cron->toCronExpression(
                    $this->use_advanced_mode,
                    $cronExpressionHelperService
                ),
            SettingName::LDAP_SYNC_CRON->value =>
                $this->ldap_sync_cron->toCronExpression(
                    $this->use_advanced_mode,
                    $cronExpressionHelperService
                ),
            SettingName::DOMAIN_BLACKLIST_IMPORT_CRON->value =>
                $this->domain_blacklist_import_cron->toCronExpression(
                    $this->use_advanced_mode,
                    $cronExpressionHelperService
                ),
            SettingName::CLEANUP_EXPIRED_DATA_CRON->value =>
                $this->cleanup_expired_data_cron->toCronExpression(
                    $this->use_advanced_mode,
                    $cronExpressionHelperService
                ),
        ];
    }
}
