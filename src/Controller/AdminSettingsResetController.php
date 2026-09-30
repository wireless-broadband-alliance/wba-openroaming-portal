<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\User;
use App\Enum\AdminRoleType;
use App\Enum\AnalyticalEventType;
use App\Enum\EventMetadataKeysType;
use App\Enum\SettingType;
use App\Repository\EventRepository;
use App\Security\Voter\UserAuthenticationVoter;
use App\Service\EventActions;
use App\Service\HashArgon2idService;
use App\Service\VerificationCodeEmailGenerator;
use DateTime;
use Exception;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Contracts\Translation\TranslatorInterface;

class AdminSettingsResetController extends AbstractController
{
    public function __construct(
        private readonly EventActions $eventActions,
        private readonly TranslatorInterface $translator,
        #[Autowire(param: 'kernel.project_dir')]
        private readonly string $projectDir,
        private readonly MailerInterface $mailer,
        private readonly VerificationCodeEmailGenerator $verificationCodeGenerator,
        private readonly EventRepository $eventRepository,
        private readonly HashArgon2idService $hashArgon2idService,
    ) {
    }

    #[Route('/dashboard/confirm-checker/{type}', name: 'admin_dashboard_confirm_checker')]
    #[IsGranted(AdminRoleType::ROLE_ADMIN->value)]
    public function checkSettings(Request $request, SettingType $type): Response
    {
        /** @var User $currentUser */
        $currentUser = $this->getUser();
        $enteredCode = $request->request->get('code') ?? $request->query->get('code');
        $storedHash = $currentUser->getTwoFAcode();

        // Verify 2FA Code using HashArgon2idService
        if (
            $enteredCode === null
            || $storedHash === null
            || !$this->hashArgon2idService->verifyHash((string) $enteredCode, $storedHash)
        ) {
            $this->addFlash(
                'error',
                $this->translator->trans('incorrectVerificationCode', [], 'controllers')
            );

            return $this->redirectToRoute('admin_dashboard_confirm_reset', ['type' => $type->value]);
        }

        // Fetch reset configuration for requested setting type
        $config = $this->getResetConfiguration($type);

        // Check specific voter/role permissions
        if (!$this->isGranted($config['voter'])) {
            $this->addFlash(
                'error',
                $this->translator->trans('access_denied_no_session', [], 'controllers')
            );

            return $this->redirectToRoute('admin_dashboard_confirm_reset', ['type' => $type->value]);
        }

        // Run CLI Console Reset Command
        $this->runConsoleCommand($config['command']);

        // Log Analytical Event
        $this->eventActions->saveEvent(
            $currentUser,
            $config['event'],
            new DateTime(),
            [
                EventMetadataKeysType::IP->value => $request->getClientIp(),
                EventMetadataKeysType::USER_AGENT->value => $request->headers->get('User-Agent'),
                EventMetadataKeysType::UUID->value => $currentUser->getUuid(),
            ]
        );

        // Flash success message and redirect to setting page
        $this->addFlash(
            'success',
            $this->translator->trans($config['flash'], [], 'controllers')
        );

        return $this->redirectToRoute($config['route']);
    }

    private function runConsoleCommand(string $commandName): void
    {
        $process = new Process(['php', 'bin/console', $commandName, '--yes', '--no-interaction'], $this->projectDir);
        $process->run();

        if (!$process->isSuccessful()) {
            throw new ProcessFailedException($process);
        }
    }

    /**
     * @return array{voter: string, command: string, flash: string, event: string, route: string}
     */
    private function getResetConfiguration(SettingType $type): array
    {
        return match ($type) {
            SettingType::CUSTOM => [
                'voter' => UserAuthenticationVoter::LANDING_PAGE_CONFIG_WRITE,
                'command' => 'reset:customSettings',
                'flash' => 'settingResetSuccessfully',
                'event' => AnalyticalEventType::SETTING_PAGE_STYLE_RESET_REQUEST->value,
                'route' => 'admin_dashboard_customize',
            ],
            SettingType::TERMS => [
                'voter' => UserAuthenticationVoter::TERMS_POLICIES_WRITE,
                'command' => 'reset:termsSettings',
                'flash' => 'termsPoliciesSettingsResetSuccessfully',
                'event' => AnalyticalEventType::SETTING_TERMS_RESET_REQUEST->value,
                'route' => 'admin_dashboard_settings_terms',
            ],
            SettingType::RADIUS => [
                'voter' => UserAuthenticationVoter::RADIUS_PROFILE_CONFIG_WRITE,
                'command' => 'reset:radiusSettings',
                'flash' => 'radiusConfigurationsResetSuccessfully',
                'event' => AnalyticalEventType::SETTING_RADIUS_CONF_RESET_REQUEST->value,
                'route' => 'admin_dashboard_settings_radius',
            ],
            SettingType::LDAP => [
                'voter' => UserAuthenticationVoter::LDAP_SYNCHRONIZATION_WRITE,
                'command' => 'reset:ldapSettings',
                'flash' => 'LDAPSettingsResetSuccessfully',
                'event' => AnalyticalEventType::SETTING_LDAP_CONF_RESET_REQUEST->value,
                'route' => 'admin_dashboard_settings_LDAP',
            ],
            SettingType::STATUS => [
                'voter' => UserAuthenticationVoter::PLATFORM_STATUS_WRITE,
                'command' => 'reset:statusSettings',
                'flash' => 'platformModeStatusResetSuccessfully',
                'event' => AnalyticalEventType::SETTING_PLATFORM_STATUS_RESET_REQUEST->value,
                'route' => 'admin_dashboard_settings_status',
            ],
            SettingType::CAPPORT => [
                'voter' => UserAuthenticationVoter::USER_ENGAGEMENT_WRITE,
                'command' => 'reset:capportSettings',
                'flash' => 'platformModeStatusResetSuccessfully',
                'event' => AnalyticalEventType::SETTING_CAPPORT_CONF_RESET_REQUEST->value,
                'route' => 'admin_dashboard_settings_capport',
            ],
            SettingType::AUTH => [
                'voter' => UserAuthenticationVoter::AUTHENTICATION_METHODS_WRITE,
                'command' => 'reset:authSettings',
                'flash' => 'authenticationSettingsResetSuccessfully',
                'event' => AnalyticalEventType::SETTING_AUTHS_CONF_RESET_REQUEST->value,
                'route' => 'admin_dashboard_settings_auth',
            ],
            SettingType::TWO_FA => [
                'voter' => UserAuthenticationVoter::TWO_FACTOR_AUTH_WRITE,
                'command' => 'reset:twoFASettings',
                'flash' => 'authenticationSettingsResetSuccessfully',
                'event' => AnalyticalEventType::SETTING_PLATFORM_2FA_RESET_REQUEST->value,
                'route' => 'admin_dashboard_settings_two_fa',
            ],
            SettingType::SMS => [
                'voter' => UserAuthenticationVoter::SMS_CONFIG_WRITE,
                'command' => 'reset:smsSettings',
                'flash' => 'SMSSettingsClearSuccessfully',
                'event' => AnalyticalEventType::SETTING_SMS_CONF_CLEAR_REQUEST->value,
                'route' => 'admin_dashboard_settings_sms',
            ],
            SettingType::SCHEDULE => [
                'voter' => UserAuthenticationVoter::CRON_SCHEDULE_WRITE,
                'command' => 'reset:ScheduleSettings',
                'flash' => 'configurationScheduleClearSuccessfully',
                'event' => AnalyticalEventType::SETTING_SMS_CONF_CLEAR_REQUEST->value,
                'route' => 'admin_dashboard_settings_schedule',
            ],
            SettingType::RETURN_APPS => [
                'voter' => UserAuthenticationVoter::RETURN_APPS_MANAGEMENT_WRITE,
                'command' => 'reset:returnApps',
                'flash' => 'returnAppsResetSuccessfully',
                'event' => AnalyticalEventType::RETURN_APPS_RESET_REQUEST->value,
                'route' => 'admin_dashboard_return_apps',
            ],
            SettingType::SECURITY_TXT => [
                'voter' => UserAuthenticationVoter::TERMS_POLICIES_WRITE,
                'command' => 'reset:securityTxtSettings',
                'flash' => 'securityTxtSettingsResetSuccessfully',
                'event' => AnalyticalEventType::SETTING_TERMS_RESET_REQUEST->value,
                'route' => 'admin_dashboard_settings_security_txt',
            ],
            SettingType::ADMIN_IP_RESTRICTION => [
                'voter' => AdminRoleType::ROLE_SUPER_ADMIN->value,
                'command' => 'reset:admin-ip-restriction',
                'flash' => 'ipRestrictionResetSuccessfully',
                'event' => AnalyticalEventType::SETTING_IP_RESTRICTION_CONF_REQUEST->value,
                'route' => 'admin_dashboard_settings_ip_restriction',
            ],
        };
    }

    /**
     * Regenerate the verification code for the user and send a new email.
     *
     * @param string $type Type of action
     * @return RedirectResponse A redirect response.
     * @throws Exception
     * @throws TransportExceptionInterface
     * @throws \DateMalformedStringException
     */
    #[Route('/dashboard/regenerate/{type}', name: 'app_dashboard_regenerate_code_admin')]
    #[IsGranted(AdminRoleType::ROLE_ADMIN->value)]
    public function regenerateCode(string $type, Request $request): RedirectResponse
    {
        /** @var User $currentUser */
        $currentUser = $this->getUser();

        // Regenerate the verification code for the admin to reset settings
        if (
            in_array($type, [
                SettingType::CUSTOM->value,
                SettingType::TERMS->value,
                SettingType::RADIUS->value,
                SettingType::STATUS->value,
                SettingType::LDAP->value,
                SettingType::CAPPORT->value,
                SettingType::AUTH->value,
                SettingType::TWO_FA->value,
                SettingType::SMS->value,
                SettingType::SCHEDULE->value,
                SettingType::RETURN_APPS->value,
                SettingType::SECURITY_TXT->value,
                SettingType::ADMIN_IP_RESTRICTION->value,
            ], true)
        ) {
            $lastResend = $this->eventRepository->findLatest2FACodeAttemptEvent(
                $currentUser,
                AnalyticalEventType::SETTING_RESET_CODE_REQUEST->value
            );

            $timeIntervalInSeconds = 120;

            if ($this->verificationCodeGenerator->canResendCode($currentUser, $timeIntervalInSeconds)) {
                $email = $this->verificationCodeGenerator->createEmailAdminPage(
                    $currentUser,
                    $request->getClientIp(),
                    $request->headers->get('User-Agent'),
                    $type
                );

                $this->mailer->send($email);
                $this->addFlash(
                    'success',
                    $this->translator->trans(
                        'successResendAdmin',
                        ['%email%' => $currentUser->getEmail()],
                        'controllers'
                    )
                );

                return $this->redirectToRoute('admin_dashboard_confirm_reset', ['type' => $type]);
            }

            $timeLeft = $this->verificationCodeGenerator->timeLeftToResendCode($timeIntervalInSeconds, $lastResend);

            $this->addFlash(
                'error',
                $this->translator->trans(
                    'errorAdminWait',
                    ['%time%' => $timeLeft],
                    'controllers'
                )
            );

            return $this->redirectToRoute('admin_dashboard_confirm_reset', ['type' => $type]);
        }

        return $this->redirectToRoute('admin_page');
    }
}
