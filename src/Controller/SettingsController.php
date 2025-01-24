<?php

namespace App\Controller;

use App\Entity\Event;
use App\Entity\Setting;
use App\Entity\TextEditor;
use App\Entity\User;
use App\Enum\AnalyticalEventType;
use App\Enum\EmailConfirmationStrategy;
use App\Enum\PlatformMode;
use App\Enum\TextEditorName;
use App\Enum\twoFAType;
use App\Form\AuthType;
use App\Form\CapportType;
use App\Form\LDAPType;
use App\Form\RadiusType;
use App\Form\SMSType;
use App\Form\StatusType;
use App\Form\TermsType;
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
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\IsGranted;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;
use Symfony\Component\Routing\Attribute\Route;

class SettingsController extends AbstractController
{
    private EventActions $eventActions;

    private GetSettings $getSettings;
    private SettingRepository $settingRepository;
    private UserRepository $userRepository;
    private EntityManagerInterface $entityManager;
    private RadiusAuthsRepository $radiusAuthsRepository;
    private RadiusAccountingRepository $radiusAccountingRepository;

    public function __construct(
        EventActions $eventActions,
        GetSettings $getSettings,
        SettingRepository $settingRepository,
        UserRepository $userRepository,
        EntityManagerInterface $entityManager,
        RadiusAuthsRepository $radiusAuthsRepository,
        RadiusAccountingRepository $radiusAccountingRepository
    ) {
        $this->eventActions = $eventActions;
        $this->getSettings = $getSettings;
        $this->settingRepository = $settingRepository;
        $this->userRepository = $userRepository;
        $this->entityManager = $entityManager;
        $this->radiusAuthsRepository = $radiusAuthsRepository;
        $this->radiusAccountingRepository = $radiusAccountingRepository;
    }
    /*
     * Check if the code and then return the correct action
     */
    /**
     * @param RequestStack $requestStack
     * @param EntityManagerInterface $em
     * @param string $type Type of action
     * @return Response
     * @throws Exception
     */
    #[Route('/dashboard/confirm-checker/{type}', name: 'admin_confirm_checker')]
    #[IsGranted('ROLE_ADMIN')]
    public function checkSettings(
        RequestStack $requestStack,
        EntityManagerInterface $em,
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
                    'uuid' => $currentUser->getUuid(),
                ];
                $this->eventActions->saveEvent(
                    $currentUser,
                    AnalyticalEventType::SETTING_PAGE_STYLE_RESET_REQUEST,
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
                    'uuid' => $currentUser->getUuid(),
                ];
                $this->eventActions->saveEvent(
                    $currentUser,
                    AnalyticalEventType::SETTING_TERMS_RESET_REQUEST,
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
                    'uuid' => $currentUser->getUuid(),
                ];
                $this->eventActions->saveEvent(
                    $currentUser,
                    AnalyticalEventType::SETTING_RADIUS_CONF_RESET_REQUEST,
                    new DateTime(),
                    $eventMetadata
                );

                return $this->redirectToRoute('admin_dashboard_settings_radius');
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

                $event = new Event();
                $event->setUser($currentUser);
                $event->setEventDatetime(new DateTime());
                $event->setEventName(AnalyticalEventType::SETTING_PLATFORM_STATUS_RESET_REQUEST);
                $event->setEventMetadata([
                    'ip' => $request->getClientIp(),
                    'uuid' => $currentUser->getUuid()
                ]);

                $em->persist($event);
                $em->flush();
                return $this->redirectToRoute('admin_dashboard_settings_status');
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
                    'uuid' => $currentUser->getUuid(),
                ];
                $this->eventActions->saveEvent(
                    $currentUser,
                    AnalyticalEventType::SETTING_LDAP_CONF_RESET_REQUEST,
                    new DateTime(),
                    $eventMetadata
                );

                return $this->redirectToRoute('admin_dashboard_settings_LDAP');
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
                    'uuid' => $currentUser->getUuid(),
                ];
                $this->eventActions->saveEvent(
                    $currentUser,
                    AnalyticalEventType::SETTING_CAPPORT_CONF_RESET_REQUEST,
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
                    'uuid' => $currentUser->getUuid(),
                ];
                $this->eventActions->saveEvent(
                    $currentUser,
                    AnalyticalEventType::SETTING_AUTHS_CONF_RESET_REQUEST,
                    new DateTime(),
                    $eventMetadata
                );

                return $this->redirectToRoute('admin_dashboard_settings_auth');
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
                $this->addFlash('success_admin', 'The configuration SMS settings has been clear successfully!');

                $eventMetadata = [
                    'ip' => $request->getClientIp(),
                    'uuid' => $currentUser->getUuid(),
                ];
                $this->eventActions->saveEvent(
                    $currentUser,
                    AnalyticalEventType::SETTING_SMS_CONF_CLEAR_REQUEST,
                    new DateTime(),
                    $eventMetadata
                );

                return $this->redirectToRoute('admin_dashboard_settings_sms');
            }
        }

        $this->addFlash('error_admin', 'The verification code is incorrect. Please try again.');
        return $this->redirectToRoute('admin_confirm_reset', ['type' => $type]);
    }

    /**
     * @param Request $request
     * @param EntityManagerInterface $em
     * @param GetSettings $getSettings
     * @return Response
     */
    #[Route('/dashboard/settings/terms', name: 'admin_dashboard_settings_terms')]
    #[IsGranted('ROLE_ADMIN')]
    public function settingsTerms(
        Request $request,
        EntityManagerInterface $em,
        GetSettings $getSettings
    ): Response {
        // Get the current logged-in user (admin)
        /** @var User $currentUser */
        $currentUser = $this->getUser();

        $textEditorRepository = $em->getRepository(TextEditor::class);
        $tosTextEditor = $textEditorRepository->findOneBy(['name' => TextEditorName::TOS]);
        if (!$tosTextEditor) {
            $tosTextEditor = new TextEditor();
            $tosTextEditor->setName(TextEditorName::TOS);
            $tosTextEditor->setContent('');
            $em->persist($tosTextEditor);
        }
        $privacyPolicyTextEditor = $textEditorRepository->findoneBy(['name' => TextEditorName::PRIVACY_POLICY]);
        if (!$privacyPolicyTextEditor) {
            $privacyPolicyTextEditor = new TextEditor();
            $privacyPolicyTextEditor->setName(TextEditorName::PRIVACY_POLICY);
            $privacyPolicyTextEditor->setContent('');
            $em->persist($privacyPolicyTextEditor);
        }
        $em->flush();

        $data = $this->getSettings->getSettings($this->userRepository, $this->settingRepository);

        $settingsRepository = $em->getRepository(Setting::class);
        $settings = $settingsRepository->findAll();

        foreach ($settings as $setting) {
            if ($setting->getName() === 'TOS_EDITOR' || $setting->getName() === 'PRIVACY_POLICY_EDITOR') {
                $em->remove($setting);
                $em->flush();
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
            if ($tosSetting) {
                $tosSetting->setValue($tos);
                $em->persist($tosSetting);
            }

            $privacyPolicySetting = $settingsRepository->findOneBy(['name' => 'PRIVACY_POLICY']);
            if ($privacyPolicySetting) {
                $privacyPolicySetting->setValue($privacyPolicy);
                $em->persist($privacyPolicySetting);
            }

            $tosLinkSetting = $settingsRepository->findOneBy(['name' => 'TOS_LINK']);
            if ($tosLinkSetting) {
                $tosLinkSetting->setValue($tosLink);
                $em->persist($tosLinkSetting);
            }

            $privacyPolicyLinkSetting = $settingsRepository->findOneBy(['name' => 'PRIVACY_POLICY_LINK']);
            if ($privacyPolicyLinkSetting) {
                $privacyPolicyLinkSetting->setValue($privacyPolicyLink);
                $em->persist($privacyPolicyLinkSetting);
            }
            $sanitizeHtml = new SanitizeHTML();
            if ($tosTextEditor) {
                $tosEditorSetting = $textEditorRepository->findOneBy(['name' => TextEditorName::TOS]);
                if ($tosEditorSetting) {
                    $cleanHTML = $sanitizeHtml->sanitizeHtml($tosTextEditor);
                    $tosEditorSetting->setContent($cleanHTML);
                }
                $em->persist($tosEditorSetting);
            }

            if ($privacyPolicyTextEditor) {
                $privacyPolicyEditorSetting = $textEditorRepository->findOneBy([
                    'name' => TextEditorName::PRIVACY_POLICY
                ]);
                if ($privacyPolicyEditorSetting) {
                    $cleanHTML = $sanitizeHtml->sanitizeHtml($privacyPolicyTextEditor);
                    $privacyPolicyEditorSetting->setContent($cleanHTML);
                }
                $em->persist($privacyPolicyEditorSetting);
            }
            $eventMetadata = [
                'ip' => $request->getClientIp(),
                'uuid' => $currentUser->getUuid(),
            ];
            $this->eventActions->saveEvent(
                $currentUser,
                AnalyticalEventType::SETTING_TERMS_REQUEST,
                new DateTime(),
                $eventMetadata
            );


            $em->flush();
            $this->addFlash('success_admin', 'Terms and Policies links changes have been applied successfully.');
            return $this->redirectToRoute('admin_dashboard_settings_terms');
        }


        return $this->render('admin/settings_actions.html.twig', [
            'data' => $data,
            'settings' => $settings,
            'getSettings' => $getSettings,
            'current_user' => $currentUser,
            'form' => $form->createView(),
        ]);
    }

    /**
     * @param Request $request
     * @param EntityManagerInterface $em
     * @param GetSettings $getSettings
     * @return Response
     */
    #[Route('/dashboard/settings/radius', name: 'admin_dashboard_settings_radius')]
    #[IsGranted('ROLE_ADMIN')]
    public function settingsRadius(Request $request, EntityManagerInterface $em, GetSettings $getSettings): Response
    {
        $data = $getSettings->getSettings($this->userRepository, $this->settingRepository);
        // Get the current logged-in user (admin)
        /** @var User $currentUser */
        $currentUser = $this->getUser();
        $domainService = new Domain();
        $settingsRepository = $em->getRepository(Setting::class);
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
                    if ($setting) {
                        $setting->setValue($value);
                        $em->persist($setting);
                    }
                }

                $eventMetadata = [
                    'ip' => $request->getClientIp(),
                    'uuid' => $currentUser->getUuid(),
                ];
                $this->eventActions->saveEvent(
                    $currentUser,
                    AnalyticalEventType::SETTING_RADIUS_CONF_REQUEST,
                    new DateTime(),
                    $eventMetadata
                );

                $this->addFlash('success_admin', 'Radius configuration have been applied successfully.');
                return $this->redirectToRoute('admin_dashboard_settings_radius');
            }
        }

        return $this->render('admin/settings_actions.html.twig', [
            'data' => $data,
            'settings' => $settings,
            'getSettings' => $getSettings,
            'form' => $form->createView()
        ]);
    }

    /**
     * @param Request $request
     * @param EntityManagerInterface $em
     * @param GetSettings $getSettings
     * @return Response
     */
    #[Route('/dashboard/settings/status', name: 'admin_dashboard_settings_status')]
    #[IsGranted('ROLE_ADMIN')]
    public function settingsStatus(
        Request $request,
        EntityManagerInterface $em,
        GetSettings $getSettings
    ): Response {
        // Get the current logged-in user (admin)
        /** @var User $currentUser */
        $currentUser = $this->getUser();
        $data = $this->getSettings->getSettings($this->userRepository, $this->settingRepository);

        $settingsRepository = $em->getRepository(Setting::class);
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
            $userDeleteTime = $submittedData['USER_DELETE_TIME'] ?? 5;
            // Update the 'USER_VERIFICATION', and, if the platform mode is Live, set email verification to ON always
            $emailVerification = ($platformMode === PlatformMode::LIVE) ?
                EmailConfirmationStrategy::EMAIL : $submittedData['USER_VERIFICATION'] ?? null;
            $twoFactorAuthStatus = $submittedData['TWO_FACTOR_AUTH_STATUS'] ?? twoFAType::NOT_ENFORCED;

            $platformModeSetting = $settingsRepository->findOneBy(['name' => 'PLATFORM_MODE']);
            if ($platformModeSetting) {
                $platformModeSetting->setValue($platformMode);
                $em->persist($platformModeSetting);
            }

            $emailVerificationSetting = $settingsRepository->findOneBy(['name' => 'USER_VERIFICATION']);
            if ($emailVerificationSetting) {
                $emailVerificationSetting->setValue($emailVerification);
                $em->persist($emailVerificationSetting);
            }

            $turnstileCheckerSetting = $settingsRepository->findOneBy(['name' => 'TURNSTILE_CHECKER']);
            if ($turnstileCheckerSetting) {
                $turnstileCheckerSetting->setValue($turnstileChecker);
                $em->persist($turnstileCheckerSetting);
            }
            $userDeleteTimeSetting = $settingsRepository->findOneBy(['name' => 'USER_DELETE_TIME']);
            if ($userDeleteTimeSetting) {
                $userDeleteTimeSetting->setValue($userDeleteTime);
                $em->persist($userDeleteTimeSetting);
            }
            $twoFactorAuthStatusSetting = $settingsRepository->findOneBy(['name' => 'TWO_FACTOR_AUTH_STATUS']);
            if ($twoFactorAuthStatusSetting) {
                $twoFactorAuthStatusSetting->setValue($twoFactorAuthStatus);
                $em->persist($twoFactorAuthStatusSetting);
            }
            // Flush the changes to the database
            $em->flush();

            $eventMetadata = [
                'ip' => $request->getClientIp(),
                'uuid' => $currentUser->getUuid()
            ];
            $this->eventActions->saveEvent(
                $currentUser,
                AnalyticalEventType::SETTING_PLATFORM_STATUS_REQUEST,
                new DateTime(),
                $eventMetadata
            );

            $this->addFlash('success_admin', 'The new changes have been applied successfully.');
            return $this->redirectToRoute('admin_dashboard_settings_status');
        }


        return $this->render('admin/settings_actions.html.twig', [
            'data' => $data,
            'settings' => $settings,
            'getSettings' => $getSettings,
            'current_user' => $currentUser,
            'form' => $form->createView(),
        ]);
    }

    /**
     * @param Request $request
     * @param EntityManagerInterface $em
     * @param GetSettings $getSettings
     * @return Response
     */
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
                if ($setting) {
                    $setting->setValue($value);
                    $em->persist($setting);
                }
            }

            $eventMetadata = [
                'ip' => $request->getClientIp(),
                'uuid' => $currentUser->getUuid(),
            ];
            $this->eventActions->saveEvent(
                $currentUser,
                AnalyticalEventType::SETTING_LDAP_CONF_REQUEST,
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

    /**
     * @param Request $request
     * @param EntityManagerInterface $em
     * @param GetSettings $getSettings
     * @return Response
     */
    #[Route('/dashboard/settings/auth', name: 'admin_dashboard_settings_auth')]
    #[IsGranted('ROLE_ADMIN')]
    public function settingsAuths(
        Request $request,
        EntityManagerInterface $em,
        GetSettings $getSettings,
        CertificateService $certificateService
    ): Response {
        // Get the current logged-in user (admin)
        /** @var User $currentUser */
        $currentUser = $this->getUser();
        $data = $this->getSettings->getSettings($this->userRepository, $this->settingRepository);

        $settingsRepository = $em->getRepository(Setting::class);
        $settings = $settingsRepository->findAll();

        $certificatePath = $this->getParameter('kernel.project_dir') . '/signing-keys/cert.pem';
        $certificateLimitDate = strtotime($certificateService->getCertificateExpirationDate($certificatePath));
        $realTime = time();
        $timeLeft = round(($certificateLimitDate - $realTime) / (60 * 60 * 24)) - 1;
        $profileLimitDate = ((int)$timeLeft);
        if ($profileLimitDate < 0) {
            $profileLimitDate = 0;
        }

        $defaultTimeZone = date_default_timezone_get();
        $dateTime = (new DateTime())
            ->setTimestamp($certificateLimitDate)
            ->setTimezone(new \DateTimeZone($defaultTimeZone));

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
                'AUTH_METHOD_SAML_LABEL',
                'AUTH_METHOD_SAML_DESCRIPTION',
                'PROFILE_LIMIT_DATE_SAML',

                'AUTH_METHOD_GOOGLE_LOGIN_ENABLED',
                'AUTH_METHOD_GOOGLE_LOGIN_LABEL',
                'AUTH_METHOD_GOOGLE_LOGIN_DESCRIPTION',
                'VALID_DOMAINS_GOOGLE_LOGIN',
                'PROFILE_LIMIT_DATE_GOOGLE',

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
                'AUTH_METHOD_REGISTER_LABEL',
                'AUTH_METHOD_LOGIN_TRADITIONAL_LABEL',
                'AUTH_METHOD_SMS_REGISTER_LABEL',
            ];

            foreach ($settingsToUpdate as $settingName) {
                $value = $submittedData[$settingName] ?? null;

                // Check if the setting is a label, to be impossible to set it null of empty
                if (in_array($settingName, $labelsFields)) {
                    if ($value === null || $value === "") {
                        continue;
                    }
                }

                $setting = $settingsRepository->findOneBy(['name' => $settingName]);
                if ($settingName === 'VALID_DOMAINS_GOOGLE_LOGIN') {
                    if ($setting) {
                        $setting->setValue($value);
                        $em->persist($setting);
                    }
                    continue;
                }

                if ($setting) {
                    $setting->setValue($value);
                    $em->persist($setting);
                }
            }

            $eventMetadata = [
                'ip' => $request->getClientIp(),
                'uuid' => $currentUser->getUuid(),
            ];

            $this->eventActions->saveEvent(
                $currentUser,
                AnalyticalEventType::SETTING_AUTHS_CONF_REQUEST,
                new DateTime(),
                $eventMetadata
            );
            $this->addFlash('success_admin', 'New authentication configuration have been applied successfully.');
            return $this->redirectToRoute('admin_dashboard_settings_auth');
        }

        return $this->render('admin/settings_actions.html.twig', [
            'data' => $data,
            'settings' => $settings,
            'getSettings' => $getSettings,
            'current_user' => $currentUser,
            'form' => $form->createView(),
            'profileLimitDate' => $profileLimitDate,
            'humanReadableExpirationDate' => $humanReadableExpirationDate
        ]);
    }

    /**
     * @param Request $request
     * @param EntityManagerInterface $em
     * @param GetSettings $getSettings
     * @return Response
     */
    #[Route('/dashboard/settings/capport', name: 'admin_dashboard_settings_capport')]
    #[IsGranted('ROLE_ADMIN')]
    public function settingsCAPPORT(
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
                if ($setting) {
                    $setting->setValue($value);
                    $em->persist($setting);
                }
            }

            $eventMetadata = [
                'ip' => $request->getClientIp(),
                'uuid' => $currentUser->getUuid(),
            ];
            $this->eventActions->saveEvent(
                $currentUser,
                AnalyticalEventType::SETTING_CAPPORT_CONF_REQUEST,
                new DateTime(),
                $eventMetadata
            );

            $this->addFlash('success_admin', 'New CAPPORT configuration have been applied successfully.');
            return $this->redirectToRoute('admin_dashboard_settings_capport');
        }

        return $this->render('admin/settings_actions.html.twig', [
            'data' => $data,
            'settings' => $settings,
            'getSettings' => $getSettings,
            'form' => $form->createView()
        ]);
    }

    /**
     * @param Request $request
     * @param EntityManagerInterface $em
     * @param GetSettings $getSettings
     * @return Response
     */
    #[Route('/dashboard/settings/sms', name: 'admin_dashboard_settings_sms')]
    #[IsGranted('ROLE_ADMIN')]
    public function settingsSMS(
        Request $request,
        EntityManagerInterface $em,
        GetSettings $getSettings
    ): Response {
        // Get the current logged-in user (admin)
        /** @var User $currentUser */
        $currentUser = $this->getUser();
        $data = $this->getSettings->getSettings($this->userRepository, $this->settingRepository);

        $settingsRepository = $em->getRepository(Setting::class);
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
                if ($setting) {
                    $setting->setValue($value);
                    $em->persist($setting);
                }
            }

            $eventMetadata = [
                'ip' => $request->getClientIp(),
                'uuid' => $currentUser->getUuid(),
            ];
            $this->eventActions->saveEvent(
                $currentUser,
                AnalyticalEventType::SETTING_SMS_CONF_REQUEST,
                new DateTime(),
                $eventMetadata
            );

            $this->addFlash('success_admin', 'New SMS configuration have been applied successfully.');
            return $this->redirectToRoute('admin_dashboard_settings_sms');
        }

        return $this->render('admin/settings_actions.html.twig', [
            'data' => $data,
            'settings' => $settings,
            'getSettings' => $getSettings,
            'current_user' => $currentUser,
            'form' => $form->createView(),
        ]);
    }

    /**
     * Render Statistics  about the Portal data
     */
    /**
     * @param Request $request
     * @return Response
     * @throws \JsonException
     * @throws Exception
     */
    #[Route('/dashboard/statistics', name: 'admin_dashboard_statistics')]
    #[IsGranted('ROLE_ADMIN')]
    public function statisticsData(Request $request): Response
    {
        $data = $this->getSettings->getSettings($this->userRepository, $this->settingRepository);

        // Get the submitted start and end dates from the form
        $startDateString = $request->request->get('startDate');
        $endDateString = $request->request->get('endDate');

        // Convert the date strings to DateTime objects
        if ($startDateString) {
            $startDate = new DateTime($startDateString);
        } else {
            $startDate = (new DateTime())->modify(
                '-1 week'
            );
        }

        if ($endDateString) {
            $endDate = new DateTime($endDateString);
        } else {
            $endDate = new DateTime();
        }

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

        $memory_before = memory_get_usage();
        $memory_after = memory_get_usage();
        $memory_diff = $memory_after - $memory_before;

        // Check that the memory usage does not exceed the PHP memory limit of 128M
        if ($memory_diff > 128 * 1024 * 1024) {
            $this->addFlash(
                'error_admin',
                'The data you requested is too large to be processed. Please try a smaller date range.'
            );
            return $this->redirectToRoute('admin_dashboard_statistics');
        }

        return $this->render('admin/statistics.html.twig', [
            'data' => $data,
            'devicesDataJson' => json_encode($fetchChartDevices, JSON_THROW_ON_ERROR),
            'authenticationDataJson' => json_encode($fetchChartAuthentication, JSON_THROW_ON_ERROR),
            'platformStatusDataJson' => json_encode($fetchChartPlatformStatus, JSON_THROW_ON_ERROR),
            'usersVerifiedDataJson' => json_encode($fetchChartUserVerified, JSON_THROW_ON_ERROR),
            'SMSEmailDataJson' => json_encode($fetchChartSMSEmail, JSON_THROW_ON_ERROR),
            'selectedStartDate' => $startDate->format('Y-m-d\TH:i'),
            'selectedEndDate' => $endDate->format('Y-m-d\TH:i'),
        ]);
    }
}
