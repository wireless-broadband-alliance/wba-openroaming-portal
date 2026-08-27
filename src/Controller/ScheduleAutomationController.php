<?php

namespace App\Controller;

use App\DTO\ScheduleDTO;
use App\Entity\User;
use App\Enum\AnalyticalEventType;
use App\Enum\EventMetadataKeysType;
use App\Enum\OperationMode;
use App\Enum\SettingName;
use App\Form\ScheduleType;
use App\Repository\SettingRepository;
use App\Security\Voter\UserAuthenticationVoter;
use App\Service\CronExpressionHelperService;
use App\Service\EventActions;
use App\Service\GetSettings;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Contracts\Translation\TranslatorInterface;

class ScheduleAutomationController extends AbstractController
{
    public function __construct(
        private readonly GetSettings $getSettings,
        private readonly SettingRepository $settingRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly EventActions $eventActions,
        private readonly TranslatorInterface $translator,
        private readonly CronExpressionHelperService $cronExpressionHelperService
    ) {
    }

    #[Route('/dashboard/settings/schedule', name: 'admin_dashboard_settings_schedule')]
    #[IsGranted(UserAuthenticationVoter::CRON_SCHEDULE_READ)]
    public function settingsSchedule(Request $request): Response
    {
        /** @var User $currentUser */
        $currentUser = $this->getUser();
        $canWrite = $this->isGranted(UserAuthenticationVoter::CRON_SCHEDULE_WRITE);

        /** @var array<string, array{value: string, description: string}> $data */
        $data = $this->getSettings->getSettings();

        $scheduleDTO = new ScheduleDTO($this->settingRepository, $this->cronExpressionHelperService);

        $form = $this->createForm(ScheduleType::class, $scheduleDTO, ['disabled' => !$canWrite]);

        $form->handleRequest($request);

        if ($canWrite && $form->isSubmitted() && $form->isValid()) {
            $changeset = [];

            // Track cron expression changes
            foreach (
                $scheduleDTO->toCronExpressions(
                    $this->cronExpressionHelperService
                ) as $settingName => $cronExpression
            ) {
                if ($data[$settingName]['value'] !== $cronExpression) {
                    $changeset[$settingName] = [
                        EventMetadataKeysType::OLD_DATA->value => $data[$settingName]['value'],
                        EventMetadataKeysType::NEW_DATA->value => $cronExpression,
                    ];
                }
                $this->saveSetting($settingName, $cronExpression, $scheduleDTO->use_advanced_mode);
            }

            $newUserDeleteTime = $scheduleDTO->delete_unconfirmed_users_cron->userDeleteTime;

            $userDeleteTime = $this->settingRepository->findOneBy(['name' => SettingName::USER_DELETE_TIME->value]);

            if ($userDeleteTime) {
                $userDeleteTime->setValue((string)$newUserDeleteTime);
                $this->entityManager->persist($userDeleteTime);
            }

            $newNotificationTime = $scheduleDTO->users_when_profile_expires_cron->timeIntervalNotification;

            $notificationTime = $this->settingRepository->findOneBy([
                'name' => SettingName::TIME_INTERVAL_NOTIFICATION->value
            ]);

            if ($notificationTime) {
                $notificationTime->setValue((string)$newNotificationTime);
                $this->entityManager->persist($notificationTime);
            }

            $newUserRetentionDays = $scheduleDTO->cleanup_expired_data_cron->userRetentionDays;
            $userRetentionSetting = $this->settingRepository->findOneBy(['name' => SettingName::USER_RETENTION_DAYS->value]);

            if ($userRetentionSetting) {
                if ($userRetentionSetting->getValue() !== (string)$newUserRetentionDays) {
                    $changeset[SettingName::USER_RETENTION_DAYS->value] = [
                        EventMetadataKeysType::OLD_DATA->value => $userRetentionSetting->getValue(),
                        EventMetadataKeysType::NEW_DATA->value => (string)$newUserRetentionDays,
                    ];
                }
                $userRetentionSetting->setValue((string)$newUserRetentionDays);
                $this->entityManager->persist($userRetentionSetting);
            }

            $newOtpExpirationHours = $scheduleDTO->cleanup_expired_data_cron->otpExpirationHours;
            $otpExpirationSetting = $this->settingRepository->findOneBy(['name' => SettingName::OTP_EXPIRATION_HOURS->value]);

            if ($otpExpirationSetting) {
                if ($otpExpirationSetting->getValue() !== (string)$newOtpExpirationHours) {
                    $changeset[SettingName::OTP_EXPIRATION_HOURS->value] = [
                        EventMetadataKeysType::OLD_DATA->value => $otpExpirationSetting->getValue(),
                        EventMetadataKeysType::NEW_DATA->value => (string)$newOtpExpirationHours,
                    ];
                }
                $otpExpirationSetting->setValue((string)$newOtpExpirationHours);
                $this->entityManager->persist($otpExpirationSetting);
            }

            // Track enablement changes
            $enablementSettings = [
                SettingName::DELETE_UNCONFIRMED_USERS_CRON_ENABLED->value =>
                    $scheduleDTO->delete_unconfirmed_users_enabled,
                SettingName::USERS_WHEN_PROFILE_EXPIRES_CRON_ENABLED->value =>
                    $scheduleDTO->users_when_profile_expires_enabled,
                SettingName::LDAP_SYNC_CRON_ENABLED->value =>
                    $scheduleDTO->ldap_sync_enabled,
                SettingName::DOMAIN_BLACKLIST_IMPORT_CRON_ENABLED->value =>
                    $scheduleDTO->domain_blacklist_import_enabled,
                SettingName::CLEANUP_EXPIRED_DATA_CRON_ENABLED->value =>
                    $scheduleDTO->cleanup_expired_data_enabled
            ];

            foreach ($enablementSettings as $settingName => $newEnabledValue) {
                $newValue = $newEnabledValue ? OperationMode::ON->value : OperationMode::OFF->value;
                $oldValue = $this->settingRepository->findOneBy(['name' => $settingName])?->getValue()
                    ?? OperationMode::ON->value; // fallback matches isEnabled() default

                if ($oldValue !== $newValue) {
                    $changeset[$settingName] = [
                        EventMetadataKeysType::OLD_DATA->value => $oldValue,
                        EventMetadataKeysType::NEW_DATA->value => $newValue,
                    ];
                }

                $this->saveSetting($settingName, $newValue, $scheduleDTO->use_advanced_mode);
            }

            // Analytics
            $this->eventActions->saveEvent(
                $currentUser,
                AnalyticalEventType::SETTING_SCHEDULE_CONF_REQUEST->value,
                new DateTime(),
                [
                    EventMetadataKeysType::IP->value => $request->getClientIp(),
                    EventMetadataKeysType::USER_AGENT->value => $request->headers->get('User-Agent'),
                    EventMetadataKeysType::UUID->value => $currentUser->getUuid(),
                    EventMetadataKeysType::CHANGESET->value => $changeset
                ]
            );

            $this->entityManager->flush();

            $this->addFlash(
                'success',
                $this->translator->trans('scheduleConfigSuccess', [], 'controllers')
            );
            return $this->redirectToRoute('admin_dashboard_settings_schedule');
        }

        return $this->render('dashboard/shared/settings_actions.html.twig', [
            'user' => $currentUser,
            'data' => $data,
            'settings' => $this->settingRepository->findAll(),
            'form' => $form->createView(),
            'formDTO' => $scheduleDTO,
        ]);
    }

    /**
     * Save or update a setting by name.
     */
    private function saveSetting(string $name, ?string $value, bool $advancedMode): void
    {
        $setting = $this->settingRepository->findOneBy(['name' => $name]);
        if ($setting !== null) {
            $setting->setValue($value);
            $this->entityManager->persist($setting);
        }
        $advancedModeStatus = $this->settingRepository->findOneBy(
            ['name' => SettingName::CRON_ADVANCED_STATUS->value]
        );
        $advancedModeValue = $advancedMode ? OperationMode::ON->value : OperationMode::OFF->value;
        if ($advancedModeStatus !== null) {
            $advancedModeStatus->setValue($advancedModeValue);
            $this->entityManager->persist($advancedModeStatus);
        }
    }
}
