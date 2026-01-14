<?php

namespace App\Controller;

use App\Entity\User;
use App\Entity\UserExternalAuth;
use App\Enum\AnalyticalEventType;
use App\Enum\FirewallType;
use App\Enum\OperationMode;
use App\Enum\OSType;
use App\Enum\PlatformMode;
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
use App\Service\EventActions;
use App\Service\GetSettings;
use App\Service\OSDetectionService;
use App\Service\ProfileManager;
use App\Service\TwoFAService;
use App\Service\UserDeletionService;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Http\Authentication\UserAuthenticatorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

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
    ) {
    }

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

        // Check if the user_verification setting is active
        if ($currentUser) {
            if ($data[SettingName::USER_VERIFICATION->value]["value"] === OperationMode::ON->value) {
                // Retrieve the cookie about SAML_ACCOUNT Deletion from the request
                $previousLoggedID = $request->cookies->get('previousLoggedID');
                $userExternalAuths = $this->userExternalAuthRepository->findBy(['user' => $currentUser]);
                if ($previousLoggedID && $previousLoggedID == $currentUser->getId()) {
                    $this->userDeletionService->deleteUser(
                        $currentUser,
                        $userExternalAuths,
                        $request,
                        $currentUser
                    );

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
                    $userExternalAuths[0]->getProvider() === UserProvider::PORTAL_ACCOUNT->value &&
                    !$session->has('session_verified') &&
                    $data[SettingName::LOGIN_WITH_UUID_ONLY->value]['value'] === OperationMode::OFF->value
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

                if (
                    $data[SettingName::LOGIN_WITH_UUID_ONLY->value]["value"] === OperationMode::OFF->value ||
                    $currentUser->getUserExternalAuths()[0]->getProvider() !== UserProvider::PORTAL_ACCOUNT->value
                ) {
                    // Checks the 2FA status of the platform if mandatory and force the user to configure it
                    if (
                        $data[SettingName::TWO_FACTOR_AUTH_STATUS->value]['value'] ===
                        TwoFAType::ENFORCED_FOR_LOCAL->value &&
                        $currentUser->getUserExternalAuths()->get(0)->getProvider() ===
                        UserProvider::PORTAL_ACCOUNT->value &&
                        $currentUser->getTwoFAType() ===
                        UserTwoFactorAuthenticationStatus::DISABLED->value
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
        }

        // Check if the current user has a provider
        $userExternalAuths = $this->userExternalAuthRepository->findBy(['user' => $currentUser]);
        $externalAuthsData = [];
        if (!empty($userExternalAuths)) {
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
        $form = $this->createForm(AccountUserUpdateLandingType::class, $this->getUser());
        $formPassword = $this->createForm(NewPasswordAccountType::class, $this->getUser());
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
                        $this->addFlash(
                            'error',
                            $this->translator->trans('selectOperatingSystem', [], 'controllers')
                        );
                    }

                    $userAuths = new UserExternalAuth();
                    /** @var User $user */
                    $user = $formRegistrationDemo->getData();
                    $user->setEmail($user->getEmail());
                    $user->setCreatedAt(new DateTime());
                    $user->setPassword(
                        $this->userPasswordEncoder->hashPassword(
                            $user,
                            uniqid("", true)
                        )
                    );
                    $user->setUuid(
                        str_replace(
                            '@',
                            "-DEMO-" .
                            uniqid("", true) .
                            "-",
                            $user->getEmail()
                        )
                    );
                    $userAuths->setProvider(UserProvider::PORTAL_ACCOUNT->value);
                    $userAuths->setProviderId(UserProvider::EMAIL->value);
                    $userAuths->setUser($user);
                    $entityManager->persist($user);
                    $entityManager->persist($userAuths);
                    // Defines the Event to the table
                    $eventMetadata = [
                        'ip' => $request->getClientIp(),
                        'user_agent' => $request->headers->get('User-Agent'),
                        'platform' => PlatformMode::DEMO->value,
                        'uuid' => $user->getUuid(),
                        'registrationType' => UserProvider::EMAIL->value,
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

                    if ($data[SettingName::USER_VERIFICATION->value]['value'] === OperationMode::ON->value) {
                        return $this->redirectToRoute('app_login_confirmation');
                    }

                    if ($data[SettingName::USER_VERIFICATION->value]['value'] === OperationMode::OFF->value) {
                        $session->set('session_verified', true);
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


    /**
     * Widget with data about the account of the user / upload new password
     *
     * @return RedirectResponse
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

        $formRevokeProfiles = $this->createForm(RevokeProfilesType::class, $this->getUser());
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
                'ip' => $request->getClientIp(),
                'user_agent' => $request->headers->get('User-Agent'),
                'platform' => PlatformMode::LIVE->value,
                'uuid' => $user->getUuid(),
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

        $form = $this->createForm(AccountUserUpdateLandingType::class, $this->getUser());
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $eventMetaData = [
                'ip' => $request->getClientIp(),
                'user_agent' => $request->headers->get('User-Agent'),
                'platform' => PlatformMode::LIVE->value,
                'uuid' => $user->getUuid(),
                'Old data' => [
                    'First Name' => $oldFirstName,
                    'Last Name' => $oldLastName,
                ],
                'New data' => [
                    'First Name' => $user->getFirstName(),
                    'Last Name' => $user->getLastName(),
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

            // Redirect the user upon successful form submission
            return $this->redirectToRoute('app_landing');
        }

        $formPassword = $this->createForm(NewPasswordAccountType::class, $this->getUser());
        $formPassword->handleRequest($request);

        if ($formPassword->isSubmitted() && $formPassword->isValid()) {
            /** @var User $user */
            $user = $this->getUser();

            $currentPasswordDB = $user->getPassword();
            $typedPassword = $formPassword->get('password')->getData();

            // Compare the typed password with the hashed password from the database
            if (!password_verify((string)$typedPassword, $currentPasswordDB)) {
                $this->addFlash(
                    'error',
                    $this->translator->trans('passwordInvalid', [], 'controllers')
                );
                return $this->redirectToRoute('app_landing');
            }

            if ($formPassword->get('newPassword')->getData() !== $formPassword->get('confirmPassword')->getData()) {
                $this->addFlash(
                    'error',
                    $this->translator->trans('typeTheSamePasswordBothFields', [], 'controllers')
                );
                return $this->redirectToRoute('app_landing');
            }

            $user->setPassword(
                $this->userPasswordEncoder->hashPassword(
                    $user,
                    $formPassword->get('newPassword')->getData()
                )
            );
            $session = $request->getSession();

            // Check and kill the dashboard session if the admin is logged at both firewalls at the same time
            if ($session->has('_security_dashboard')) {
                $session->remove('_security_dashboard');
            }

            $this->entityManager->persist($user);
            $this->entityManager->flush();

            $eventMetaData = [
                'ip' => $request->getClientIp(),
                'user_agent' => $request->headers->get('User-Agent'),
                'platform' => PlatformMode::LIVE->value,
                'uuid' => $user->getUuid(),
            ];
            $this->eventActions->saveEvent(
                $user,
                AnalyticalEventType::USER_ACCOUNT_UPDATE_PASSWORD->value,
                new DateTime(),
                $eventMetaData
            );

            $this->addFlash(
                'success',
                $this->translator->trans('passwordUpdatedSuccessfully', [], 'controllers')
            );
        }

        return $this->redirectToRoute('app_landing');
    }
}
