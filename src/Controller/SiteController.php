<?php

namespace App\Controller;

use App\DTO\NewPasswordAccountDTO;
use App\Entity\User;
use App\Entity\UserExternalAuth;
use App\Enum\AnalyticalEventType;
use App\Enum\EventMetadataKeysType;
use App\Enum\FirewallType;
use App\Enum\OperationMode;
use App\Enum\OSType;
use App\Enum\PlatformMode;
use App\Enum\SessionStatus;
use App\Enum\SettingName;
use App\Enum\TwoFAType;
use App\Enum\UserProvider;
use App\Enum\UserRadiusProfileRevokeReason;
use App\Enum\UserTwoFactorAuthenticationStatus;
use App\Form\AccountUserUpdateLandingType;
use App\Form\NewPasswordAccountType;
use App\Form\RegistrationFormType;
use App\Form\RevokeProfilesType;
use App\Form\TOSType;
use App\Repository\UserExternalAuthRepository;
use App\Security\LandingAuthenticator;
use App\Service\EmailGenerator;
use App\Service\EventActions;
use App\Service\GetSettings;
use App\Service\OSDetectionService;
use App\Service\ProfileManager;
use App\Service\SendSMS;
use App\Service\TwoFAService;
use App\Service\UserDeletion\UserDeletionService;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Exception\ORMException;
use Exception;
use libphonenumber\PhoneNumber;
use RuntimeException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\Exception\ExceptionInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Security\Http\Authentication\UserAuthenticatorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use Throwable;

/**
 * @method getParameterBag()
 */
class SiteController extends AbstractController
{
    public function __construct(
        private readonly UserExternalAuthRepository $userExternalAuthRepository,
        private readonly GetSettings $getSettings,
        private readonly EventActions $eventActions,
        private readonly ProfileManager $profileManager,
        private readonly TwoFAService $twoFAService,
        private readonly TranslatorInterface $translator,
        private readonly UserDeletionService $userDeletionService,
        private readonly EntityManagerInterface $entityManager,
        private readonly OSDetectionService $OSDetectionService,
        private readonly UserPasswordHasherInterface $userPasswordEncoder,
        private readonly UserAuthenticatorInterface $userAuthenticator,
        private readonly LandingAuthenticator $authenticator,
        private readonly EmailGenerator $emailGenerator,
        private readonly SendSMS $sendSMS,
    ) {
    }

    /**
     * @throws \JsonException
     * @throws ORMException
     * @throws ExceptionInterface
     */
    #[Route('/', name: 'app_landing')]
    public function landing(
        Request $request,
        EntityManagerInterface $entityManager,
    ): Response {
        // Call the getSettings method of GetSettings class to retrieve the data
        /** @var array<string, array{value: string, description: string}> $data */
        $data = $this->getSettings->getSettings();

        /** @var User|null $currentUser */
        $currentUser = $this->getUser();
        $session = $request->getSession();

        $userExternalAuths = [];

        // Check if the user_verification setting is active
        if ($currentUser && $data[SettingName::USER_VERIFICATION->value]["value"] === OperationMode::ON->value) {
            // Retrieve the cookie about SAML_ACCOUNT Deletion from the request
            $previousLoggedID = (int)$request->cookies->get('previousLoggedID');
            $userExternalAuths = $this->userExternalAuthRepository->findBy(['user' => $currentUser]);

            if ($previousLoggedID && $previousLoggedID === $currentUser->getId()) {
                // Notify the user before their data is wiped
                try {
                    if ($currentUser->getEmail() !== null) {
                        $this->emailGenerator->sendUserAccountDeletionConfirmationEmail($currentUser);
                    } elseif ($currentUser->getPhoneNumber() instanceof PhoneNumber) {
                        $message = $this->translator->trans('sms_account_deletion', [], 'UserDeletionService');
                        $this->sendSMS->sendSmsNoValidation($currentUser, $message);
                    }
                } catch (Throwable) {
                    // non-fatal — deletion continues regardless
                }

                $result = $this->userDeletionService->deleteUser(
                    $currentUser,
                    $userExternalAuths,
                    $request,
                    $currentUser
                );

                if (!$result['success'] || $result['success'] !== true) {
                    throw new RuntimeException($result['message']);
                }

                return $this->redirectToRoute('app_logout');
            }

            // Checks if the user has a "forgot_password_request", if yes, return to the password-reset form
            if ($currentUser->isForgotPasswordRequest()) {
                $this->addFlash(
                    'error',
                    $this->translator->trans('confirmNewPasswordBeforeDownloadProfile', [], 'controllers')
                );
                return $this->redirectToRoute('app_site_forgot_password_checker');
            }
            if ($currentUser->getDeletedAt()) {
                return $this->redirectToRoute('app_logout');
            }

            // Check if the user is verified
            if (
                $data[SettingName::LOGIN_WITH_UUID_ONLY->value]['value'] === 'false' &&
                !empty($userExternalAuths) &&
                $userExternalAuths[0]->getProvider() === UserProvider::PORTAL_ACCOUNT->value &&
                !$session->has(SessionStatus::VERIFIED->value)
            ) {
                if (
                    $this->twoFAService->canValidationCode(
                        $currentUser,
                        AnalyticalEventType::LOGIN_WITH_UUID_ONLY_CODE->value
                    )
                ) {
                    $this->twoFAService->generate2FACode(
                        $currentUser,
                        $request->getClientIp(),
                        $request->headers->get('User-Agent'),
                        AnalyticalEventType::LOGIN_WITH_UUID_ONLY_CODE->value
                    );
                    $this->addFlash(
                        'success',
                        $this->translator->trans('codeSentSuccessfully', [], 'controllers')
                    );
                    return $this->redirectToRoute('app_login_confirmation');
                }
                $interval_minutes = $this->twoFAService->timeLeftToResendCode(
                    $currentUser,
                    AnalyticalEventType::LOGIN_WITH_UUID_ONLY_CODE->value
                );
                $this->addFlash(
                    'error',
                    $this->translator->trans(
                        'codeAlreadySent',
                        [
                            '%minutes%' => $interval_minutes
                        ],
                        'controllers'
                    )
                );
                return $this->redirectToRoute('app_login_confirmation');
            }

            // --- 2FA enforcement (TWO_FACTOR_AUTH_STATUS) ---
            // A UUID-only (magic-link) login for a portal account already verified the user via
            // email/SMS during the login flow itself, so forcing 2FA setup on top of that is
            // redundant. Enforcement therefore only applies to traditional password logins and to
            // SSO logins — it is independent of the UUID-only / external-provider branching below.
            $isPortalAccount = !empty($userExternalAuths)
                && $userExternalAuths[0]->getProvider() === UserProvider::PORTAL_ACCOUNT->value;

            $isUuidOnlyLogin = $session->has('authenticated_via_uuid_only');

            $skipEnforcement = $isPortalAccount && $isUuidOnlyLogin;

            if (!$skipEnforcement) {
                if (
                    $data[SettingName::TWO_FACTOR_AUTH_STATUS->value]['value'] ===
                    TwoFAType::ENFORCED_FOR_LOCAL->value &&
                    $isPortalAccount &&
                    $currentUser->getTwoFAType() === UserTwoFactorAuthenticationStatus::DISABLED->value
                ) {
                    return $this->redirectToRoute('app_configure2FA');
                }

                if (
                    $data[SettingName::TWO_FACTOR_AUTH_STATUS->value]['value']
                    === TwoFAType::ENFORCED_FOR_ALL->value &&
                    $currentUser->getTwoFAType() === UserTwoFactorAuthenticationStatus::DISABLED->value
                ) {
                    return $this->redirectToRoute('app_configure2FA');
                }
            }

            if (
                $data[SettingName::LOGIN_WITH_UUID_ONLY->value]["value"] === 'true' ||
                (!$currentUser->getUserExternalAuths()->isEmpty() && $currentUser->getUserExternalAuths(
                    )[0]->getProvider() !== UserProvider::PORTAL_ACCOUNT->value)
            ) {
                if (
                    $currentUser->getTwoFAType() !==
                    UserTwoFactorAuthenticationStatus::DISABLED->value &&
                    !$session->has('2fa_verified_landing')
                ) {
                    if (
                        $currentUser->getTwoFAType() ===
                        UserTwoFactorAuthenticationStatus::SMS->value
                    ) {
                        return $this->redirectToRoute('app_2FA_generate_code');
                    }
                    if (
                        $currentUser->getTwoFAType() ===
                        UserTwoFactorAuthenticationStatus::EMAIL->value
                    ) {
                        return $this->redirectToRoute('app_2FA_generate_code');
                    }
                    if (
                        $currentUser->getTwoFAType() ===
                        UserTwoFactorAuthenticationStatus::TOTP->value
                    ) {
                        return $this->redirectToRoute('app_verify2FA_TOTP');
                    }
                }
                // Check if the user has OTPCodes
                if (
                    $currentUser->getTwoFAtype() !== UserTwoFactorAuthenticationStatus::DISABLED->value &&
                    !$this->twoFAService->hasValidOTPCodes($currentUser)
                ) {
                    return $this->redirectToRoute('app_otpCodes');
                }
            }
        }

        // Check if the current user has a provider
        $externalAuthsData = [];
        if (!empty($userExternalAuths) && $currentUser) {
            // Populate the externalAuthsData array
            foreach ($userExternalAuths as $userExternalAuth) {
                $externalAuthsData[$currentUser->getId()][] = [
                    'provider' => $userExternalAuth->getProvider(),
                    'providerId' => $userExternalAuth->getProviderId(),
                ];
            }
        }

        $userAgent = $request->headers->get('User-Agent');
        $actionName = $request->attributes->get('_route');

        // Prepare Forms before any action
        $form = $this->createForm(AccountUserUpdateLandingType::class, $currentUser);

        $passwordDTO = new NewPasswordAccountDTO();

        $formPassword = $this->createForm(
            NewPasswordAccountType::class,
            $passwordDTO
        );

        $formRegistrationDemo = $this->createForm(RegistrationFormType::class, $this->getUser());
        $formRevokeProfiles = $this->createForm(RevokeProfilesType::class, $this->getUser());
        $formTOS = $this->createForm(TOSType::class);

        if ($data[SettingName::PLATFORM_MODE->value]['value'] === PlatformMode::DEMO->value) {
            if ($request->isMethod('POST') && !$this->getUser()) {
                $formRegistrationDemo->handleRequest($request);
                if ($formRegistrationDemo->isSubmitted() && $formRegistrationDemo->isValid()) {
                    $payload = $request->request->all();
                    if ($data[SettingName::TURNSTILE_CHECKER->value]['value'] === OperationMode::ON->value) {
                        $turnstileResponse = $request->request->get('cf-turnstile-response');
                        // Validate the Turnstile CAPTCHA
                        if (empty($turnstileResponse)) {
                            $this->addFlash(
                                'error',
                                $this->translator->trans('invalidCaptcha', [], 'landing')
                            );

                            return $this->redirectToRoute('app_landing');
                        }
                    }

                    if (empty($payload['radio-os']) && empty($payload['detected-os'])) {
                        $this->addFlash('error', $this->translator->trans('selectOperatingSystem', [], 'controllers'));
                    }

                    $userAuths = new UserExternalAuth();
                    /** @var User $user */
                    $user = $formRegistrationDemo->getData();
                    $user->setEmail($user->getEmail());
                    $user->setCreatedAt(new DateTime());
                    $user->setPassword(
                        $this->userPasswordEncoder->hashPassword($user, uniqid("", true))
                    );
                    $user->setUuid(
                        str_replace('@', "-DEMO-" . uniqid("", true) . "-", $user->getEmail())
                    );
                    $userAuths->setProvider(UserProvider::PORTAL_ACCOUNT->value);
                    $userAuths->setProviderId(UserProvider::EMAIL->value);
                    $userAuths->setUser($user);

                    // Save user and auth record to DB
                    $entityManager->persist($user);
                    $entityManager->persist($userAuths);
                    $entityManager->flush();

                    // Defines the Event
                    $eventMetadata = [
                        EventMetadataKeysType::IP->value => $request->getClientIp(),
                        EventMetadataKeysType::USER_AGENT->value => $request->headers->get('User-Agent'),
                        EventMetadataKeysType::UUID->value => $user->getUuid(),
                        EventMetadataKeysType::PLATFORM->value => PlatformMode::DEMO->value,
                        EventMetadataKeysType::REGISTRATION_TYPE->value => UserProvider::EMAIL->value,
                    ];
                    $this->eventActions->saveEvent(
                        $user,
                        AnalyticalEventType::USER_CREATION->value,
                        new DateTime(),
                        $eventMetadata
                    );

                    $this->userAuthenticator->authenticateUser(
                        $user,
                        $this->authenticator,
                        $request
                    );

                    // Redirect to landing so the top verification guard handles code generation single-handedly
                    if ($data[SettingName::USER_VERIFICATION->value]['value'] === OperationMode::ON->value) {
                        return $this->redirectToRoute('app_landing');
                    }

                    if ($data[SettingName::USER_VERIFICATION->value]['value'] === OperationMode::OFF->value) {
                        $session->set(SessionStatus::VERIFIED->value, true);
                        return $this->redirectToRoute('app_landing');
                    }
                }
            }

            if ($request->isMethod('POST') && $this->getUser()) {
                $payload = $request->request->all();
                if (!array_key_exists('radio-os', $payload)) {
                    if (!array_key_exists('detected-os', $payload)) {
                        $os = $request->query->get('os');
                        if (!empty($os)) {
                            $payload['radio-os'] = $os;
                        } else {
                            return $this->redirectToRoute($actionName);
                        }
                    } else {
                        $payload['radio-os'] = $payload['detected-os'];
                    }
                }

                if ($payload['radio-os'] !== 'none') {
                    /**
                     * Overriding macOS to iOS due to the profiles being the same and there being no route for the macOS
                     * enum value, so the UI shows macOS but on the logic to generate the profile iOS is used instead
                     */
                    $osValue = $payload['radio-os'];
                    if (is_array($osValue)) {
                        // handle array case safely; for example, pick the first value
                        $osValue = reset($osValue) ?: '';
                    } elseif (!is_string($osValue)) {
                        // fallback for non-string types
                        $osValue = (string)$osValue;
                    }

                    if ($osValue === OSType::MACOS->value) {
                        $osValue = OSType::IOS->value;
                    }

                    return $this->redirectToRoute(
                        'profile_' . strtolower((string)$osValue),
                        ['os' => $osValue]
                    );
                }
            }
        } elseif ($request->isMethod('POST')) {
            $payload = $request->request->all();
            if (empty($payload['radio-os']) && empty($payload['detected-os'])) {
                $this->addFlash(
                    'error',
                    $this->translator->trans('selectOperatingSystem', [], 'controllers')
                );
            }
            if (!array_key_exists('radio-os', $payload)) {
                if (!array_key_exists('detected-os', $payload)) {
                    $os = $request->query->get('os');
                    if (!empty($os)) {
                        $payload['radio-os'] = $os;
                    } else {
                        return $this->redirectToRoute($actionName);
                    }
                } else {
                    $payload['radio-os'] = $payload['detected-os'];
                }
            }
            if ($payload['radio-os'] !== 'none' && $this->getUser() instanceof UserInterface) {
                /**
                 * Overriding macOS to iOS due to the profiles being the same and there being no route for the macOS
                 * enum value, so the UI shows macOS but on the logic to generate the profile iOS is used instead
                 */
                $osValue = $payload['radio-os'];

                // Ensure $osValue is a string
                if (is_array($osValue)) {
                    $osValue = reset($osValue) ?: '';
                } elseif (!is_string($osValue)) {
                    $osValue = (string)$osValue;
                }

                if ($osValue === OSType::MACOS->value) {
                    $osValue = OSType::IOS->value;
                }

                return $this->redirectToRoute(
                    'profile_' . strtolower((string)$osValue),
                    ['os' => $osValue]
                );
            }
        }

        $os = $request->query->get('os');
        if (!empty($os)) {
            $payload['radio-os'] = $os;
        }

        $data['os'] = [
            'selected' => $payload['radio-os'] ?? $this->OSDetectionService->detectDevice($userAgent),
            'items' => [
                OSType::WINDOWS->value => ['alt' => 'Windows Logo'],
                OSType::IOS->value => ['alt' => 'Apple Logo'],
                OSType::ANDROID->value => ['alt' => 'Android Logo']
            ]
        ];

        if ($data['os']['selected'] === OSType::NONE->value && $currentUser && $currentUser->isVerified()) {
            $this->addFlash(
                'error',
                $this->translator->trans('selectOperatingSystem', [], 'controllers')
            );
        }

        if ($currentUser) {
            return $this->render('landing/authUser/landing_auth_user.html.twig', [
                'form' => $form->createView(),
                'formPassword' => $formPassword->createView(),
                'formRevokeProfiles' => $formRevokeProfiles->createView(),
                'data' => $data,
                'user' => $currentUser,
                'context' => FirewallType::LANDING->value,
            ]);
        }

        return $this->render('landing/landing.html.twig', [
            'form' => $form->createView(),
            'formPassword' => $formPassword->createView(),
            'formTOS' => $formTOS,
            'formRevokeProfiles' => $formRevokeProfiles->createView(),
            'registrationFormDemo' => $formRegistrationDemo->createView(),
            'data' => $data,
            'userExternalAuths' => $externalAuthsData,
            'user' => $currentUser,
            'context' => FirewallType::LANDING->value,
        ]);
    }

    #[Route('/app/continue', name: 'app_api_landing')]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function appApiLanding(Request $request): Response
    {
        $session = $request->getSession();
        $appReturn = $session->get('app_return');

        // Check if session exists
        if (!$appReturn) {
            throw $this->createAccessDeniedException(
                $this->translator->trans(
                    'access_denied_no_session',
                    [],
                    'controllers'
                )
            );
        }

        /** @var array<string, array{value: string, description: string}> $data */
        $data = $this->getSettings->getSettings();

        // Check if RETURN_APPS_ENABLED is true
        $returnAppsEnabled = $data[SettingName::RETURN_APPS_ENABLED->value]['value'] ?? OperationMode::OFF->value;
        if ($returnAppsEnabled !== OperationMode::ON->value) {
            throw $this->createAccessDeniedException(
                $this->translator->trans(
                    'access_denied_feature_disabled',
                    [],
                    'controllers'
                )
            );
        }

        // Check if session is still valid (TTL)
        $timestamp = $appReturn['timestamp'] ?? 0;
        $ttl = $appReturn['ttl'] ?? 0;
        if ((time() - $timestamp) > $ttl) {
            throw $this->createAccessDeniedException(
                $this->translator->trans(
                    'access_denied_session_expired',
                    [],
                    'controllers'
                )
            );
        }

        /** @var User $currentUser */
        $currentUser = $this->getUser();

        // Prepare forms
        $form = $this->createForm(AccountUserUpdateLandingType::class, $currentUser);

        $passwordDTO = new NewPasswordAccountDTO();

        $formPassword = $this->createForm(
            NewPasswordAccountType::class,
            $passwordDTO
        );

        $formRevokeProfiles = $this->createForm(RevokeProfilesType::class, $currentUser);

        return $this->render('landing/authUser/landing_api_auth_user.html.twig', [
            'form' => $form->createView(),
            'formPassword' => $formPassword->createView(),
            'formRevokeProfiles' => $formRevokeProfiles->createView(),
            'data' => $data,
            'user' => $currentUser,
        ]);
    }

    /**
     * Widget with data about the account of the user / upload new password
     *
     * @throws Exception
     */
    #[Route('/account/user', name: 'app_landing_account_user', methods: ['POST'])]
    public function accountUser(
        Request $request,
    ): Response {
        /** @var User $user */
        $user = $this->getUser();
        $oldFirstName = $user->getFirstName();
        $oldLastName = $user->getLastName();

        $formRevokeProfiles = $this->createForm(RevokeProfilesType::class, $user);
        $formRevokeProfiles->handleRequest($request);

        if ($formRevokeProfiles->isSubmitted() && $formRevokeProfiles->isValid()) {
            $revokeProfiles = $this->profileManager->disableProfiles(
                $user,
                UserRadiusProfileRevokeReason::USER_REVOKED_PROFILE->value,
                true
            );
            if (!$revokeProfiles) {
                $this->addFlash(
                    'error',
                    $this->translator->trans('accountWithoutProfilesAssociated', [], 'controllers')
                );
                return $this->redirectToRoute('app_landing');
            }
            $eventMetaData = [
                EventMetadataKeysType::IP->value => $request->getClientIp(),
                EventMetadataKeysType::USER_AGENT->value => $request->headers->get('User-Agent'),
                EventMetadataKeysType::UUID->value => $user->getUuid(),
            ];
            $this->eventActions->saveEvent(
                $user,
                AnalyticalEventType::USER_REVOKE_PROFILES->value,
                new DateTime(),
                $eventMetaData
            );

            $this->addFlash(
                'success',
                $this->translator->trans('profilesAssociatedRevoked', [], 'controllers')
            );
            return $this->redirectToRoute('app_landing');
        }

        $form = $this->createForm(AccountUserUpdateLandingType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $eventMetaData = [
                EventMetadataKeysType::IP->value => $request->getClientIp(),
                EventMetadataKeysType::USER_AGENT->value => $request->headers->get('User-Agent'),
                EventMetadataKeysType::UUID->value => $user->getUuid(),
                EventMetadataKeysType::USER_OLD_DATA->value => [
                    EventMetadataKeysType::FIRST_NAME->value => $oldFirstName,
                    EventMetadataKeysType::LAST_NAME->value => $oldLastName,
                ],
                EventMetadataKeysType::USER_NEW_DATA->value => [
                    EventMetadataKeysType::FIRST_NAME->value => $user->getFirstName(),
                    EventMetadataKeysType::LAST_NAME->value => $user->getLastName(),
                ],
            ];
            $this->eventActions->saveEvent(
                $user,
                AnalyticalEventType::USER_ACCOUNT_UPDATE->value,
                new DateTime(),
                $eventMetaData
            );

            $this->addFlash(
                'success',
                $this->translator->trans('accountInformationUpdated', [], 'controllers')
            );

            return $this->redirectToRoute('app_landing');
        }

        $passwordDTO = new NewPasswordAccountDTO();
        $formPassword = $this->createForm(
            NewPasswordAccountType::class,
            $passwordDTO
        );
        $formPassword->handleRequest($request);

        if ($formPassword->isSubmitted() && $formPassword->isValid()) {
            $user->setPassword(
                $this->userPasswordEncoder->hashPassword(
                    $user,
                    $passwordDTO->newPassword
                )
            );
            $session = $request->getSession();

            if ($session->has('_security_dashboard')) {
                $session->remove('_security_dashboard');
            }

            $this->entityManager->persist($user);
            $this->entityManager->flush();

            $eventMetaData = [
                EventMetadataKeysType::IP->value => $request->getClientIp(),
                EventMetadataKeysType::USER_AGENT->value => $request->headers->get('User-Agent'),
                EventMetadataKeysType::UUID->value => $user->getUuid(),
            ];
            $this->eventActions->saveEvent(
                $user,
                AnalyticalEventType::USER_ACCOUNT_UPDATE_PASSWORD->value,
                new DateTime(),
                $eventMetaData
            );

            $this->addFlash(
                'success',
                $this->translator->trans(
                    'passwordUpdatedSuccessfully',
                    [],
                    'controllers'
                )
            );

            // Redirect upon successful password update
            return $this->redirectToRoute('app_landing');
        }

        // Populate required template data ($data['os']) before rendering validation errors
        /** @var array<string, mixed> $data */
        $data = $this->getSettings->getSettings();
        $userAgent = $request->headers->get('User-Agent');
        $data['os'] = [
            'selected' => $this->OSDetectionService->detectDevice($userAgent),
            'items' => [
                OSType::WINDOWS->value => ['alt' => 'Windows Logo'],
                OSType::IOS->value => ['alt' => 'Apple Logo'],
                OSType::ANDROID->value => ['alt' => 'Android Logo']
            ]
        ];

        return $this->render('landing/authUser/landing_auth_user.html.twig', [
            'form' => $form->createView(),
            'formPassword' => $formPassword->createView(),
            'formRevokeProfiles' => $formRevokeProfiles->createView(),
            'data' => $data,
            'user' => $user,
            'context' => FirewallType::LANDING->value,
        ], new Response(null, Response::HTTP_UNPROCESSABLE_ENTITY));
    }
}
