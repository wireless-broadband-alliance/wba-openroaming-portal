<?php

namespace App\Controller;

use App\Entity\Event;
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
use App\Repository\EventRepository;
use App\Repository\SettingRepository;
use App\Repository\UserExternalAuthRepository;
use App\Repository\UserRepository;
use App\Security\LandingAuthenticator;
use App\Service\EventActions;
use App\Service\GetSettings;
use App\Service\ProfileManager;
use App\Service\SendSMS;
use App\Service\TwoFAService;
use App\Service\UserDeletionService;
use App\Service\VerificationCodeEmailGenerator;
use DateInterval;
use DateTime;
use DateTimeInterface;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\NonUniqueResultException;
use Exception;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Security\Http\Authentication\UserAuthenticatorInterface;

/**
 * @method getParameterBag()
 */
class SiteController extends AbstractController
{
    /**
     * SiteController constructor.
     *
     * @param UserRepository $userRepository The repository for accessing user data.
     * @param UserExternalAuthRepository $userExternalAuthRepository The repository required to fetch the provider.
     * @param SettingRepository $settingRepository The setting repository is used to create the getSettings function.
     * @param GetSettings $getSettings The instance of GetSettings class.
     * @param EventActions $eventActions Used to generate event related to the User creation
     * @param VerificationCodeEmailGenerator $verificationCodeGenerator Generates a new verification code
     * of the user account
     * @param ProfileManager $profileManager Calls the functions to enable/disable provisioning profiles
     */
    public function __construct(
        private readonly UserRepository $userRepository,
        private readonly UserExternalAuthRepository $userExternalAuthRepository,
        private readonly SettingRepository $settingRepository,
        private readonly GetSettings $getSettings,
        private readonly EventActions $eventActions,
        private readonly VerificationCodeEmailGenerator $verificationCodeGenerator,
        private readonly ProfileManager $profileManager,
        private readonly TwoFAService $twoFAService,
        private readonly UserDeletionService $userDeletionService,
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
        $data = $this->getSettings->getSettings($this->userRepository, $this->settingRepository);
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
                return $this->redirectToRoute('app_email_code');
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
                        return $this->redirectToRoute('app_regenerate_email_code');
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
    public function termsConditions(EntityManagerInterface $em): RedirectResponse|Response
    {
        // Call the getSettings method of GetSettings class to retrieve the data
        $data = $this->getSettings->getSettings($this->userRepository, $this->settingRepository);

        $settingsRepository = $em->getRepository(Setting::class);
        $tosFormat = $settingsRepository->findOneBy(['name' => 'TOS']);
        $textEditorRepository = $em->getRepository(TextEditor::class);
        if (
            $tosFormat &&
            $tosFormat->getValue() === TextInputType::TEXT_EDITOR->value
        ) {
            if ($textEditorRepository->findOneBy(['name' => TextEditorName::TOS->value]) !== null) {
                $content = $textEditorRepository->findOneBy(['name' => TextEditorName::TOS->value])->getContent();
            } else {
                $content = '';
            }
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
            return $this->redirect($settingsRepository->findOneBy(['name' => 'TOS_LINK'])->getValue());
        }
        return $this->redirectToRoute('app_landing');
    }

    #[Route('/privacy-policy', name: 'app_privacy_policy')]
    public function privacyPolicy(EntityManagerInterface $em): RedirectResponse|Response
    {
        // Call the getSettings method of GetSettings class to retrieve the data
        $data = $this->getSettings->getSettings($this->userRepository, $this->settingRepository);

        $settingsRepository = $em->getRepository(Setting::class);
        $textEditorRepository = $em->getRepository(TextEditor::class);
        $privacyPolicyFormat = $settingsRepository->findOneBy(['name' => 'PRIVACY_POLICY']);
        if (
            $privacyPolicyFormat &&
            $privacyPolicyFormat->getValue() === TextInputType::TEXT_EDITOR->value
        ) {
            if ($textEditorRepository->findOneBy(['name' => TextEditorName::PRIVACY_POLICY->value]) !== null) {
                $content = $textEditorRepository->findOneBy(
                    ['name' => TextEditorName::PRIVACY_POLICY->value]
                )->getContent();
            } else {
                $content = '';
            }
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
            return $this->redirect($settingsRepository->findOneBy(['name' => 'PRIVACY_POLICY_LINK'])->getValue());
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
        EntityManagerInterface $em,
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

            $em->persist($user);
            $em->flush();

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

    /**
     * Regenerate the verification code for the user and send a new email.
     *
     * @return RedirectResponse A redirect response.
     * @throws TransportExceptionInterface
     * @throws \DateMalformedStringException
     * @throws NonUniqueResultException
     */
    #[Route('/email/regenerate', name: 'app_regenerate_email_code')]
    #[IsGranted('ROLE_USER')]
    public function regenerateCode(
        EventRepository $eventRepository,
        MailerInterface $mailer,
        Request $request
    ): RedirectResponse {
        /** @var User $currentUser */
        $currentUser = $this->getUser();
        $isVerified = $currentUser->isVerified();

        if (!$isVerified) {
            $latestEvent = $eventRepository->findLatestRequestAttemptEvent(
                $currentUser,
                AnalyticalEventType::USER_EMAIL_ATTEMPT->value
            );
            $minInterval = new DateInterval('PT2M');
            $currentTime = new DateTime();

            // Check if enough time has passed since the last attempt
            $latestEventMetadata = $latestEvent instanceof Event ? $latestEvent->getEventMetadata() : [];
            $lastVerificationCodeTime = isset($latestEventMetadata['lastVerificationCodeTime'])
                ? new DateTime($latestEventMetadata['lastVerificationCodeTime'])
                : null;
            $verificationAttempts = $latestEventMetadata['verificationAttempts'] ?? 0;

            if (
                !$latestEvent || ($lastVerificationCodeTime instanceof DateTime &&
                    $lastVerificationCodeTime->add($minInterval) < $currentTime)
            ) {
                // Increment the attempt count
                $attempts = $verificationAttempts + 1;

                $email = $this->verificationCodeGenerator->createEmailLanding($currentUser);
                $mailer->send($email);

                // Save event with attempt count and current time
                if (!$latestEvent instanceof Event) {
                    $latestEvent = new Event();
                    $latestEvent->setUser($currentUser);
                    $latestEvent->setEventDatetime(new DateTime());
                    $latestEvent->setEventName(AnalyticalEventType::USER_EMAIL_ATTEMPT->value);
                    $latestEventMetadata = [
                        'platform' => PlatformMode::LIVE->value,
                        'uuid' => $currentUser->getEmail(),
                        'ip' => $request->getClientIp(),
                    ];
                }

                $latestEventMetadata['lastVerificationCodeTime'] = $currentTime->format(DateTimeInterface::ATOM);
                $latestEventMetadata['verificationAttempts'] = $attempts;
                $latestEvent->setEventMetadata($latestEventMetadata);

                $eventRepository->save($latestEvent, true);

                $message = sprintf('We have sent you a new code to: %s.', $currentUser->getEmail());
                $this->addFlash('success', $message);
            } else {
                // Inform the user to wait before trying again
                $this->addFlash('error', 'Please wait 2 minutes before trying again.');
            }
        }

        return $this->redirectToRoute('app_landing');
    }

    /**
     * @throws Exception
     */
    #[Route('/email', name: 'app_email_code')]
    #[IsGranted('ROLE_USER')]
    public function sendCode(): Response
    {
        // Call the getSettings method of GetSettings class to retrieve the data
        $data = $this->getSettings->getSettings($this->userRepository, $this->settingRepository);

        // Get the current user
        /** @var User $currentUser */
        $currentUser = $this->getUser();

        if (!$currentUser) {
            $this->addFlash('error', 'You can only access this page logged in.');
            return $this->redirectToRoute('app_landing');
        }

        if (!$currentUser->isVerified()) {
            $formTOS = $this->createForm(TOSType::class);
            // Render the template with the verification code
            return $this->render('site/landing.html.twig', [
                'data' => $data,
                'formTOS' => $formTOS,
                'user' => $currentUser
            ]);
        }

        // User is already verified, render the landing template
        return $this->redirectToRoute('app_landing');
    }

    #[Route('/email/check', name: 'app_check_email_code')]
    #[IsGranted('ROLE_USER')]
    public function verifyCode(
        RequestStack $requestStack,
        UserRepository $userRepository,
        Request $request
    ): Response {
        // Get the current user
        /** @var User $currentUser */
        $currentUser = $this->getUser();
        $session = $request->getSession();

        if (!$currentUser) {
            $this->addFlash('error', 'You can only access this page logged in.');
            return $this->redirectToRoute('app_landing');
        }

        // Checks if the user has a "forgot_password_request", if yes, return to password reset form
        if ($this->userRepository->findOneBy(['id' => $currentUser->getId(), 'forgot_password_request' => true])) {
            $this->addFlash('error', 'You need to confirm the new password before download a profile!');
            return $this->redirectToRoute('app_site_forgot_password_checker');
        }

        // Get the entered code from the form
        $enteredCode = $requestStack->getCurrentRequest()->request->get('code');

        if ($enteredCode === $currentUser->getVerificationCode()) {
            // Set the user as verified
            $currentUser->setVerificationCode(random_int(100000, 999999));
            $currentUser->setIsVerified(true);
            $session->set('session_verified', true);
            $userRepository->save($currentUser, true);

            $eventMetadata = [
                'ip' => $request->getClientIp(),
                'user_agent' => $request->headers->get('User-Agent'),
                'platform' => PlatformMode::LIVE->value,
                'uuid' => $currentUser->getUuid(),
            ];
            $this->eventActions->saveEvent(
                $currentUser,
                AnalyticalEventType::USER_VERIFICATION->value,
                new DateTime(),
                $eventMetadata
            );

            $this->addFlash('success', 'Your account is now successfully verified');
            return $this->redirectToRoute('app_landing');
        }

        // Code is incorrect, display error message and redirect again to the check email page
        $this->addFlash('error', 'The verification code is incorrect. Please try again.');
        return $this->redirectToRoute('app_email_code');
    }


    /**
     * Regenerate the verification code for the user and send a new SMS.
     *
     * @return RedirectResponse A redirect response.
     * @throws Exception
     * @throws \Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface
     */
    #[Route('/sms/regenerate', name: 'app_regenerate_sms_code')]
    #[IsGranted('ROLE_USER')]
    public function regenerateCodeSMS(EventRepository $eventRepository, SendSMS $sendSmsService): RedirectResponse
    {
        // Call the getSettings method of GetSettings class to retrieve the data
        $data = $this->getSettings->getSettings($this->userRepository, $this->settingRepository);

        /** @var User $currentUser */
        $currentUser = $this->getUser();

        if (!$currentUser) {
            $this->addFlash('error', 'You can only access this page logged in.');
            return $this->redirectToRoute('app_landing');
        }

        // Checks if the user has a "forgot_password_request", if yes, return to password reset form
        if ($this->userRepository->findOneBy(['id' => $currentUser->getId(), 'forgot_password_request' => true])) {
            $this->addFlash('error', 'You need to confirm the new password before downloading a profile!');
            return $this->redirectToRoute('app_site_forgot_password_checker');
        }

        try {
            $result = $sendSmsService->regenerateSmsCode($currentUser);

            if ($result) {
                // If the service returns true, show the attempts left with a message
                $latestEvent = $eventRepository->findLatestSmsAttemptEvent($currentUser);

                // Check if $latestEvent to avoid null conflicts
                if ($latestEvent instanceof \App\Entity\Event) {
                    $latestEventMetadata = $latestEvent->getEventMetadata();
                    $verificationAttempts = $latestEventMetadata['verificationAttempts'] ?? 0;
                    $attemptsLeft = 3 - $verificationAttempts;

                    $message = sprintf(
                        'We have sent you a new code to: %s. You have %d attempt(s) left.',
                        $currentUser->getPhoneNumber(),
                        $attemptsLeft
                    );
                    $this->addFlash('success', $message);
                }
            } else {
                // If regeneration failed, show an appropriate error message
                $this->addFlash(
                    'error',
                    'Failed to regenerate SMS code. Please, wait '
                    . $data['SMS_TIMER_RESEND']['value']
                    . ' minute(s) before generating a new code.'
                );
            }
        } catch (\RuntimeException $e) {
            // Handle generic exception and display a message to the user
            $this->addFlash('error', $e->getMessage());
        } catch (Exception) {
            // Handle exceptions thrown by the service (e.g., network issues, API errors)
            $this->addFlash('error', 'An error occurred while regenerating the SMS code. Please try again later.');
        }
        return $this->redirectToRoute('app_landing');
    }

    #[Route('/change-locale/{locale}', name: 'change_locale')]
    public function changeLocale(string $locale, Request $request): Response
    {
        // Store the locale in the session
        $request->getSession()->set('_locale', $locale);

        $referer = $request->headers->get('referer', $this->generateUrl('app_landing'));
        return $this->redirect($referer);
    }
}
