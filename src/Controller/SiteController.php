<?php

namespace App\Controller;

use App\Entity\Setting;
use App\Entity\TextEditor;
use App\Entity\User;
use App\Entity\UserExternalAuth;
use App\Enum\AnalyticalEventType;
use App\Enum\FirewallType;
use App\Enum\OperationMode;
use App\Enum\OSTypes;
use App\Enum\PlatformMode;
use App\Enum\TextEditorName;
use App\Enum\TextInputType;
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
use App\Service\ProfileManager;
use App\Service\TwoFAService;
use App\Service\UserDeletionService;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Http\Authentication\UserAuthenticatorInterface;

/**
 * @method getParameterBag()
 */
class SiteController extends AbstractController
{
    /**
     * SiteController constructor.
     *
     * @param UserExternalAuthRepository $userExternalAuthRepository The repository required to fetch the provider.
     * @param GetSettings $getSettings The instance of GetSettings class.
     * @param EventActions $eventActions Used to generate event related to the User creation
     * of the user account
     * @param ProfileManager $profileManager Calls the functions to enable/disable provisioning profiles
     * @param TwoFAService $twoFAService Calls the functions to manage the 2fa configuration request
     * @param UserDeletionService $userDeletionService Calls the functions responsible for user account deletion
     * @param EntityManagerInterface $entityManager Call the symfony responsible bundle for data submission to DB
     */
    public function __construct(
        private readonly UserExternalAuthRepository $userExternalAuthRepository,
        private readonly GetSettings $getSettings,
        private readonly EventActions $eventActions,
        private readonly ProfileManager $profileManager,
        private readonly TwoFAService $twoFAService,
        private readonly UserDeletionService $userDeletionService,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    #[Route('/', name: 'app_landing')]
    public function landing(
        Request $request,
        UserPasswordHasherInterface $userPasswordEncoder,
        UserAuthenticatorInterface $userAuthenticator,
        LandingAuthenticator $authenticator,
        EntityManagerInterface $entityManager,
        RequestStack $requestStack
    ): Response {
        // Call the getSettings method of GetSettings class to retrieve the data
        $data = $this->getSettings->getSettings();
        /** @var User $currentUser */
        $currentUser = $this->getUser();
        $session = $request->getSession();

        // Check if the user is logged in and verification of the user
        // And check if the user doesn't have a forgot_password_request active
        if (
            isset($data["USER_VERIFICATION"]["value"]) &&
            $data["USER_VERIFICATION"]["value"] === OperationMode::ON->value &&
            $currentUser
        ) {
            // Retrieve the cookie about SAML_ACCOUNT Deletion from the request
            $previousLoggedID = $request->cookies->get('previousLoggedID');

            // $previousLoggedID it's a string
            // currentUser->getID it's an int
            if ($previousLoggedID && $previousLoggedID == $currentUser->getId()) {
                $userExternalAuths = $this->userExternalAuthRepository->findBy(['user' => $currentUser]);
                $this->userDeletionService->deleteUser(
                    $currentUser,
                    $userExternalAuths,
                    $request,
                    $currentUser
                );

                return $this->redirectToRoute('app_logout');
            }

            // Checks if the user has a "forgot_password_request", if yes, return to password reset form
            if ($currentUser->isForgotPasswordRequest()) {
                $this->addFlash(
                    'error',
                    'You need to confirm the new password before download a profile!'
                );
                return $this->redirectToRoute('app_site_forgot_password_checker');
            }
            if ($currentUser->getDeletedAt()) {
                return $this->redirectToRoute('app_logout');
            }

            // Check if the user is verified
            if (!$session->has('session_verified') && !$currentUser->isVerified()) {
                return $this->redirectToRoute('app_login_confirmation');
            }

            // Checks the 2FA status of the platform if mandatory forces the user to configure it
            if (
                $currentUser->getUserExternalAuths() &&
                ($data['TWO_FACTOR_AUTH_STATUS']['value'] ===
                    TwoFAType::ENFORCED_FOR_LOCAL->value &&
                    $currentUser->getUserExternalAuths()->get(0)->getProvider() ===
                    UserProvider::PORTAL_ACCOUNT->value && ($currentUser->getTwoFAType() === null ||
                        $currentUser->getTwoFAType() ===
                        UserTwoFactorAuthenticationStatus::DISABLED->value))
            ) {
                return $this->redirectToRoute('app_configure2FA');
            }
            if (
                $data['TWO_FACTOR_AUTH_STATUS']['value'] === TwoFAType::ENFORCED_FOR_ALL->value &&
                ($currentUser->getTwoFAType() === null ||
                    $currentUser->getTwoFAType() ===
                    UserTwoFactorAuthenticationStatus::DISABLED->value)
            ) {
                return $this->redirectToRoute('app_configure2FA');
            }
        }

        if (
            $currentUser &&
            (
                $currentUser->getTwoFAType() !==
                UserTwoFactorAuthenticationStatus::DISABLED->value &&
                !$session->has('2fa_verified_landing'))
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
        // check if the user have otpCodes
        if (
            $currentUser &&
            $currentUser->getTwoFAtype() !== UserTwoFactorAuthenticationStatus::DISABLED->value &&
            !$this->twoFAService->hasValidOTPCodes($currentUser)
        ) {
            return $this->redirectToRoute('app_otpCodes');
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
        $actionName = $requestStack->getCurrentRequest()->attributes->get('_route');
        if ($data['PLATFORM_MODE']['value']) {
            if ($request->isMethod('POST')) {
                $payload = $request->request->all();
                if (empty($payload['radio-os']) && empty($payload['detected-os'])) {
                    $this->addFlash('error', 'Please select Operating System!');
                } elseif (!$this->getUser() instanceof UserInterface) {
                    $user = new User();
                    $userAuths = new UserExternalAuth();
                    $form = $this->createForm(RegistrationFormType::class, $user);
                    $form->handleRequest($request);
                    if ($form->isSubmitted() && $form->isValid()) {
                        $user = $form->getData();

                        $user->setEmail($user->getEmail());
                        $user->setCreatedAt(new DateTime());
                        $user->setPassword($userPasswordEncoder->hashPassword($user, uniqid("", true)));
                        $user->setUuid(str_replace('@', "-DEMO-" . uniqid("", true) . "-", $user->getEmail()));
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

                        $userAuthenticator->authenticateUser(
                            $user,
                            $authenticator,
                            $request
                        );
                    }

                    if ($data["USER_VERIFICATION"]['value'] === OperationMode::ON->value) {
                        return $this->redirectToRoute('app_login_confirmation');
                    }
                    if ($data["USER_VERIFICATION"]['value'] === OperationMode::OFF->value) {
                        return $this->redirectToRoute('app_landing');
                    }
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
                    if ($payload['radio-os'] === OSTypes::MACOS->value) {
                        $payload['radio-os'] = OSTypes::IOS->value;
                    }
                    return $this->redirectToRoute(
                        'profile_' . strtolower((string)$payload['radio-os']),
                        ['os' => $payload['radio-os']]
                    );
                }
            }
        } elseif ($request->isMethod('POST')) {
            $payload = $request->request->all();
            if (empty($payload['radio-os']) && empty($payload['detected-os'])) {
                $this->addFlash('error', 'Please select Operating System!');
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
            if (
                $payload['radio-os'] !== 'none' && $this->getUser() instanceof UserInterface
            ) {
                /**
                 * Overriding macOS to iOS due to the profiles being the same and there being no route for the macOS
                 * enum value, so the UI shows macOS but on the logic to generate the profile iOS is used instead
                 */
                if ($payload['radio-os'] === OSTypes::MACOS->value) {
                    $payload['radio-os'] = OSTypes::IOS->value;
                }
                return $this->redirectToRoute(
                    'profile_' . strtolower((string)$payload['radio-os']),
                    ['os' => $payload['radio-os']]
                );
            }
        }

        $os = $request->query->get('os');
        if (!empty($os)) {
            $payload['radio-os'] = $os;
        }

        $data['os'] = [
            'selected' => $payload['radio-os'] ?? $this->detectDevice($userAgent),
            'items' => [
                OSTypes::WINDOWS->value => ['alt' => 'Windows Logo'],
                OSTypes::IOS->value => ['alt' => 'Apple Logo'],
                OSTypes::ANDROID->value => ['alt' => 'Android Logo']
            ]
        ];

        if ($data['os']['selected'] === OSTypes::NONE->value && $currentUser && $currentUser->isVerified()) {
            $this->addFlash('error', 'Please select Operating System!');
        }

        $form = $this->createForm(AccountUserUpdateLandingType::class, $this->getUser());
        $formPassword = $this->createForm(NewPasswordAccountType::class, $this->getUser());
        $formRegistrationDemo = $this->createForm(RegistrationFormType::class, $this->getUser());
        $formRevokeProfiles = $this->createForm(RevokeProfilesType::class, $this->getUser());
        $formTOS = $this->createForm(TOSType::class);

        return $this->render('site/landing.html.twig', [
            'form' => $form->createView(),
            'formPassword' => $formPassword->createView(),
            'formTOS' => $formTOS,
            'formRevokeProfiles' => $formRevokeProfiles->createView(),
            'registrationFormDemo' => $formRegistrationDemo->createView(),
            'data' => $data,
            'userExternalAuths' => $externalAuthsData,
            'user' => $currentUser,
            'context' => FirewallType::LANDING->value
        ]);
    }

    #[Route('/terms-conditions', name: 'app_terms_conditions')]
    public function termsConditions(): RedirectResponse|Response
    {
        // Call the getSettings method of GetSettings class to retrieve the data
        $data = $this->getSettings->getSettings();

        $settingsRepository = $this->entityManager->getRepository(Setting::class);
        $tosFormat = $settingsRepository->findOneBy(['name' => 'TOS']);
        $textEditorRepository = $this->entityManager->getRepository(TextEditor::class);

        if (
            $tosFormat &&
            $tosFormat->getValue() === TextInputType::TEXT_EDITOR->value
        ) {
            $textEditorEntry = $textEditorRepository->findOneBy(['name' => TextEditorName::TOS->value]);
            $content = $textEditorEntry !== null ? $textEditorEntry->getContent() : '';

            return $this->render('site/shared/tos/_tos.html.twig', [
                'content' => $content,
                'data' => $data
            ]);
        }

        if (
            $tosFormat &&
            $tosFormat->getValue() === TextInputType::LINK->value &&
            $settingsRepository->findOneBy(['name' => 'TOS_LINK'])
        ) {
            $tosLink = $settingsRepository->findOneBy(['name' => 'TOS_LINK'])->getValue();
            return $this->redirect($tosLink);
        }

        return $this->redirectToRoute('app_landing');
    }

    #[Route('/privacy-policy', name: 'app_privacy_policy')]
    public function privacyPolicy(): RedirectResponse|Response
    {
        // Call the getSettings method of GetSettings class to retrieve the data
        $data = $this->getSettings->getSettings();

        $settingsRepository = $this->entityManager->getRepository(Setting::class);
        $textEditorRepository = $this->entityManager->getRepository(TextEditor::class);
        $privacyPolicyFormat = $settingsRepository->findOneBy(['name' => 'PRIVACY_POLICY']);

        if (
            $privacyPolicyFormat &&
            $privacyPolicyFormat->getValue() === TextInputType::TEXT_EDITOR->value
        ) {
            $privacyPolicyEntry = $textEditorRepository->findOneBy(['name' => TextEditorName::PRIVACY_POLICY->value]);
            $content = $privacyPolicyEntry !== null ? $privacyPolicyEntry->getContent() : '';

            return $this->render('site/shared/tos/_privacy_policy.html.twig', [
                'content' => $content,
                'data' => $data
            ]);
        }

        if (
            $privacyPolicyFormat &&
            $privacyPolicyFormat->getValue() === TextInputType::LINK->value &&
            $settingsRepository->findOneBy(['name' => 'PRIVACY_POLICY_LINK'])
        ) {
            $privacyPolicyLink = $settingsRepository->findOneBy(['name' => 'PRIVACY_POLICY_LINK'])->getValue();
            return $this->redirect($privacyPolicyLink);
        }

        return $this->redirectToRoute('app_landing');
    }


    /**
     * Widget with data about the account of the user / upload new password
     *
     * @return RedirectResponse
     * @throws Exception
     */
    #[Route('/account/user', name: 'app_site_account_user', methods: ['POST'])]
    public function accountUser(
        Request $request,
        UserPasswordHasherInterface $passwordHasher,
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
                $this->addFlash('error', 'This account doesn\'t have profiles associated!');
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

            $this->addFlash('success', 'Your profiles associated with this account have been revoked.');
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

            $this->addFlash('success', 'Your account information has been updated');

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
                $this->addFlash('error', 'Current password Invalid. Please try again.');
                return $this->redirectToRoute('app_landing');
            }

            if ($formPassword->get('newPassword')->getData() !== $formPassword->get('confirmPassword')->getData()) {
                $this->addFlash(
                    'error',
                    'Please make sure to type the same password on both fields. 
                    If the problem keep occurring contact our support!'
                );
                return $this->redirectToRoute('app_landing');
            }

            $user->setPassword($passwordHasher->hashPassword($user, $formPassword->get('newPassword')->getData()));
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

            $this->addFlash('success', 'Your password has been updated successfully!');
        }

        return $this->redirectToRoute('app_landing');
    }

    private function detectDevice($userAgent): string
    {
        $os = OSTypes::NONE->value;

        // Windows
        if (preg_match('/windows|win32/i', (string)$userAgent)) {
            $os = OSTypes::WINDOWS->value;
        }

        // macOS
        if (preg_match('/macintosh|mac os x/i', (string)$userAgent)) {
            $os = OSTypes::MACOS->value;
        }

        // iOS
        if (preg_match('/iphone|ipod|ipad/i', (string)$userAgent)) {
            $os = OSTypes::IOS->value;
        }

        // Android
        if (preg_match('/android/i', (string)$userAgent)) {
            $os = OSTypes::ANDROID->value;
        }

        // Linux
//        if (preg_match('/linux/i', $userAgent)) {
//            $os = OSTypes::LINUX;
//        }

        return $os;
    }
}
