<?php

namespace App\Controller;

use App\Entity\Setting;
use App\Entity\TextEditor;
use App\Entity\User;
use App\Enum\AnalyticalEventType;
use App\Enum\OperationMode;
use App\Enum\PlatformMode;
use App\Enum\TextEditorName;
use App\Form\AuthType;
use App\Form\CapportType;
use App\Form\LDAPType;
use App\Form\RadiusType;
use App\Form\SMSType;
use App\Form\StatusType;
use App\Form\TermsType;
use App\Form\TwoFASettingsType;
use App\RadiusDb\Repository\RadiusAccountingRepository;
use App\RadiusDb\Repository\RadiusAuthsRepository;
use App\Repository\SettingRepository;
use App\Repository\UserRepository;
use App\Service\CertificateService;
use App\Service\Domain;
use App\Service\EventActions;
use App\Service\GetSettings;
use App\Service\SanitizeHTML;
use App\Service\Statistics;
use DateTime;
use DateTimeZone;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpClient\Exception\JsonException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

class SettingsController extends AbstractController
{
    public function __construct(
        private readonly EventActions $eventActions,
        private readonly GetSettings $getSettings,
        private readonly SettingRepository $settingRepository,
        private readonly UserRepository $userRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly RadiusAuthsRepository $radiusAuthsRepository,
        private readonly RadiusAccountingRepository $radiusAccountingRepository
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
        RequestStack $requestStack,
        Request $request,
        string $type
    ): Response {
        // Get the entered code from the form
        $enteredCode = $requestStack->getCurrentRequest()->request->get('code');
        /** @var User $currentUser */
        $currentUser = $this->getUser();
        if ($enteredCode === $currentUser->getVerificationCode()) {
            if ($type === 'settingCustom') {
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
                $this->addFlash('success_admin', 'The setting has been reset successfully!');
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

            if ($type === 'settingTerms') {
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
                $this->addFlash('success_admin', 'The terms and policies settings has been reset successfully!');

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

            if ($type === 'settingRadius') {
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
                $this->addFlash('success_admin', 'The Radius configurations has been reset successfully!');

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


            if ($type === 'settingLDAP') {
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
                $this->addFlash('success_admin', 'The LDAP settings has been reset successfully!');

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

            if ($type === 'settingStatus') {
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
                $this->addFlash('success_admin', 'The platform mode status has been reset successfully!');

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

            if ($type === 'settingCAPPORT') {
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
                $this->addFlash('success_admin', 'The CAPPORT settings has been reset successfully!');

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

            if ($type === 'settingAUTH') {
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
                $this->addFlash('success_admin', 'The authentication settings has been reset successfully!');

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

            if ($type === 'settingTwoAF') {
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
                $this->addFlash('success_admin', 'The Two Factor settings has been reset successfully!');

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

            if ($type === 'settingSMS') {
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
                    'The configuration SMS settings has been clear successfully!'
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
        }

        $this->addFlash('error_admin', 'The verification code is incorrect. Please try again.');
        return $this->redirectToRoute('admin_confirm_reset', ['type' => $type]);
    }

    #[Route('/dashboard/settings/terms', name: 'admin_dashboard_settings_terms')]
    #[IsGranted('ROLE_ADMIN')]
    public function settingsTerms(
        Request $request,
        GetSettings $getSettings
    ): Response {
        // Get the current logged-in user (admin)
        /** @var User $currentUser */
        $currentUser = $this->getUser();

        $textEditorRepository = $this->entityManager->getRepository(TextEditor::class);
        $tosTextEditor = $textEditorRepository->findOneBy(['name' => TextEditorName::TOS->value]);
        if ($tosTextEditor === null) {
            $tosTextEditor = new TextEditor();
            $tosTextEditor->setName(TextEditorName::TOS->value);
            $tosTextEditor->setContent('');
            $this->entityManager->persist($tosTextEditor);
        }
        $privacyPolicyTextEditor = $textEditorRepository->findoneBy(['name' => TextEditorName::PRIVACY_POLICY->value]);
        if ($privacyPolicyTextEditor === null) {
            $privacyPolicyTextEditor = new TextEditor();
            $privacyPolicyTextEditor->setName(TextEditorName::PRIVACY_POLICY->value);
            $privacyPolicyTextEditor->setContent('');
            $this->entityManager->persist($privacyPolicyTextEditor);
        }
        $this->entityManager->flush();

        $data = $this->getSettings->getSettings($this->userRepository, $this->settingRepository);

        $settingsRepository = $this->entityManager->getRepository(Setting::class);
        $settings = $settingsRepository->findAll();

        foreach ($settings as $setting) {
            if ($setting->getName() === 'TOS_EDITOR' || $setting->getName() === 'PRIVACY_POLICY_EDITOR') {
                $this->entityManager->remove($setting);
                $this->entityManager->flush();
            }
        }

        $tosTextEditorSetting = new Setting();
        $tosTextEditorSetting->setName('TOS_EDITOR');
        $tosTextEditorSetting->setValue($tosTextEditor->getContent());
        $privacyPolicyTextEditorSetting = new Setting();
        $privacyPolicyTextEditorSetting->setName('PRIVACY_POLICY_EDITOR');
        $privacyPolicyTextEditorSetting->setValue($privacyPolicyTextEditor->getContent());

        $settings = array_merge($settings, [$tosTextEditorSetting, $privacyPolicyTextEditorSetting]);

        $form = $this->createForm(TermsType::class, null, [
            'settings' => $settings,
        ]);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Get the submitted data
            $submittedData = $form->getData();

            // Update settings
            $tos = $submittedData['TOS'];
            $privacyPolicy = $submittedData['PRIVACY_POLICY'];
            $tosLink = $submittedData['TOS_LINK'] ?? null;
            $privacyPolicyLink = $submittedData['PRIVACY_POLICY_LINK'] ?? null;
            $tosTextEditor = $submittedData['TOS_EDITOR'] ?? '';
            $privacyPolicyTextEditor = $submittedData['PRIVACY_POLICY_EDITOR'] ?? '';


            $tosSetting = $settingsRepository->findOneBy(['name' => 'TOS']);
            if ($tosSetting !== null) {
                $tosSetting->setValue($tos);
                $this->entityManager->persist($tosSetting);
            }

            $privacyPolicySetting = $settingsRepository->findOneBy(['name' => 'PRIVACY_POLICY']);
            if ($privacyPolicySetting !== null) {
                $privacyPolicySetting->setValue($privacyPolicy);
                $this->entityManager->persist($privacyPolicySetting);
            }

            $tosLinkSetting = $settingsRepository->findOneBy(['name' => 'TOS_LINK']);
            if ($tosLinkSetting !== null) {
                $tosLinkSetting->setValue($tosLink);
                $this->entityManager->persist($tosLinkSetting);
            }

            $privacyPolicyLinkSetting = $settingsRepository->findOneBy(['name' => 'PRIVACY_POLICY_LINK']);
            if ($privacyPolicyLinkSetting !== null) {
                $privacyPolicyLinkSetting->setValue($privacyPolicyLink);
                $this->entityManager->persist($privacyPolicyLinkSetting);
            }
            $sanitizeHtml = new SanitizeHTML();
            if ($tosTextEditor) {
                $tosEditorSetting = $textEditorRepository->findOneBy(['name' => TextEditorName::TOS->value]);
                if ($tosEditorSetting !== null) {
                    $cleanHTML = $sanitizeHtml->sanitizeHtml($tosTextEditor);
                    $tosEditorSetting->setContent($cleanHTML);
                }
                $this->entityManager->persist($tosEditorSetting);
            }

            if ($privacyPolicyTextEditor) {
                $privacyPolicyEditorSetting = $textEditorRepository->findOneBy([
                    'name' => TextEditorName::PRIVACY_POLICY->value
                ]);
                if ($privacyPolicyEditorSetting !== null) {
                    $cleanHTML = $sanitizeHtml->sanitizeHtml($privacyPolicyTextEditor);
                    $privacyPolicyEditorSetting->setContent($cleanHTML);
                }
                $this->entityManager->persist($privacyPolicyEditorSetting);
            }
            $eventMetadata = [
                'ip' => $request->getClientIp(),
                'user_agent' => $request->headers->get('User-Agent'),
                'uuid' => $currentUser->getUuid(),
            ];
            $this->eventActions->saveEvent(
                $currentUser,
                AnalyticalEventType::SETTING_TERMS_REQUEST->value,
                new DateTime(),
                $eventMetadata
            );


            $this->entityManager->flush();
            $this->addFlash('success_admin', 'Terms and Policies links changes have been applied successfully.');
            return $this->redirectToRoute('admin_dashboard_settings_terms');
        }


        return $this->render('admin/settings_actions.html.twig', [
            'user' => $currentUser,
            'data' => $data,
            'settings' => $settings,
            'getSettings' => $getSettings,
            'current_user' => $currentUser,
            'form' => $form->createView(),
        ]);
    }


    #[Route('/dashboard/settings/LDAP', name: 'admin_dashboard_settings_LDAP')]
    #[IsGranted('ROLE_ADMIN')]
    public function settingsLDAP(
        Request $request,
        EntityManagerInterface $em,
        GetSettings $getSettings
    ): Response {
        $data = $this->getSettings->getSettings($this->userRepository, $this->settingRepository);
        // Get the current logged-in user (admin)
        /** @var User $currentUser */
        $currentUser = $this->getUser();

        $settingsRepository = $em->getRepository(Setting::class);
        $settings = $settingsRepository->findAll();

        $form = $this->createForm(LDAPType::class, null, [
            'settings' => $settings,
        ]);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $submittedData = $form->getData();

            $settingsToUpdate = [
                'SYNC_LDAP_ENABLED',
                'SYNC_LDAP_SERVER',
                'SYNC_LDAP_BIND_USER_DN',
                'SYNC_LDAP_BIND_USER_PASSWORD',
                'SYNC_LDAP_SEARCH_BASE_DN',
                'SYNC_LDAP_SEARCH_FILTER',
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
                    $em->persist($setting);
                }
            }

            $eventMetadata = [
                'ip' => $request->getClientIp(),
                'user_agent' => $request->headers->get('User-Agent'),
                'uuid' => $currentUser->getUuid(),
            ];
            $this->eventActions->saveEvent(
                $currentUser,
                AnalyticalEventType::SETTING_LDAP_CONF_REQUEST->value,
                new DateTime(),
                $eventMetadata
            );


            $this->addFlash('success_admin', 'New LDAP configuration have been applied successfully.');
            return $this->redirectToRoute('admin_dashboard_settings_LDAP');
        }

        return $this->render('admin/settings_actions.html.twig', [
            'data' => $data,
            'settings' => $settings,
            'getSettings' => $getSettings,
            'form' => $form->createView()
        ]);
    }

    #[Route('/dashboard/settings/radius', name: 'admin_dashboard_settings_radius')]
    #[IsGranted('ROLE_ADMIN')]
    public function settingsRadius(
        Request $request,
        GetSettings $getSettings
    ): Response {
        $data = $getSettings->getSettings($this->userRepository, $this->settingRepository);
        // Get the current logged-in user (admin)
        /** @var User $currentUser */
        $currentUser = $this->getUser();
        $domainService = new Domain();
        $settingsRepository = $this->entityManager->getRepository(Setting::class);
        $settings = $settingsRepository->findAll();

        $form = $this->createForm(RadiusType::class, null, [
            'settings' => $settings,
        ]);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $submittedData = $form->getData();

            $staticValue = '887FAE2A-F051-4CC9-99BB-8DFD66F553A9';
            if ($submittedData['PAYLOAD_IDENTIFIER'] === $staticValue) {
                $this->addFlash('error_admin', 'Please do not use the default value from the Payload Identifier card.');
            } else {
                $settingsToUpdate = [
                    'RADIUS_REALM_NAME',
                    'DISPLAY_NAME',
                    'PAYLOAD_IDENTIFIER',
                    'OPERATOR_NAME',
                    'DOMAIN_NAME',
                    'RADIUS_TLS_NAME',
                    'NAI_REALM',
                    'RADIUS_TRUSTED_ROOT_CA_SHA1_HASH',
                    'PROFILES_ENCRYPTION_TYPE_IOS_ONLY',
                ];

                foreach ($settingsToUpdate as $settingName) {
                    $value = $submittedData[$settingName] ?? null;

                    // Check for specific settings that need domain validation
                    if (
                        in_array(
                            $settingName,
                            [
                                'RADIUS_REALM_NAME',
                                'DOMAIN_NAME',
                                'RADIUS_TLS_NAME',
                                'NAI_REALM'
                            ]
                        ) && !$domainService->isValidDomain($value)
                    ) {
                        $this->addFlash(
                            'error_admin',
                            "The value for $settingName is not a valid domain or does not resolve to an IP address."
                        );
                        return $this->redirectToRoute('admin_dashboard_settings_radius');
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
                    AnalyticalEventType::SETTING_RADIUS_CONF_REQUEST->value,
                    new DateTime(),
                    $eventMetadata
                );

                $this->addFlash('success_admin', 'Radius configuration have been applied successfully.');
                return $this->redirectToRoute('admin_dashboard_settings_radius');
            }
        }

        return $this->render('admin/settings_actions.html.twig', [
            'user' => $currentUser,
            'data' => $data,
            'settings' => $settings,
            'getSettings' => $getSettings,
            'form' => $form->createView()
        ]);
    }

    #[Route('/dashboard/settings/status', name: 'admin_dashboard_settings_status')]
    #[IsGranted('ROLE_ADMIN')]
    public function settingsStatus(
        Request $request,
        GetSettings $getSettings
    ): Response {
        // Get the current logged-in user (admin)
        /** @var User $currentUser */
        $currentUser = $this->getUser();
        $data = $this->getSettings->getSettings($this->userRepository, $this->settingRepository);

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
            $platformMode = $submittedData['PLATFORM_MODE'] ?? null;
            $turnstileChecker = $submittedData['TURNSTILE_CHECKER'] ?? null;
            $apiStatus = $submittedData['API_STATUS'] ?? null;
            $userDeleteTime = $submittedData['USER_DELETE_TIME'] ?? 5;
            // Update the 'USER_VERIFICATION', and, if the platform mode is Live, set email verification to ON always
            $emailVerification = ($platformMode === PlatformMode::LIVE->value) ?
                OperationMode::ON->value : $submittedData['USER_VERIFICATION'] ?? null;
            $timeIntervalNotifications = $submittedData['TIME_INTERVAL_NOTIFICATION'] ?? 7;

            $platformModeSetting = $settingsRepository->findOneBy(['name' => 'PLATFORM_MODE']);
            if ($platformModeSetting !== null) {
                $platformModeSetting->setValue($platformMode);
                $this->entityManager->persist($platformModeSetting);
            }

            $emailVerificationSetting = $settingsRepository->findOneBy(['name' => 'USER_VERIFICATION']);
            if ($emailVerificationSetting !== null) {
                $emailVerificationSetting->setValue($emailVerification);
                $this->entityManager->persist($emailVerificationSetting);
            }

            $turnstileCheckerSetting = $settingsRepository->findOneBy(['name' => 'TURNSTILE_CHECKER']);
            if ($turnstileCheckerSetting !== null) {
                $turnstileCheckerSetting->setValue($turnstileChecker);
                $this->entityManager->persist($turnstileCheckerSetting);
            }

            $apiStatusSetting = $settingsRepository->findOneBy(['name' => 'API_STATUS']);
            if ($apiStatusSetting !== null) {
                $apiStatusSetting->setValue($apiStatus);
                $this->entityManager->persist($apiStatusSetting);
            }

            $userDeleteTimeSetting = $settingsRepository->findOneBy(['name' => 'USER_DELETE_TIME']);
            if ($userDeleteTimeSetting !== null) {
                $userDeleteTimeSetting->setValue($userDeleteTime);
                $this->entityManager->persist($userDeleteTimeSetting);
            }

            $timeIntervalNotificationsSetting = $settingsRepository->findOneBy([
                'name' => 'TIME_INTERVAL_NOTIFICATION'
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

            $this->addFlash('success_admin', 'The new changes have been applied successfully.');
            return $this->redirectToRoute('admin_dashboard_settings_status');
        }


        return $this->render('admin/settings_actions.html.twig', [
            'user' => $currentUser,
            'data' => $data,
            'settings' => $settings,
            'getSettings' => $getSettings,
            'current_user' => $currentUser,
            'form' => $form->createView(),
        ]);
    }


    #[Route('/dashboard/settings/twoFA', name: 'admin_dashboard_settings_two_fa')]
    #[IsGranted('ROLE_ADMIN')]
    public function settingsTwoFA(
        Request $request,
        GetSettings $getSettings
    ): Response {
        // Get the current logged-in user (admin)
        /** @var User $currentUser */
        $currentUser = $this->getUser();
        $data = $this->getSettings->getSettings($this->userRepository, $this->settingRepository);

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
                'TWO_FACTOR_AUTH_STATUS',
                'TWO_FACTOR_AUTH_APP_LABEL',
                'TWO_FACTOR_AUTH_APP_ISSUER',
                'TWO_FACTOR_AUTH_CODE_EXPIRATION_TIME',
                'TWO_FACTOR_AUTH_ATTEMPTS_NUMBER_RESEND_CODE',
                'TWO_FACTOR_AUTH_TIME_RESET_ATTEMPTS',
                'TWO_FACTOR_AUTH_RESEND_INTERVAL'
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

            $this->addFlash('success_admin', 'The new changes have been applied successfully.');
            return $this->redirectToRoute('admin_dashboard_settings_two_fa');
        }

        return $this->render('admin/settings_actions.html.twig', [
            'user' => $currentUser,
            'data' => $data,
            'settings' => $settings,
            'getSettings' => $getSettings,
            'current_user' => $currentUser,
            'formTwoFA' => $formTwoFA->createView(),
        ]);
    }

    #[Route('/dashboard/settings/auth', name: 'admin_dashboard_settings_auth')]
    #[IsGranted('ROLE_ADMIN')]
    public function settingsAuths(
        Request $request,
        GetSettings $getSettings,
        CertificateService $certificateService
    ): Response {
        // Get the current logged-in user (admin)
        /** @var User $currentUser */
        $currentUser = $this->getUser();
        $data = $this->getSettings->getSettings($this->userRepository, $this->settingRepository);

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
        $form = $this->createForm(AuthType::class, null, [
            'settings' => $settings,
            'profileLimitDate' => $profileLimitDate,
            'humanReadableExpirationDate' => $humanReadableExpirationDate
        ]);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $submittedData = $form->getData();

            $settingsToUpdate = [
                'AUTH_METHOD_SAML_ENABLED',

                'AUTH_METHOD_GOOGLE_LOGIN_ENABLED',
                'AUTH_METHOD_GOOGLE_LOGIN_LABEL',
                'AUTH_METHOD_GOOGLE_LOGIN_DESCRIPTION',
                'VALID_DOMAINS_GOOGLE_LOGIN',
                'PROFILE_LIMIT_DATE_GOOGLE',

                'AUTH_METHOD_MICROSOFT_LOGIN_ENABLED',
                'AUTH_METHOD_MICROSOFT_LOGIN_LABEL',
                'AUTH_METHOD_MICROSOFT_LOGIN_DESCRIPTION',
                'VALID_DOMAINS_MICROSOFT_LOGIN',
                'PROFILE_LIMIT_DATE_MICROSOFT',

                'AUTH_METHOD_REGISTER_ENABLED',
                'AUTH_METHOD_REGISTER_LABEL',
                'AUTH_METHOD_REGISTER_DESCRIPTION',
                'PROFILE_LIMIT_DATE_EMAIL',

                'AUTH_METHOD_LOGIN_TRADITIONAL_ENABLED',
                'AUTH_METHOD_LOGIN_TRADITIONAL_LABEL',
                'AUTH_METHOD_LOGIN_TRADITIONAL_DESCRIPTION',

                'AUTH_METHOD_SMS_REGISTER_ENABLED',
                'AUTH_METHOD_SMS_REGISTER_LABEL',
                'AUTH_METHOD_SMS_REGISTER_DESCRIPTION',
                'PROFILE_LIMIT_DATE_SMS',
            ];

            $labelsFields = [
                'AUTH_METHOD_SAML_LABEL',
                'AUTH_METHOD_GOOGLE_LOGIN_LABEL',
                'AUTH_METHOD_MICROSOFT_LOGIN_LABEL',
                'AUTH_METHOD_REGISTER_LABEL',
                'AUTH_METHOD_LOGIN_TRADITIONAL_LABEL',
                'AUTH_METHOD_SMS_REGISTER_LABEL',
            ];

            foreach ($settingsToUpdate as $settingName) {
                $value = $submittedData[$settingName] ?? null;

                // Check if the setting is a label, to be impossible to set it null of empty
                if (($value === null || $value === "") && in_array($settingName, $labelsFields)) {
                    continue;
                }

                $setting = $settingsRepository->findOneBy(['name' => $settingName]);
                if ($setting !== null) {
                    $setting->setValue($value);
                    $this->entityManager->persist($setting);
                }
                if ($settingName === 'VALID_DOMAINS_GOOGLE_LOGIN' || $settingName === 'VALID_DOMAINS_MICROSOFT_LOGIN') {
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
            $this->addFlash('success_admin', 'New authentication configuration have been applied successfully.');
            return $this->redirectToRoute('admin_dashboard_settings_auth');
        }

        return $this->render('admin/settings_actions.html.twig', [
            'user' => $currentUser,
            'data' => $data,
            'settings' => $settings,
            'getSettings' => $getSettings,
            'current_user' => $currentUser,
            'form' => $form->createView(),
            'profileLimitDate' => $profileLimitDate,
            'humanReadableExpirationDate' => $humanReadableExpirationDate
        ]);
    }

    #[Route('/dashboard/settings/capport', name: 'admin_dashboard_settings_capport')]
    #[IsGranted('ROLE_ADMIN')]
    public function settingsCAPPORT(
        Request $request,
        GetSettings $getSettings
    ): Response {
        $data = $this->getSettings->getSettings($this->userRepository, $this->settingRepository);
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
                'CAPPORT_ENABLED',
                'CAPPORT_PORTAL_URL',
                'CAPPORT_VENUE_INFO_URL',
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

            $this->addFlash('success_admin', 'New CAPPORT configuration have been applied successfully.');
            return $this->redirectToRoute('admin_dashboard_settings_capport');
        }

        return $this->render('admin/settings_actions.html.twig', [
            'user' => $currentUser,
            'data' => $data,
            'settings' => $settings,
            'getSettings' => $getSettings,
            'form' => $form->createView()
        ]);
    }

    #[Route('/dashboard/settings/sms', name: 'admin_dashboard_settings_sms')]
    #[IsGranted('ROLE_ADMIN')]
    public function settingsSMS(
        Request $request,
        GetSettings $getSettings
    ): Response {
        // Get the current logged-in user (admin)
        /** @var User $currentUser */
        $currentUser = $this->getUser();
        $data = $this->getSettings->getSettings($this->userRepository, $this->settingRepository);

        $settingsRepository = $this->entityManager->getRepository(Setting::class);
        $settings = $settingsRepository->findAll();

        $form = $this->createForm(SMSType::class, null, [
            'settings' => $settings,
        ]);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $submittedData = $form->getData();

            $settingsToUpdate = [
                'SMS_USERNAME',
                'SMS_USER_ID',
                'SMS_HANDLE',
                'SMS_FROM',
                'SMS_TIMER_RESEND',
                'DEFAULT_REGION_PHONE_INPUTS'
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

            $this->addFlash('success_admin', 'New SMS configuration have been applied successfully.');
            return $this->redirectToRoute('admin_dashboard_settings_sms');
        }

        return $this->render('admin/settings_actions.html.twig', [
            'user' => $currentUser,
            'data' => $data,
            'settings' => $settings,
            'getSettings' => $getSettings,
            'current_user' => $currentUser,
            'form' => $form->createView(),
        ]);
    }

    /**
     * Render Statistics about the Portal data
     */
    /**
     * @throws JsonException
     * @throws Exception
     */
    #[Route('/dashboard/statistics', name: 'admin_dashboard_statistics')]
    #[IsGranted('ROLE_ADMIN')]
    public function statisticsData(Request $request): Response
    {
        $data = $this->getSettings->getSettings($this->userRepository, $this->settingRepository);

        /** @var User $currentUser */
        $currentUser = $this->getUser();

        // Get the submitted start and end dates from the form
        $startDateString = $request->request->get('startDate');
        $endDateString = $request->request->get('endDate');

        // Convert the date strings to DateTime objects
        $startDate = $startDateString ? new DateTime($startDateString) : new DateTime()->modify(
            '-1 week'
        );

        $endDate = $endDateString ? new DateTime($endDateString) : new DateTime();

        $interval = $startDate->diff($endDate);

        if ($interval->days > 366) {
            $this->addFlash('error_admin', 'Maximum date range is 1 year');
            return $this->redirectToRoute('admin_dashboard_statistics');
        }

        $statisticsService = new Statistics(
            $this->entityManager,
            $this->radiusAuthsRepository,
            $this->radiusAccountingRepository
        );
        $fetchChartDevices = $statisticsService->fetchChartDevices($startDate, $endDate);
        $fetchChartAuthentication = $statisticsService->fetchChartAuthentication($startDate, $endDate);
        $fetchChartPlatformStatus = $statisticsService->fetchChartPlatformStatus($startDate, $endDate);
        $fetchChartUserVerified = $statisticsService->fetchChartUserVerified($startDate, $endDate);
        $fetchChartSMSEmail = $statisticsService->fetchChartSMSEmail($startDate, $endDate);
        $fetchChart2FA = $statisticsService->fetchChart2FA($startDate, $endDate);

        $memory_before = memory_get_usage();
        $memory_after = memory_get_usage();
        $memory_diff = $memory_after - $memory_before;

        // Check that the memory usage does not exceed the PHP memory limit of 128M
        if ($memory_diff > 134217728) {
            $this->addFlash(
                'error_admin',
                'The data you requested is too large to be processed. Please try a smaller date range.'
            );
            return $this->redirectToRoute('admin_dashboard_statistics');
        }

        return $this->render('admin/statistics.html.twig', [
            'user' => $currentUser,
            'data' => $data,
            'devicesDataJson' => json_encode($fetchChartDevices, JSON_THROW_ON_ERROR),
            'authenticationDataJson' => json_encode($fetchChartAuthentication, JSON_THROW_ON_ERROR),
            'platformStatusDataJson' => json_encode($fetchChartPlatformStatus, JSON_THROW_ON_ERROR),
            'usersVerifiedDataJson' => json_encode($fetchChartUserVerified, JSON_THROW_ON_ERROR),
            'SMSEmailDataJson' => json_encode($fetchChartSMSEmail, JSON_THROW_ON_ERROR),
            'twoFADataJson' => json_encode($fetchChart2FA, JSON_THROW_ON_ERROR),
            'selectedStartDate' => $startDate->format('Y-m-d\TH:i'),
            'selectedEndDate' => $endDate->format('Y-m-d\TH:i'),
        ]);
    }
}
