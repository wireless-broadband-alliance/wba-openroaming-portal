<?php

namespace App\Controller;

use App\DTO\AuthSettingsTypeDTO;
use App\DTO\CapportSettingsDTO;
use App\DTO\LDAPSettingsDTO;
use App\DTO\PlatformStatusSettingsDTO;
use App\DTO\RadiusSettingsDTO;
use App\DTO\SMSSettingsDTO;
use App\DTO\TwoFASettingsDTO;
use App\Entity\Setting;
use App\Entity\TextEditor;
use App\Entity\User;
use App\Enum\AdminRoleType;
use App\Enum\AnalyticalEventType;
use App\Enum\LanguageType;
use App\Enum\SettingName;
use App\Enum\SettingType;
use App\Enum\TextEditorName;
use App\Form\CapportSettingsType;
use App\Form\LDAPSettingsType;
use App\Form\PlatformStatusSettingsType;
use App\Form\RadiusSettingsType;
use App\Form\SMSSettingsType;
use App\Form\AuthSettingsType;
use App\Form\TermsType;
use App\Form\TwoFASettingsType;
use App\Repository\TextEditorRepository;
use App\Security\Voter\UserAuthenticationVoter;
use App\Service\CertificateCheckerService;
use App\Service\EventActions;
use App\Service\GetSettings;
use App\Service\HtmlSanitizerService;
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
        private readonly CertificateCheckerService $certificateCheckerService,
        private readonly TextEditorRepository $textEditorRepository,
        private readonly SettingsService $settingsService,
        private readonly HtmlSanitizerService $htmlSanitizerService,
    ) {
    }

    /*
     * Check if the code and then return the correct action
     */
    /**
     * @param string $type Type of action
     */
    #[Route('/dashboard/confirm-checker/{type}', name: 'admin_confirm_checker')]
    #[IsGranted(AdminRoleType::ROLE_ADMIN->value)]
    public function checkSettings(Request $request, string $type): Response
    {
        // Get the entered code from the form
        $enteredCode = $request->get('code');

        /** @var User $currentUser */
        $currentUser = $this->getUser();

        if ($enteredCode === $currentUser->getTwoFAcode()) {
            if (
                $type === SettingType::SettingCustom->value
                && $this->isGranted(UserAuthenticationVoter::LANDING_PAGE_CONFIG_WRITE)
            ) {
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
                    'success',
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

            if (
                $type === SettingType::SettingTerms->value
                && $this->isGranted(UserAuthenticationVoter::TERMS_POLICIES_WRITE)
            ) {
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
                    'success',
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

            if (
                $type === SettingType::SettingRadius->value
                && $this->isGranted(UserAuthenticationVoter::RADIUS_PROFILE_CONFIG_WRITE)
            ) {
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
                    'success',
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

            if (
                $type === SettingType::SettingLDAP->value
                && $this->isGranted(UserAuthenticationVoter::LDAP_SYNCHRONIZATION_WRITE)
            ) {
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
                    'success',
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

            if (
                $type === SettingType::SettingStatus->value
                && $this->isGranted(UserAuthenticationVoter::PLATFORM_STATUS_WRITE)
            ) {
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
                    'success',
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

            if (
                $type === SettingType::SettingCAPPORT->value
                && $this->isGranted(UserAuthenticationVoter::USER_ENGAGEMENT_WRITE)
            ) {
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
                    'success',
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

            if (
                $type === SettingType::SettingAUTH->value
                && $this->isGranted(UserAuthenticationVoter::AUTHENTICATION_METHODS_WRITE)
            ) {
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
                    'success',
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

            if (
                $type === SettingType::SettingTwoFA->value
                && $this->isGranted(UserAuthenticationVoter::TWO_FACTOR_AUTH_WRITE)
            ) {
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
                    'success',
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

            if (
                $type === SettingType::SettingSMS->value
                && $this->isGranted(UserAuthenticationVoter::SMS_CONFIG_WRITE)
            ) {
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
                    'success',
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

            if (
                $type === SettingType::SettingSchedule->value
                && $this->isGranted(UserAuthenticationVoter::CRON_SCHEDULE_WRITE)
            ) {
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
                    'success',
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
            if (
                $type === SettingType::SettingsReturnApps->value
                && $this->isGranted(UserAuthenticationVoter::RETURN_APPS_MANAGEMENT_WRITE)
            ) {
                $command = 'php bin/console reset:returnApps --yes';
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
                    'success',
                    $this->translator->trans('returnAppsResetSuccessfully', [], 'controllers')
                );

                $eventMetadata = [
                    'ip' => $request->getClientIp(),
                    'user_agent' => $request->headers->get('User-Agent'),
                    'uuid' => $currentUser->getUuid(),
                ];
                $this->eventActions->saveEvent(
                    $currentUser,
                    AnalyticalEventType::RETURN_APPS_RESET_REQUEST->value,
                    new DateTime(),
                    $eventMetadata
                );

                return $this->redirectToRoute('admin_dashboard_return_apps');
            }
        } else {
            $this->addFlash(
                'error',
                $this->translator->trans('incorrectVerificationCode', [], 'controllers')
            );
        }

        $this->addFlash(
            'error',
            $this->translator->trans('incorrectVerificationCode', [], 'controllers')
        );
        return $this->redirectToRoute('admin_confirm_reset', ['type' => $type]);
    }

    #[Route(
        '/dashboard/settings/terms/{language}',
        name: 'admin_dashboard_settings_terms',
        defaults: ['language' => LanguageType::EN->value]
    )]
    #[IsGranted(UserAuthenticationVoter::TERMS_POLICIES_READ)]
    public function settingsTerms(Request $request, string $language): Response
    {
        /** @var User $currentUser */
        $currentUser = $this->getUser();
        $canWrite = $this->isGranted(UserAuthenticationVoter::TERMS_POLICIES_WRITE);

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

        $form = $this->createForm(TermsType::class, null, ['settings' => $settings, 'disabled' => !$canWrite]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid() && $canWrite) {
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
                $this->htmlSanitizerService->sanitize(
                    $form->get(TextEditorName::TOS_EDITOR->value)->getData()
                )
            );
            $privacyPolicyTextEditor->setContent(
                $this->htmlSanitizerService->sanitize(
                    $form->get(TextEditorName::PRIVACY_POLICY_EDITOR->value)->getData()
                )
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
                'success',
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
    #[IsGranted(UserAuthenticationVoter::LDAP_SYNCHRONIZATION_READ)]
    public function settingsLDAP(Request $request): Response
    {
        /** @var array<string, array{value: string, description: string}> $data */
        $data = $this->getSettings->getSettings();

        /** @var User $currentUser */
        $currentUser = $this->getUser();
        $canWrite = $this->isGranted(UserAuthenticationVoter::LDAP_SYNCHRONIZATION_WRITE);

        // Initialize DTO from settings
        $dto = new LDAPSettingsDTO($data);

        // Create form bound to DTO
        $form = $this->createForm(LDAPSettingsType::class, $dto, ['disabled' => !$canWrite]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid() && $canWrite) {
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
                'success',
                $this->translator->trans('LDAPConfigurationAppliedSuccessfully', [], 'controllers')
            );

            return $this->redirectToRoute('admin_dashboard_settings_LDAP');
        }

        return $this->render('dashboard/shared/settings_actions.html.twig', [
            'form' => $form->createView(),
            'ldapSettingsDTO' => $dto,
            'data' => $data,
            'user' => $currentUser,
        ]);
    }

    #[Route('/dashboard/settings/radius', name: 'admin_dashboard_settings_radius')]
    #[IsGranted(UserAuthenticationVoter::RADIUS_PROFILE_CONFIG_READ)]
    public function settingsRadius(Request $request): Response
    {
        /** @var array<string, array{value: string, description: string}> $data */
        $data = $this->getSettings->getSettings();

        /** @var User $currentUser */
        $currentUser = $this->getUser();
        $canWrite = $this->isGranted(UserAuthenticationVoter::RADIUS_PROFILE_CONFIG_WRITE);

        // Initialize DTO from settings
        $dto = new RadiusSettingsDTO($data);

        // Create form bound to DTO
        $form = $this->createForm(RadiusSettingsType::class, $dto, ['disabled' => !$canWrite]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid() && $canWrite) {
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
                'success',
                $this->translator->trans('radiusConfigurationAppliedSuccessfully', [], 'controllers')
            );

            return $this->redirectToRoute('admin_dashboard_settings_radius');
        }

        return $this->render('dashboard/shared/settings_actions.html.twig', [
            'form' => $form->createView(),
            'radiusSettingsDTO' => $dto,
            'data' => $data,
            'user' => $currentUser,
        ]);
    }

    #[Route('/dashboard/settings/status', name: 'admin_dashboard_settings_status')]
    #[IsGranted(UserAuthenticationVoter::PLATFORM_STATUS_READ)]
    public function settingsStatus(Request $request): Response
    {
        /** @var array<string, array{value: string, description: string}> $data */
        $data = $this->getSettings->getSettings();

        /** @var User $currentUser */
        $currentUser = $this->getUser();
        $canWrite = $this->isGranted(UserAuthenticationVoter::PLATFORM_STATUS_WRITE);

        // Initialize DTO from settings
        $dto = new PlatformStatusSettingsDTO($data);

        // Create form bound to DTO
        $form = $this->createForm(PlatformStatusSettingsType::class, $dto, ['disabled' => !$canWrite]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid() && $canWrite) {
            // Save updated settings
            $this->settingsService->updateSettingsFromArray($dto->toArray());
            $this->settingsService->flush();

            // Log the event
            $this->eventActions->saveEvent(
                $currentUser,
                AnalyticalEventType::SETTING_PLATFORM_STATUS_REQUEST->value,
                new DateTime(),
                [
                    'ip' => $request->getClientIp(),
                    'user_agent' => $request->headers->get('User-Agent'),
                    'uuid' => $currentUser->getUuid(),
                ]
            );

            $this->addFlash(
                'success',
                $this->translator->trans('newChangesAppliedSuccessfully', [], 'controllers')
            );
            return $this->redirectToRoute('admin_dashboard_settings_status');
        }

        return $this->render('dashboard/shared/settings_actions.html.twig', [
            'form' => $form->createView(),
            'platformStatusSettingsDTO' => $dto,
            'data' => $data,
            'user' => $currentUser,
        ]);
    }

    #[Route('/dashboard/settings/twoFA', name: 'admin_dashboard_settings_two_fa')]
    #[IsGranted(UserAuthenticationVoter::TWO_FACTOR_AUTH_READ)]
    public function settingsTwoFA(Request $request): Response
    {
        /** @var array<string, array{value: string, description: string}> $data */
        $data = $this->getSettings->getSettings();

        /** @var User $currentUser */
        $currentUser = $this->getUser();
        $canWrite = $this->isGranted(UserAuthenticationVoter::TWO_FACTOR_AUTH_WRITE);

        // Initialize DTO from settings
        $dto = new TwoFASettingsDTO($data);

        // Create form bound to DTO
        $form = $this->createForm(TwoFASettingsType::class, $dto, ['disabled' => !$canWrite]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid() && $canWrite) {
            // Save updated settings
            $this->settingsService->updateSettingsFromArray($dto->toArray());
            $this->settingsService->flush();

            // Log the event
            $this->eventActions->saveEvent(
                $currentUser,
                AnalyticalEventType::SETTING_PLATFORM_2FA_REQUEST->value,
                new DateTime(),
                [
                    'ip' => $request->getClientIp(),
                    'user_agent' => $request->headers->get('User-Agent'),
                    'uuid' => $currentUser->getUuid(),
                ]
            );

            $this->addFlash(
                'success',
                $this->translator->trans('newChangesAppliedSuccessfully', [], 'controllers')
            );
            return $this->redirectToRoute('admin_dashboard_settings_two_fa');
        }

        return $this->render('dashboard/shared/settings_actions.html.twig', [
            'form' => $form->createView(),
            'twoFASettingsDTO' => $dto,
            'data' => $data,
            'user' => $currentUser,
        ]);
    }

    /**
     * @throws \DateInvalidTimeZoneException
     * @throws \Exception
     */
    #[Route(
        '/dashboard/settings/auth/{language}',
        name: 'admin_dashboard_settings_auth',
        defaults: ['language' => LanguageType::EN->value]
    )]
    #[IsGranted(AdminRoleType::ROLE_ADMIN->value)]
    public function settingsAuths(
        Request $request,
        string $language
    ): Response {
        $missingFiles = $this->certificateCheckerService->verifyCertificates();
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
        $canWrite = $this->isGranted(UserAuthenticationVoter::AUTHENTICATION_METHODS_WRITE);

        /** @var array<string, array{value: string, description: string}> $data */
        $data = $this->getSettings->getSettings($language);

        $settingsRepository = $this->entityManager->getRepository(Setting::class);
        $settings = $settingsRepository->findAll();

        $certificatePath = $this->getParameter('kernel.project_dir') . '/signing-keys/cert.pem';
        $certificateLimitDate = strtotime(
            (string)$this->certificateCheckerService->getCertificateExpirationDate(
                $certificatePath
            )
        );
        $realTime = time();
        $timeLeft = round(($certificateLimitDate - $realTime) / (86400)) - 1;
        $profileLimitDate = ((int)$timeLeft);
        if ($profileLimitDate < 0) {
            $profileLimitDate = 0;
        }

        $defaultTimeZone = date_default_timezone_get();
        $dateTime = new DateTime()
            ->setTimestamp((int)$certificateLimitDate)
            ->setTimezone(new DateTimeZone($defaultTimeZone));

        // Convert to human-readable format
        $humanReadableExpirationDate = $dateTime->format('Y-m-d H:i:s T');

        // Get the settings value according to the language
        $settingsTranslated = $this->getSettings->getSettingsByLocale($settings, $data);

        $authSettingsTypeDTO = new AuthSettingsTypeDTO(
            $data,
            $profileLimitDate,
            $humanReadableExpirationDate
        );

        $form = $this->createForm(AuthSettingsType::class, $authSettingsTypeDTO, ['disabled' => !$canWrite]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid() && $canWrite) {
            $this->settingsService->updateAuthSettingsToTranslateFromArray($authSettingsTypeDTO->toArray(), $language);

            $this->settingsService->flush();


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
                'success',
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
            'authSettingsTypeDTO' => $authSettingsTypeDTO
        ]);
    }

    #[Route('/dashboard/settings/capport', name: 'admin_dashboard_settings_capport')]
    #[IsGranted(UserAuthenticationVoter::USER_ENGAGEMENT_READ)]
    public function settingsCAPPORT(Request $request): Response
    {
        /** @var array<string, array{value: string, description: string}> $data */
        $data = $this->getSettings->getSettings();

        /** @var User $currentUser */
        $currentUser = $this->getUser();
        $canWrite = $this->isGranted(UserAuthenticationVoter::USER_ENGAGEMENT_WRITE);

        // Initialize DTO from settings
        $dto = new CapportSettingsDTO($data);

        // Create form bound to DTO
        $form = $this->createForm(CapportSettingsType::class, $dto, ['disabled' => !$canWrite]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid() && $canWrite) {
            // Save updated settings
            $this->settingsService->updateSettingsFromArray($dto->toArray());
            $this->settingsService->flush();

            // Log the event
            $this->eventActions->saveEvent(
                $currentUser,
                AnalyticalEventType::SETTING_CAPPORT_CONF_REQUEST->value,
                new DateTime(),
                [
                    'ip' => $request->getClientIp(),
                    'user_agent' => $request->headers->get('User-Agent'),
                    'uuid' => $currentUser->getUuid(),
                ]
            );

            $this->addFlash(
                'success',
                $this->translator->trans('CAPPORTConfigurationAppliedSuccessfully', [], 'controllers')
            );
            return $this->redirectToRoute('admin_dashboard_settings_capport');
        }

        return $this->render('dashboard/shared/settings_actions.html.twig', [
            'form' => $form->createView(),
            'capportSettingsDTO' => $dto,
            'data' => $data,
            'user' => $currentUser,
        ]);
    }

    #[Route('/dashboard/settings/sms', name: 'admin_dashboard_settings_sms')]
    #[IsGranted(UserAuthenticationVoter::SMS_CONFIG_READ)]
    public function settingsSMS(Request $request): Response
    {
        /** @var array<string, array{value: string, description: string}> $data */
        $data = $this->getSettings->getSettings();

        /** @var User $currentUser */
        $currentUser = $this->getUser();
        $canWrite = $this->isGranted(UserAuthenticationVoter::SMS_CONFIG_WRITE);

        // Initialize DTO from settings
        $dto = new SMSSettingsDTO($data);

        // Create form bound to DTO
        $form = $this->createForm(SMSSettingsType::class, $dto, ['disabled' => !$canWrite]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid() && $canWrite) {
            // Save updated settings
            $this->settingsService->updateSettingsFromArray($dto->toArray());
            $this->settingsService->flush();

            // Log the event
            $this->eventActions->saveEvent(
                $currentUser,
                AnalyticalEventType::SETTING_SMS_CONF_REQUEST->value,
                new DateTime(),
                [
                    'ip' => $request->getClientIp(),
                    'user_agent' => $request->headers->get('User-Agent'),
                    'uuid' => $currentUser->getUuid(),
                ]
            );

            $this->addFlash(
                'success',
                $this->translator->trans('SMSConfigurationAppliedSuccessfully', [], 'controllers')
            );
            return $this->redirectToRoute('admin_dashboard_settings_sms');
        }

        return $this->render('dashboard/shared/settings_actions.html.twig', [
            'form' => $form->createView(),
            'SMSSettingsDTO' => $dto,
            'data' => $data,
            'user' => $currentUser,
        ]);
    }
}
