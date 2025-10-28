<?php

namespace App\Controller;

use App\DTO\LDAPSettingsDTO;
use App\DTO\RadiusSettingsDTO;
use App\Entity\Setting;
use App\Entity\TextEditor;
use App\Entity\User;
use App\Enum\AnalyticalEventType;
use App\Enum\LanguageType;
use App\Enum\OperationMode;
use App\Enum\PlatformMode;
use App\Enum\SettingName;
use App\Enum\SettingType;
use App\Enum\TextEditorName;
use App\Form\AuthType;
use App\Form\CapportType;
use App\Form\LDAPType;
use App\Form\RadiusType;
use App\Form\SMSType;
use App\Form\StatusType;
use App\Form\TermsType;
use App\Form\TwoFASettingsType;
use App\Repository\SettingTranslationRepository;
use App\Repository\TextEditorRepository;
use App\Service\CertificateService;
use App\Service\DomainService;
use App\Service\EnforcePasswordResetService;
use App\Service\EventActions;
use App\Service\GetSettings;
use App\Service\SanitizeHTML;
use App\Service\SettingsService;
use DateTime;
use DateTimeZone;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Contracts\Translation\TranslatorInterface;

class SettingsController extends AbstractController
{
    public function __construct(
        private readonly EventActions $eventActions,
        private readonly GetSettings $getSettings,
        private readonly EntityManagerInterface $entityManager,
        private readonly TranslatorInterface $translator,
        private readonly SettingTranslationRepository $settingTranslationRepository,
        private readonly EnforcePasswordResetService $enforcePasswordResetService,
        private readonly CertificateService $certificateService,
        private readonly SettingsService $settingsService,
        private readonly TextEditorRepository $textEditorRepository,
        private readonly DomainService $domainService,
    ) {
    }

    /*
     * Check if the code and then return the correct action
     */
    /**
     * @param string $type Type of action
     */
    #[Route('/dashboard/confirm-checker/{type}', name: 'admin_confirm_checker')]
    #[IsGranted('ROLE_ADMIN')]
    public function checkSettings(
        Request $request,
        string $type
    ): Response {
        // Get the entered code from the form
        $enteredCode = $request->get('code');

        /** @var User $currentUser */
        $currentUser = $this->getUser();

        if ($enteredCode === $currentUser->getTwoFAcode()) {
            if ($type === SettingType::SettingCustom->value) {
                $command = 'php bin/console reset:customSettings --yes';
                $projectRootDir = $this->getParameter('kernel.project_dir');
                $process = new Process(explode(' ', $command), $projectRootDir);
                $process->run();
                if (!$process->isSuccessful()) {
                    throw new ProcessFailedException($process);
                }
                // if you want to dd("$output, $errorOutput"), please use the following variables
                $output = $process->getOutput();
                $errorOutput = $process->getErrorOutput();
                $this->addFlash(
                    'success_admin',
                    $this->translator->trans(
                        'settingResetSuccessfully',
                        [],
                        'controllers'
                    )
                );
                $eventMetadata = [
                    'ip' => $request->getClientIp(),
                    'user_agent' => $request->headers->get('User-Agent'),
                    'uuid' => $currentUser->getUuid(),
                ];
                $this->eventActions->saveEvent(
                    $currentUser,
                    AnalyticalEventType::SETTING_PAGE_STYLE_RESET_REQUEST->value,
                    new DateTime(),
                    $eventMetadata
                );

                return $this->redirectToRoute('admin_dashboard_customize');
            }

            if ($type === SettingType::SettingTerms->value) {
                $command = 'php bin/console reset:termsSettings --yes';
                $projectRootDir = $this->getParameter('kernel.project_dir');
                $process = new Process(explode(' ', $command), $projectRootDir);
                $process->run();
                if (!$process->isSuccessful()) {
                    throw new ProcessFailedException($process);
                }
                // if you want to dd("$output, $errorOutput"), please use the following variables
                $output = $process->getOutput();
                $errorOutput = $process->getErrorOutput();
                $this->addFlash(
                    'success_admin',
                    $this->translator->trans('termsPoliciesSettingsResetSuccessfully', [], 'controllers')
                );

                $eventMetadata = [
                    'ip' => $request->getClientIp(),
                    'user_agent' => $request->headers->get('User-Agent'),
                    'uuid' => $currentUser->getUuid(),
                ];
                $this->eventActions->saveEvent(
                    $currentUser,
                    AnalyticalEventType::SETTING_TERMS_RESET_REQUEST->value,
                    new DateTime(),
                    $eventMetadata
                );

                return $this->redirectToRoute('admin_dashboard_settings_terms');
            }

            if ($type === SettingType::SettingRadius->value) {
                $command = 'php bin/console reset:radiusSettings --yes';
                $projectRootDir = $this->getParameter('kernel.project_dir');
                $process = new Process(explode(' ', $command), $projectRootDir);
                $process->run();
                if (!$process->isSuccessful()) {
                    throw new ProcessFailedException($process);
                }
                // if you want to dd("$output, $errorOutput"), please use the following variables
                $output = $process->getOutput();
                $errorOutput = $process->getErrorOutput();
                $this->addFlash(
                    'success_admin',
                    $this->translator->trans('radiusConfigurationsResetSuccessfully', [], 'controllers')
                );

                $eventMetadata = [
                    'ip' => $request->getClientIp(),
                    'user_agent' => $request->headers->get('User-Agent'),
                    'uuid' => $currentUser->getUuid(),
                ];
                $this->eventActions->saveEvent(
                    $currentUser,
                    AnalyticalEventType::SETTING_RADIUS_CONF_RESET_REQUEST->value,
                    new DateTime(),
                    $eventMetadata
                );

                return $this->redirectToRoute('admin_dashboard_settings_radius');
            }

            if ($type === SettingType::SettingLDAP->value) {
                $command = 'php bin/console reset:ldapSettings --yes';
                $projectRootDir = $this->getParameter('kernel.project_dir');
                $process = new Process(explode(' ', $command), $projectRootDir);
                $process->run();
                if (!$process->isSuccessful()) {
                    throw new ProcessFailedException($process);
                }
                // if you want to dd("$output, $errorOutput"), please use the following variables
                $output = $process->getOutput();
                $errorOutput = $process->getErrorOutput();
                $this->addFlash(
                    'success_admin',
                    $this->translator->trans('LDAPSettingsResetSuccessfully', [], 'controllers')
                );

                $eventMetadata = [
                    'ip' => $request->getClientIp(),
                    'user_agent' => $request->headers->get('User-Agent'),
                    'uuid' => $currentUser->getUuid(),
                ];
                $this->eventActions->saveEvent(
                    $currentUser,
                    AnalyticalEventType::SETTING_LDAP_CONF_RESET_REQUEST->value,
                    new DateTime(),
                    $eventMetadata
                );

                return $this->redirectToRoute('admin_dashboard_settings_LDAP');
            }

            if ($type === SettingType::SettingStatus->value) {
                $command = 'php bin/console reset:statusSettings --yes';
                $projectRootDir = $this->getParameter('kernel.project_dir');
                $process = new Process(explode(' ', $command), $projectRootDir);
                $process->run();
                if (!$process->isSuccessful()) {
                    throw new ProcessFailedException($process);
                }
                // if you want to dd("$output, $errorOutput"), please use the following variables
                $output = $process->getOutput();
                $errorOutput = $process->getErrorOutput();
                $this->addFlash(
                    'success_admin',
                    $this->translator->trans('platformModeStatusResetSuccessfully', [], 'controllers')
                );

                $eventMetadata = [
                    'ip' => $request->getClientIp(),
                    'user_agent' => $request->headers->get('User-Agent'),
                    'uuid' => $currentUser->getUuid(),
                ];
                $this->eventActions->saveEvent(
                    $currentUser,
                    AnalyticalEventType::SETTING_PLATFORM_STATUS_RESET_REQUEST->value,
                    new DateTime(),
                    $eventMetadata
                );

                return $this->redirectToRoute('admin_dashboard_settings_status');
            }

            if ($type === SettingType::SettingCAPPORT->value) {
                $command = 'php bin/console reset:capportSettings --yes';
                $projectRootDir = $this->getParameter('kernel.project_dir');
                $process = new Process(explode(' ', $command), $projectRootDir);
                $process->run();
                if (!$process->isSuccessful()) {
                    throw new ProcessFailedException($process);
                }
                // if you want to dd("$output, $errorOutput"), please use the following variables
                $output = $process->getOutput();
                $errorOutput = $process->getErrorOutput();
                $this->addFlash(
                    'success_admin',
                    $this->translator->trans('platformModeStatusResetSuccessfully', [], 'controllers')
                );

                $eventMetadata = [
                    'ip' => $request->getClientIp(),
                    'user_agent' => $request->headers->get('User-Agent'),
                    'uuid' => $currentUser->getUuid(),
                ];
                $this->eventActions->saveEvent(
                    $currentUser,
                    AnalyticalEventType::SETTING_CAPPORT_CONF_RESET_REQUEST->value,
                    new DateTime(),
                    $eventMetadata
                );

                return $this->redirectToRoute('admin_dashboard_settings_capport');
            }

            if ($type === SettingType::SettingAUTH->value) {
                $command = 'php bin/console reset:authSettings --yes';
                $projectRootDir = $this->getParameter('kernel.project_dir');
                $process = new Process(explode(' ', $command), $projectRootDir);
                $process->run();
                if (!$process->isSuccessful()) {
                    throw new ProcessFailedException($process);
                }
                // if you want to dd("$output, $errorOutput"), please use the following variables
                $output = $process->getOutput();
                $errorOutput = $process->getErrorOutput();
                $this->addFlash(
                    'success_admin',
                    $this->translator->trans('authenticationSettingsResetSuccessfully', [], 'controllers')
                );

                $eventMetadata = [
                    'ip' => $request->getClientIp(),
                    'user_agent' => $request->headers->get('User-Agent'),
                    'uuid' => $currentUser->getUuid(),
                ];
                $this->eventActions->saveEvent(
                    $currentUser,
                    AnalyticalEventType::SETTING_AUTHS_CONF_RESET_REQUEST->value,
                    new DateTime(),
                    $eventMetadata
                );

                return $this->redirectToRoute('admin_dashboard_settings_auth');
            }

            if ($type === SettingType::SettingTwoFA->value) {
                $command = 'php bin/console reset:twoFASettings --yes';
                $projectRootDir = $this->getParameter('kernel.project_dir');
                $process = new Process(explode(' ', $command), $projectRootDir);
                $process->run();
                if (!$process->isSuccessful()) {
                    throw new ProcessFailedException($process);
                }
                // if you want to dd("$output, $errorOutput"), please use the following variables
                $output = $process->getOutput();
                $errorOutput = $process->getErrorOutput();
                $this->addFlash(
                    'success_admin',
                    $this->translator->trans('authenticationSettingsResetSuccessfully', [], 'controllers')
                );

                $eventMetadata = [
                    'ip' => $request->getClientIp(),
                    'user_agent' => $request->headers->get('User-Agent'),
                    'uuid' => $currentUser->getUuid(),
                ];
                $this->eventActions->saveEvent(
                    $currentUser,
                    AnalyticalEventType::SETTING_PLATFORM_2FA_RESET_REQUEST->value,
                    new DateTime(),
                    $eventMetadata
                );

                return $this->redirectToRoute('admin_dashboard_settings_two_fa');
            }

            if ($type === SettingType::SettingSMS->value) {
                $command = 'php bin/console reset:smsSettings --yes';
                $projectRootDir = $this->getParameter('kernel.project_dir');
                $process = new Process(explode(' ', $command), $projectRootDir);
                $process->run();
                if (!$process->isSuccessful()) {
                    throw new ProcessFailedException($process);
                }
                // if you want to dd("$output, $errorOutput"), please use the following variables
                $output = $process->getOutput();
                $errorOutput = $process->getErrorOutput();
                $this->addFlash(
                    'success_admin',
                    $this->translator->trans('SMSSettingsClearSuccessfully', [], 'controllers')
                );

                $eventMetadata = [
                    'ip' => $request->getClientIp(),
                    'user_agent' => $request->headers->get('User-Agent'),
                    'uuid' => $currentUser->getUuid(),
                ];
                $this->eventActions->saveEvent(
                    $currentUser,
                    AnalyticalEventType::SETTING_SMS_CONF_CLEAR_REQUEST->value,
                    new DateTime(),
                    $eventMetadata
                );

                return $this->redirectToRoute('admin_dashboard_settings_sms');
            }

            if ($type === SettingType::SettingSchedule->value) {
                $command = 'php bin/console reset:ScheduleSettings --yes';
                $projectRootDir = $this->getParameter('kernel.project_dir');
                $process = new Process(explode(' ', $command), $projectRootDir);
                $process->run();
                if (!$process->isSuccessful()) {
                    throw new ProcessFailedException($process);
                }
                // if you want to dd("$output, $errorOutput"), please use the following variables
                $output = $process->getOutput();
                $errorOutput = $process->getErrorOutput();
                $this->addFlash(
                    'success_admin',
                    $this->translator->trans('configurationScheduleClearSuccessfully', [], 'controllers'),
                );

                $eventMetadata = [
                    'ip' => $request->getClientIp(),
                    'user_agent' => $request->headers->get('User-Agent'),
                    'uuid' => $currentUser->getUuid(),
                ];
                $this->eventActions->saveEvent(
                    $currentUser,
                    AnalyticalEventType::SETTING_SMS_CONF_CLEAR_REQUEST->value,
                    new DateTime(),
                    $eventMetadata
                );

                return $this->redirectToRoute('admin_dashboard_settings_schedule');
            }
        }

        $this->addFlash(
            'error_admin',
            $this->translator->trans('incorrectVerificationCode', [], 'controllers')
        );
        return $this->redirectToRoute('admin_confirm_reset', ['type' => $type]);
    }

    #[Route(
        '/dashboard/settings/terms/{language}',
        name: 'admin_dashboard_settings_terms',
        defaults: ['language' => LanguageType::EN->value]
    )]
    #[IsGranted('ROLE_ADMIN')]
    public function settingsTerms(
        Request $request,
        string $language,
    ): Response {
        /** @var User $currentUser */
        $currentUser = $this->getUser();

        // TOS TextEditor
        $tosTextEditor = $this->textEditorRepository->findTextEditor(
            TextEditorName::TOS->value,
            $language
        );
        if (!$tosTextEditor instanceof TextEditor) {
            $tosTextEditor = new TextEditor();
            $tosTextEditor->setName(TextEditorName::TOS->value);
            $tosTextEditor->setContent('');
            $tosTextEditor->setLocale($language);
            $this->entityManager->persist($tosTextEditor);
        }

        // Privacy Policy TextEditor
        $privacyPolicyTextEditor = $this->textEditorRepository->findTextEditor(
            TextEditorName::PRIVACY_POLICY->value,
            $language
        );
        if (!$privacyPolicyTextEditor instanceof TextEditor) {
            $privacyPolicyTextEditor = new TextEditor();
            $privacyPolicyTextEditor->setName(TextEditorName::PRIVACY_POLICY->value);
            $privacyPolicyTextEditor->setContent('');
            $privacyPolicyTextEditor->setLocale($language);
            $this->entityManager->persist($privacyPolicyTextEditor);
        }

        $this->entityManager->persist($tosTextEditor);
        $this->entityManager->persist($privacyPolicyTextEditor);
        $this->entityManager->flush();

        $data = $this->getSettings->getSettings();
        $settingsRepository = $this->entityManager->getRepository(Setting::class);
        $settings = $settingsRepository->findAll();

        // Remove old editor settings
        foreach ($settings as $setting) {
            if (
                in_array(
                    $setting->getName(),
                    [TextEditorName::TOS_EDITOR->value, TextEditorName::PRIVACY_POLICY_EDITOR->value],
                    true
                )
            ) {
                $this->entityManager->remove($setting);
            }
        }

        $this->entityManager->flush();

        // Add current TextEditor content to settings array
        $settings = array_merge($settings, [
            new Setting()->setName(TextEditorName::TOS_EDITOR->value)->setValue($tosTextEditor->getContent()),
            new Setting()->setName(TextEditorName::PRIVACY_POLICY_EDITOR->value)->setValue(
                $privacyPolicyTextEditor->getContent()
            ),
        ]);

        $form = $this->createForm(TermsType::class, null, ['settings' => $settings]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $sanitizeHtml = new SanitizeHTML();

            // Update settings using the service
            foreach (
                [
                    SettingName::TOS->value => $form->get(SettingName::TOS->value)->getData(),
                    SettingName::PRIVACY_POLICY->value => $form->get(SettingName::PRIVACY_POLICY->value)->getData(),
                    SettingName::TOS_LINK->value => $form->get(SettingName::TOS_LINK->value)->getData(),
                    SettingName::PRIVACY_POLICY_LINK->value => $form->get(
                        SettingName::PRIVACY_POLICY_LINK->value
                    )->getData(),
                ] as $name => $value
            ) {
                $this->settingsService->update($name, $value);
            }
            $this->settingsService->flush();

            // Update TextEditors
            $tosTextEditor->setContent(
                $sanitizeHtml->sanitizeHtml($form->get(TextEditorName::TOS_EDITOR->value)->getData())
            );
            $privacyPolicyTextEditor->setContent(
                $sanitizeHtml->sanitizeHtml($form->get(TextEditorName::PRIVACY_POLICY_EDITOR->value)->getData())
            );
            $this->entityManager->persist($tosTextEditor);
            $this->entityManager->persist($privacyPolicyTextEditor);
            $this->entityManager->flush();

            // Track event
            $this->eventActions->saveEvent(
                $currentUser,
                AnalyticalEventType::SETTING_TERMS_REQUEST->value,
                new DateTime(),
                [
                    'ip' => $request->getClientIp(),
                    'user_agent' => $request->headers->get('User-Agent'),
                    'uuid' => $currentUser->getUuid(),
                ]
            );

            $this->addFlash(
                'success_admin',
                $this->translator->trans('termsPoliciesLinksChangesAppliedSuccessfully', [], 'controllers')
            );

            return $this->redirectToRoute('admin_dashboard_settings_terms', ['language' => $language]);
        }

        return $this->render('dashboard/shared/settings_actions.html.twig', [
            'user' => $currentUser,
            'data' => $data,
            'settings' => $settings,
            'current_user' => $currentUser,
            'form' => $form->createView(),
            'language' => $language,
        ]);
    }

    #[Route('/dashboard/settings/LDAP', name: 'admin_dashboard_settings_LDAP')]
    #[IsGranted('ROLE_ADMIN')]
    public function settingsLDAP(Request $request): Response
    {
        $data = $this->getSettings->getSettings();

        /** @var User $currentUser */
        $currentUser = $this->getUser();

        // Initialize DTO from settings
        $dto = new LDAPSettingsDTO($data);

        // Create form bound to DTO
        $form = $this->createForm(LDAPType::class, $dto);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            /** @var LDAPSettingsDTO $dto */
            $dto = $form->getData();

            // Save updated settings
            $this->settingsService->updateSettingsFromArray($dto->toArray());
            $this->settingsService->flush();

            // Log the event
            $this->eventActions->saveEvent(
                $currentUser,
                AnalyticalEventType::SETTING_LDAP_CONF_REQUEST->value,
                new DateTime(),
                [
                    'ip' => $request->getClientIp(),
                    'user_agent' => $request->headers->get('User-Agent'),
                    'uuid' => $currentUser->getUuid(),
                ]
            );

            $this->addFlash(
                'success_admin',
                $this->translator->trans('LDAPConfigurationAppliedSuccessfully', [], 'controllers')
            );

            return $this->redirectToRoute('admin_dashboard_settings_LDAP');
        }

        return $this->render('dashboard/shared/settings_actions.html.twig', [
            'form' => $form->createView(),
            'ldapSettingsDTO' => $dto,
            'data' => $data,
        ]);
    }

    #[Route('/dashboard/settings/radius', name: 'admin_dashboard_settings_radius')]
    #[IsGranted('ROLE_ADMIN')]
    public function settingsRadius(
        Request $request,
    ): Response {
        $data = $this->getSettings->getSettings();

        /** @var User $currentUser */
        $currentUser = $this->getUser();

        // Initialize DTO from settings
        $dto = new RadiusSettingsDTO($data);

        // Create form bound to DTO
        $form = $this->createForm(RadiusType::class, $dto);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            /** @var RadiusSettingsDTO $dto */
            $dto = $form->getData();

            // Save updated settings
            $this->settingsService->updateSettingsFromArray($dto->toArray());
            $this->settingsService->flush();

            // Log the event
            $this->eventActions->saveEvent(
                $currentUser,
                AnalyticalEventType::SETTING_RADIUS_CONF_REQUEST->value,
                new DateTime(),
                [
                    'ip' => $request->getClientIp(),
                    'user_agent' => $request->headers->get('User-Agent'),
                    'uuid' => $currentUser->getUuid(),
                ]
            );

            $this->addFlash(
                'success_admin',
                $this->translator->trans('radiusConfigurationAppliedSuccessfully', [], 'controllers')
            );

            return $this->redirectToRoute('admin_dashboard_settings_radius');
        }

        return $this->render('dashboard/shared/settings_actions.html.twig', [
            'form' => $form->createView(),
            'radiusSettingsDTO' => $dto,
            'data' => $data,
        ]);
    }

    #[Route('/dashboard/settings/status', name: 'admin_dashboard_settings_status')]
    #[IsGranted('ROLE_ADMIN')]
    public function settingsStatus(
        Request $request
    ): Response {
        // Get the current logged-in user (admin)
        /** @var User $currentUser */
        $currentUser = $this->getUser();
        $data = $this->getSettings->getSettings();

        $settingsRepository = $this->entityManager->getRepository(Setting::class);
        $settings = $settingsRepository->findAll();

        $form = $this->createForm(StatusType::class, null, [
            'settings' => $settings,
        ]);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            // Get the submitted data
            $submittedData = $form->getData();

            // Update the 'PLATFORM_MODE', 'USER_VERIFICATION' and 'TURNSTILE_CHECKER' settings
            $platformMode = $submittedData[SettingName::PLATFORM_MODE->value] ?? null;
            $turnstileChecker = $submittedData[SettingName::TURNSTILE_CHECKER->value] ?? null;
            $apiStatus = $submittedData[SettingName::API_STATUS->value] ?? null;
            $userDeleteTime = $submittedData[SettingName::USER_DELETE_TIME->value] ?? 5;
            // Update the 'USER_VERIFICATION', and, if the platform mode is Live, set email verification to ON always
            $emailVerification = ($platformMode === PlatformMode::LIVE->value) ?
                OperationMode::ON->value : $submittedData[SettingName::USER_VERIFICATION->value] ?? null;
            $timeIntervalNotifications = $submittedData[SettingName::TIME_INTERVAL_NOTIFICATION->value] ?? 7;

            $platformModeSetting = $settingsRepository->findOneBy(['name' => SettingName::PLATFORM_MODE->value]);
            if ($platformModeSetting !== null) {
                $platformModeSetting->setValue($platformMode);
                $this->entityManager->persist($platformModeSetting);
            }

            $emailVerificationSetting = $settingsRepository->findOneBy(
                ['name' => SettingName::USER_VERIFICATION->value]
            );
            if ($emailVerificationSetting !== null) {
                $emailVerificationSetting->setValue($emailVerification);
                $this->entityManager->persist($emailVerificationSetting);
            }

            $turnstileCheckerSetting = $settingsRepository->findOneBy([
                'name' => SettingName::TURNSTILE_CHECKER->value
            ]);
            if ($turnstileCheckerSetting !== null) {
                $turnstileCheckerSetting->setValue($turnstileChecker);
                $this->entityManager->persist($turnstileCheckerSetting);
            }

            $apiStatusSetting = $settingsRepository->findOneBy(['name' => SettingName::API_STATUS->value]);
            if ($apiStatusSetting !== null) {
                $apiStatusSetting->setValue($apiStatus);
                $this->entityManager->persist($apiStatusSetting);
            }

            $userDeleteTimeSetting = $settingsRepository->findOneBy(['name' => SettingName::USER_DELETE_TIME->value]);
            if ($userDeleteTimeSetting !== null) {
                $userDeleteTimeSetting->setValue($userDeleteTime);
                $this->entityManager->persist($userDeleteTimeSetting);
            }

            $timeIntervalNotificationsSetting = $settingsRepository->findOneBy([
                'name' => SettingName::TIME_INTERVAL_NOTIFICATION->value
            ]);
            if ($timeIntervalNotificationsSetting !== null) {
                $timeIntervalNotificationsSetting->setValue($timeIntervalNotifications);
                $this->entityManager->persist($timeIntervalNotificationsSetting);
            }
            // Flush the changes to the database
            $this->entityManager->flush();

            $eventMetadata = [
                'ip' => $request->getClientIp(),
                'user_agent' => $request->headers->get('User-Agent'),
                'uuid' => $currentUser->getUuid()
            ];
            $this->eventActions->saveEvent(
                $currentUser,
                AnalyticalEventType::SETTING_PLATFORM_STATUS_REQUEST->value,
                new DateTime(),
                $eventMetadata
            );

            $this->addFlash(
                'success_admin',
                $this->translator->trans('newChangesAppliedSuccessfully', [], 'controllers')
            );
            return $this->redirectToRoute('admin_dashboard_settings_status');
        }


        return $this->render('dashboard/shared/settings_actions.html.twig', [
            'user' => $currentUser,
            'data' => $data,
            'settings' => $settings,
            'current_user' => $currentUser,
            'form' => $form->createView(),
        ]);
    }


    #[Route('/dashboard/settings/twoFA', name: 'admin_dashboard_settings_two_fa')]
    #[IsGranted('ROLE_ADMIN')]
    public function settingsTwoFA(
        Request $request,
    ): Response {
        // Get the current logged-in user (admin)
        /** @var User $currentUser */
        $currentUser = $this->getUser();
        $data = $this->getSettings->getSettings();

        $settingsRepository = $this->entityManager->getRepository(Setting::class);
        $settings = $settingsRepository->findAll();

        $formTwoFA = $this->createForm(TwoFASettingsType::class, null, [
            'settings' => $settings,
        ]);
        $formTwoFA->handleRequest($request);
        if ($formTwoFA->isSubmitted() && $formTwoFA->isValid()) {
            $submittedData = $formTwoFA->getData();

            // List of 2FA settings to handle
            $settingsToHandle = [
                SettingName::TWO_FACTOR_AUTH_STATUS->value,
                SettingName::TWO_FACTOR_AUTH_APP_LABEL->value,
                SettingName::TWO_FACTOR_AUTH_APP_ISSUER->value,
                SettingName::TWO_FACTOR_AUTH_CODE_EXPIRATION_TIME->value,
                SettingName::TWO_FACTOR_AUTH_ATTEMPTS_NUMBER_RESEND_CODE->value,
                SettingName::TWO_FACTOR_AUTH_TIME_RESET_ATTEMPTS->value,
                SettingName::TWO_FACTOR_AUTH_RESEND_INTERVAL->value
            ];

            foreach ($settingsToHandle as $settingName) {
                $settingValue = $submittedData[$settingName] ?? '';
                $setting = $settingsRepository->findOneBy(['name' => $settingName]);
                if ($setting !== null) {
                    // Update existing setting
                    $setting->setValue($settingValue);
                }

                $this->entityManager->persist($setting);
            }
            $this->entityManager->flush();

            $eventMetadata = [
                'ip' => $request->getClientIp(),
                'user_agent' => $request->headers->get('User-Agent'),
                'uuid' => $currentUser->getUuid()
            ];
            $this->eventActions->saveEvent(
                $currentUser,
                AnalyticalEventType::SETTING_PLATFORM_2FA_REQUEST->value,
                new DateTime(),
                $eventMetadata
            );

            $this->addFlash(
                'success_admin',
                $this->translator->trans('newChangesAppliedSuccessfully', [], 'controllers')
            );
            return $this->redirectToRoute('admin_dashboard_settings_two_fa');
        }

        return $this->render('dashboard/shared/settings_actions.html.twig', [
            'user' => $currentUser,
            'data' => $data,
            'settings' => $settings,
            'current_user' => $currentUser,
            'formTwoFA' => $formTwoFA->createView(),
        ]);
    }

    #[Route(
        '/dashboard/settings/auth/{language}',
        name: 'admin_dashboard_settings_auth',
        defaults: ['language' => LanguageType::EN->value]
    )]
    #[IsGranted('ROLE_ADMIN')]
    public function settingsAuths(
        Request $request,
        CertificateService $certificateService,
        string $language
    ): Response {
        $missingFiles = $this->certificateService->verifyCertificates();
        if ($missingFiles !== []) {
            throw new HttpException(
                424,
                $this->translator->trans('certFilesMissing', [], 'controllers') .
                implode(', ', $missingFiles)
            );
        }

        // Get the current logged-in user (admin)
        /** @var User $currentUser */
        $currentUser = $this->getUser();
        $data = $this->getSettings->getSettings($language);

        $settingsRepository = $this->entityManager->getRepository(Setting::class);
        $settings = $settingsRepository->findAll();

        $certificatePath = $this->getParameter('kernel.project_dir') . '/signing-keys/cert.pem';
        $certificateLimitDate = strtotime((string)$certificateService->getCertificateExpirationDate($certificatePath));
        $realTime = time();
        $timeLeft = round(($certificateLimitDate - $realTime) / (86400)) - 1;
        $profileLimitDate = ((int)$timeLeft);
        if ($profileLimitDate < 0) {
            $profileLimitDate = 0;
        }

        $defaultTimeZone = date_default_timezone_get();
        $dateTime = new DateTime()
            ->setTimestamp($certificateLimitDate)
            ->setTimezone(new DateTimeZone($defaultTimeZone));

        // Convert to human-readable format
        $humanReadableExpirationDate = $dateTime->format('Y-m-d H:i:s T');

        // Get the settings value according to the language
        $settingsTranslated = $this->getSettings->getSettingsByLocale($settings, $data);

        $form = $this->createForm(AuthType::class, null, [
            'settings' => $settingsTranslated,
            'profileLimitDate' => $profileLimitDate,
            'humanReadableExpirationDate' => $humanReadableExpirationDate
        ]);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $submittedData = $form->getData();

            $settingsToUpdate = [
                SettingName::AUTH_METHOD_SAML_ENABLED->value,
                SettingName::AUTH_METHOD_SAML_LABEL->value,
                SettingName::AUTH_METHOD_SAML_DESCRIPTION->value,

                SettingName::AUTH_METHOD_GOOGLE_LOGIN_ENABLED->value,
                SettingName::AUTH_METHOD_GOOGLE_LOGIN_LABEL->value,
                SettingName::AUTH_METHOD_GOOGLE_LOGIN_DESCRIPTION->value,
                SettingName::VALID_DOMAINS_GOOGLE_LOGIN->value,
                SettingName::PROFILE_LIMIT_DATE_GOOGLE->value,

                SettingName::AUTH_METHOD_MICROSOFT_LOGIN_ENABLED->value,
                SettingName::AUTH_METHOD_MICROSOFT_LOGIN_LABEL->value,
                SettingName::AUTH_METHOD_MICROSOFT_LOGIN_DESCRIPTION->value,
                SettingName::VALID_DOMAINS_MICROSOFT_LOGIN->value,
                SettingName::PROFILE_LIMIT_DATE_MICROSOFT->value,

                SettingName::AUTH_METHOD_REGISTER_ENABLED->value,
                SettingName::AUTH_METHOD_REGISTER_LABEL->value,
                SettingName::AUTH_METHOD_REGISTER_DESCRIPTION->value,
                SettingName::PROFILE_LIMIT_DATE_EMAIL->value,

                SettingName::EMAIL_TIMER_RESEND->value,
                SettingName::LINK_VALIDITY->value,

                SettingName::AUTH_METHOD_LOGIN_TRADITIONAL_ENABLED->value,
                SettingName::AUTH_METHOD_LOGIN_TRADITIONAL_LABEL->value,
                SettingName::AUTH_METHOD_LOGIN_TRADITIONAL_DESCRIPTION->value,
                SettingName::LOGIN_WITH_UUID_ONLY->value,

                SettingName::AUTH_METHOD_SMS_REGISTER_ENABLED->value,
                SettingName::AUTH_METHOD_SMS_REGISTER_LABEL->value,
                SettingName::AUTH_METHOD_SMS_REGISTER_DESCRIPTION->value,
                SettingName::PROFILE_LIMIT_DATE_SMS->value,
            ];

            $labelsFields = [
                SettingName::AUTH_METHOD_SAML_LABEL->value,
                SettingName::AUTH_METHOD_GOOGLE_LOGIN_LABEL->value,
                SettingName::AUTH_METHOD_MICROSOFT_LOGIN_LABEL->value,
                SettingName::AUTH_METHOD_REGISTER_LABEL->value,
                SettingName::AUTH_METHOD_LOGIN_TRADITIONAL_LABEL->value,
                SettingName::AUTH_METHOD_SMS_REGISTER_LABEL->value,
            ];

            $descriptionsFields = [
                SettingName::AUTH_METHOD_SAML_DESCRIPTION->value,
                SettingName::AUTH_METHOD_GOOGLE_LOGIN_DESCRIPTION->value,
                SettingName::AUTH_METHOD_MICROSOFT_LOGIN_DESCRIPTION->value,
                SettingName::AUTH_METHOD_REGISTER_DESCRIPTION->value,
                SettingName::AUTH_METHOD_LOGIN_TRADITIONAL_DESCRIPTION->value,
                SettingName::AUTH_METHOD_SMS_REGISTER_DESCRIPTION->value,
            ];

            foreach ($settingsToUpdate as $settingName) {
                $value = $submittedData[$settingName] ?? null;

                if (in_array($settingName, $this->getSettings->arraySettingsToTranslate())) {
                    $locale = $language;
                    $submittedValue = $submittedData[$settingName];
                    // Get the translated setting
                    $setting = $settingsRepository->findOneBy(['name' => $settingName]);
                    $settingTranslation = $this->settingTranslationRepository->findOneBy(
                        ['setting' => $setting, 'locale' => $locale]
                    );
                    if (in_array($settingName, $descriptionsFields) && $submittedValue === null) {
                        $settingTranslation?->setTranslation('');
                    } else {
                        $settingTranslation?->setTranslation($submittedValue);
                    }
                }

                // Check if the setting is a label, to be impossible to set it null of empty
                if (($value === null || $value === "") && in_array($settingName, $labelsFields)) {
                    continue;
                }

                $setting = $settingsRepository->findOneBy(['name' => $settingName]);

                if ($setting !== null) {
                    $setting->setValue($value);
                    $this->entityManager->persist($setting);
                }

                if (
                    $settingName === SettingName::LOGIN_WITH_UUID_ONLY->value &&
                    $setting &&
                    ($setting->getValue() === OperationMode::OFF->value && $value === OperationMode::OFF->value)
                ) {
                    // Set every portal account with a password reset action, to require everyone to use a new password
                    $this->enforcePasswordResetService->enforceReset(
                        $currentUser,
                        $request->getClientIp(),
                        $request->headers->get('User-Agent')
                    );
                }

                if (
                    $settingName === SettingName::VALID_DOMAINS_GOOGLE_LOGIN->value ||
                    $settingName === SettingName::VALID_DOMAINS_MICROSOFT_LOGIN->value
                ) {
                    continue;
                }
            }

            $eventMetadata = [
                'ip' => $request->getClientIp(),
                'user_agent' => $request->headers->get('User-Agent'),
                'uuid' => $currentUser->getUuid(),
            ];

            $this->eventActions->saveEvent(
                $currentUser,
                AnalyticalEventType::SETTING_AUTHS_CONF_REQUEST->value,
                new DateTime(),
                $eventMetadata
            );
            $this->addFlash(
                'success_admin',
                $this->translator->trans('authenticationConfigurationAppliedSuccessfully', [], 'controllers')
            );
            return $this->redirectToRoute('admin_dashboard_settings_auth', ['language' => $language]);
        }

        return $this->render('dashboard/shared/settings_actions.html.twig', [
            'user' => $currentUser,
            'data' => $data,
            'settings' => $settingsTranslated,
            'current_user' => $currentUser,
            'form' => $form->createView(),
            'profileLimitDate' => $profileLimitDate,
            'humanReadableExpirationDate' => $humanReadableExpirationDate,
            'language' => $language,
        ]);
    }

    #[Route('/dashboard/settings/capport', name: 'admin_dashboard_settings_capport')]
    #[IsGranted('ROLE_ADMIN')]
    public function settingsCAPPORT(
        Request $request,
    ): Response {
        $data = $this->getSettings->getSettings();
        // Get the current logged-in user (admin)
        /** @var User $currentUser */
        $currentUser = $this->getUser();

        $settingsRepository = $this->entityManager->getRepository(Setting::class);
        $settings = $settingsRepository->findAll();

        $form = $this->createForm(CapportType::class, null, [
            'settings' => $settings,
        ]);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $submittedData = $form->getData();

            $settingsToUpdate = [
                SettingName::CAPPORT_ENABLED->value,
                SettingName::CAPPORT_PORTAL_URL->value,
                SettingName::CAPPORT_VENUE_INFO_URL->value,
            ];

            foreach ($settingsToUpdate as $settingName) {
                $value = $submittedData[$settingName] ?? null;

                // Check if any submitted data is empty
                if ($value === null) {
                    $value = "";
                }

                $setting = $settingsRepository->findOneBy(['name' => $settingName]);
                if ($setting !== null) {
                    $setting->setValue($value);
                    $this->entityManager->persist($setting);
                }
            }

            $eventMetadata = [
                'ip' => $request->getClientIp(),
                'user_agent' => $request->headers->get('User-Agent'),
                'uuid' => $currentUser->getUuid(),
            ];
            $this->eventActions->saveEvent(
                $currentUser,
                AnalyticalEventType::SETTING_CAPPORT_CONF_REQUEST->value,
                new DateTime(),
                $eventMetadata
            );

            $this->addFlash(
                'success_admin',
                $this->translator->trans('CAPPORTConfigurationAppliedSuccessfully', [], 'controllers')
            );
            return $this->redirectToRoute('admin_dashboard_settings_capport');
        }

        return $this->render('dashboard/shared/settings_actions.html.twig', [
            'user' => $currentUser,
            'data' => $data,
            'settings' => $settings,
            'form' => $form->createView()
        ]);
    }

    #[Route('/dashboard/settings/sms', name: 'admin_dashboard_settings_sms')]
    #[IsGranted('ROLE_ADMIN')]
    public function settingsSMS(
        Request $request
    ): Response {
        // Get the current logged-in user (admin)
        /** @var User $currentUser */
        $currentUser = $this->getUser();
        $data = $this->getSettings->getSettings();

        $settingsRepository = $this->entityManager->getRepository(Setting::class);
        $settings = $settingsRepository->findAll();

        $form = $this->createForm(SMSType::class, null, [
            'settings' => $settings,
        ]);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $submittedData = $form->getData();

            $settingsToUpdate = [
                SettingName::SMS_USERNAME->value,
                SettingName::SMS_USER_ID->value,
                SettingName::SMS_HANDLE->value,
                SettingName::SMS_FROM->value,
                SettingName::SMS_TIMER_RESEND->value,
                SettingName::DEFAULT_REGION_PHONE_INPUTS->value
            ];

            foreach ($settingsToUpdate as $settingName) {
                $value = $submittedData[$settingName] ?? null;

                // Check if any submitted data is empty
                if ($value === null) {
                    $value = "";
                }

                $setting = $settingsRepository->findOneBy(['name' => $settingName]);
                if ($setting !== null) {
                    $setting->setValue($value);
                    $this->entityManager->persist($setting);
                }
            }

            $eventMetadata = [
                'ip' => $request->getClientIp(),
                'user_agent' => $request->headers->get('User-Agent'),
                'uuid' => $currentUser->getUuid(),
            ];
            $this->eventActions->saveEvent(
                $currentUser,
                AnalyticalEventType::SETTING_SMS_CONF_REQUEST->value,
                new DateTime(),
                $eventMetadata
            );

            $this->addFlash(
                'success_admin',
                $this->translator->trans('SMSConfigurationAppliedSuccessfully', [], 'controllers')
            );
            return $this->redirectToRoute('admin_dashboard_settings_sms');
        }

        return $this->render('dashboard/shared/settings_actions.html.twig', [
            'user' => $currentUser,
            'data' => $data,
            'settings' => $settings,
            'current_user' => $currentUser,
            'form' => $form->createView(),
        ]);
    }
}
