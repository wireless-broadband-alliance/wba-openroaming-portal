<?php

namespace App\Controller;

use App\Entity\Event;
use App\Entity\User;
use App\Entity\UserExternalAuth;
use App\Enum\AnalyticalEventType;
use App\Enum\FirewallType;
use App\Enum\OperationMode;
use App\Enum\OSTypes;
use App\Enum\PlatformMode;
use App\Enum\TwoFAType;
use App\Enum\UserProvider;
use App\Enum\UserRadiusProfileRevokeReason;
use App\Enum\UserTwoFactorAuthenticationStatus;
use App\Form\AccountUserUpdateLandingType;
use App\Form\ForgotPasswordEmailType;
use App\Form\ForgotPasswordSMSType;
use App\Form\NewPasswordAccountType;
use App\Form\RegistrationFormType;
use App\Form\RevokeProfilesType;
use App\Form\TOSType;
use App\Repository\EventRepository;
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
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Security\Http\Authentication\UserAuthenticatorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * @method getParameterBag()
 */
class SiteController extends AbstractController
{
    /**
     * SiteController constructor.
     *
     * @param UserRepository $userRepository The repository for accessing user data.
     * @param UserExternalAuthRepository $userExternalAuthRepository The repository is required to fetch the provider.
     * @param ParameterBagInterface $parameterBag The parameter bag for accessing application configuration.
     * @param GetSettings $getSettings The instance of the GetSettings class.
     * @param EventRepository $eventRepository The entity returns the last events data related to each user.
     * @param EventActions $eventActions Used to generate event related to the User creation
     * @param VerificationCodeEmailGenerator $verificationCodeGenerator Generates a new verification code
     * of the user account
     * @param ProfileManager $profileManager Calls the functions to enable/disable provisioning profiles
     * @param SendSMS $sendSMS Call the function to send SMS using BudgetSms api
     */
    public function __construct(
        private readonly UserRepository $userRepository,
        private readonly UserExternalAuthRepository $userExternalAuthRepository,
        private readonly ParameterBagInterface $parameterBag,
        private readonly GetSettings $getSettings,
        private readonly EventRepository $eventRepository,
        private readonly EventActions $eventActions,
        private readonly VerificationCodeEmailGenerator $verificationCodeGenerator,
        private readonly ProfileManager $profileManager,
        private readonly SendSMS $sendSMS,
        private readonly TwoFAService $twoFAService,
        private readonly TranslatorInterface $translator,
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
        $data = $this->getSettings->getSettings();

        /** @var User $currentUser */
        $currentUser = $this->getUser();
        $session = $request->getSession();

        // Check if the user is logged in and verification of the user
        // And check if the user don't have a forgot_password_request active
        if (
            isset($data["USER_VERIFICATION"]["value"]) &&
            $data["USER_VERIFICATION"]["value"] === OperationMode::ON->value &&
            $currentUser
        ) {
            // Retrieve the cookie about SAML_ACCOUNT Deletion from the request
            $previousLoggedID = $request->cookies->get('previousLoggedID');

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

            $verification = $currentUser->isVerified();
            // Check if the user is verified
            if (!$verification) {
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
                if ($data['TURNSTILE_CHECKER']['value'] === OperationMode::ON->value) {
                    $turnstileResponse = $request->request->get('cf-turnstile-response');
                    // Validate the Turnstile CAPTCHA
                    if ((empty($turnstileResponse) && !$this->getUser())) {
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
            $this->addFlash(
                'error',
                $this->translator->trans('selectOperatingSystem', [], 'controllers')
            );
        }

        $form = $this->createForm(AccountUserUpdateLandingType::class, $this->getUser());
        $formPassword = $this->createForm(NewPasswordAccountType::class, $this->getUser());
        $formRegistrationDemo = $this->createForm(RegistrationFormType::class, $this->getUser());
        $formRevokeProfiles = $this->createForm(RevokeProfilesType::class, $this->getUser());
        $formTOS = $this->createForm(TOSType::class);

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

            $user->setPassword($passwordHasher->hashPassword($user, $formPassword->get('newPassword')->getData()));

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

            $this->addFlash(
                'success',
                $this->translator->trans('passwordUpdatedSuccessfully', [], 'controllers')
            );
        }

        return $this->redirectToRoute('app_landing');
    }

    /**
     * @throws TransportExceptionInterface
     * @throws Exception
     */
    #[Route('/forgot-password/email', name: 'app_site_forgot_password_email')]
    public function forgotPasswordUserEmail(
        Request $request,
        UserPasswordHasherInterface $userPasswordHasher,
        EntityManagerInterface $entityManager,
        MailerInterface $mailer
    ): Response {
        if ($this->getUser() instanceof UserInterface) {
            $this->addFlash(
                'error',
                $this->translator->trans('cantAccessThisPageLoggedIn', [], 'controllers')
            );
            return $this->redirectToRoute('app_landing');
        }

        // Call the getSettings method of GetSettings class to retrieve the data
        $data = $this->getSettings->getSettings();

        // Check if the user clicked on the 'sms' variable present only on the SMS authentication buttons
        if ($data['PLATFORM_MODE']['value'] === true) {
            $this->addFlash(
                'error',
                $this->translator->trans('portalInDemoMode', [], 'controllers')
            );
            return $this->redirectToRoute('app_landing');
        }

        if ($data['EMAIL_REGISTER_ENABLED']['value'] !== true) {
            $this->addFlash(
                'error',
                $this->translator->trans('verificationMethodNotEnabled', [], 'controllers')
            );
            return $this->redirectToRoute('app_landing');
        }

        $user = new User();
        $form = $this->createForm(ForgotPasswordEmailType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $user = $this->userRepository->findOneBy(['email' => $user->getEmail()]);
            if ($user) {
                // Check if the provider is "PORTAL_ACCOUNT" and the providerId "EMAIL"
                $userExternalAuths = $this->userExternalAuthRepository->findBy(['user' => $user]);
                $hasValidPortalAccount = false;
                // Check if the user has an external auth with PortalAccount and a valid email as providerId
                foreach ($userExternalAuths as $auth) {
                    if (
                        $auth->getProvider() === UserProvider::PORTAL_ACCOUNT->value &&
                        $auth->getProviderId() === UserProvider::EMAIL->value
                    ) {
                        $hasValidPortalAccount = true;
                        break;
                    }
                }
                if ($hasValidPortalAccount) {
                    $latestEvent = $this->eventRepository->findLatestRequestAttemptEvent(
                        $user,
                        AnalyticalEventType::FORGOT_PASSWORD_EMAIL_REQUEST->value
                    );
                    $minInterval = new DateInterval('PT2M');
                    $currentTime = new DateTime();
                    // Check if enough time has passed since the last attempt
                    $latestEventMetadata = $latestEvent instanceof Event ? $latestEvent->getEventMetadata() : [];
                    $lastVerificationCodeTime = isset($latestEventMetadata['lastVerificationCodeTime'])
                        ? new DateTime($latestEventMetadata['lastVerificationCodeTime'])
                        : null;

                    if (
                        !$latestEvent || ($lastVerificationCodeTime instanceof DateTime &&
                            $lastVerificationCodeTime->add($minInterval) < $currentTime)
                    ) {
                        // Save event with attempt count and current time
                        if (!$latestEvent instanceof Event) {
                            $latestEvent = new Event();
                            $latestEvent->setUser($user);
                            $latestEvent->setEventDatetime(new DateTime());
                            $latestEvent->setEventName(AnalyticalEventType::FORGOT_PASSWORD_EMAIL_REQUEST->value);
                            $latestEventMetadata = [
                                'platform' => PlatformMode::LIVE->value,
                                'ip' => $request->getClientIp(),
                                'uuid' => $user->getUuid(),
                            ];
                        }

                        $latestEventMetadata['lastVerificationCodeTime'] =
                            $currentTime->format(DateTimeInterface::ATOM);
                        $latestEvent->setEventMetadata($latestEventMetadata);

                        $user->setForgotPasswordRequest(true);
                        $user->setIsVerified(true);
                        $this->eventRepository->save($latestEvent, true);

                        $randomPassword = bin2hex(random_bytes(4));
                        $hashedPassword = $userPasswordHasher->hashPassword($user, $randomPassword);
                        $user->setPassword($hashedPassword);
                        $entityManager->persist($user);
                        $entityManager->flush();

                        $email = new TemplatedEmail()
                            ->from(
                                new Address(
                                    $this->parameterBag->get('app.email_address'),
                                    $this->parameterBag->get('app.sender_name')
                                )
                            )
                            ->to($user->getEmail())
                            ->subject(
                                $this->translator->trans(
                                    'subject_forgot_password',
                                    [],
                                    'user_forgot_password_request'
                                )
                            )
                            ->htmlTemplate('email/user_forgot_password_request.html.twig')
                            ->context([
                                'password' => $randomPassword,
                                'forgotPasswordUser' => true,
                                'uuid' => $user->getUuid(),
                                'emailTitle' => $data['title']['value'],
                                'contactEmail' => $data['contactEmail']['value'],
                                'currentPassword' => $randomPassword,
                                'verificationCode' => $user->getVerificationCode(),
                                'context' => FirewallType::LANDING->value,
                            ]);

                        $mailer->send($email);

                        $message = sprintf(
                            $this->translator->trans(
                                'emailSentMessage',
                                ['%email%' => $user->getEmail()],
                                'controllers'
                            )
                        );
                        $this->addFlash('success', $message);
                    } else {
                        // Inform the user to wait before trying again
                        $this->addFlash(
                            'warning',
                            $this->translator->trans('portalInDemoMode', [], 'controllers')
                        );
                    }
                } else {
                    $this->addFlash(
                        'warning',
                        $this->translator->trans('emailNotAssociatedWithValidAccount', [], 'controllers')
                    );
                }
            } else {
                $this->addFlash(
                    'warning',
                    $this->translator->trans('emailDoesntExist', [], 'controllers')
                );
            }
        }
        return $this->render('landing/forgotPassword/forgot_password_email.html.twig', [
            'forgotPasswordEmailForm' => $form->createView(),
            'data' => $data,
            'context' => FirewallType::LANDING->value,
        ]);
    }

    /**
     * @throws Exception
     */
    #[Route('forgot-password/sms',
        name: 'app_site_forgot_password_sms',
    )]
    public function forgotPasswordUserSMS(
        Request $request,
        UserPasswordHasherInterface $userPasswordHasher,
        EntityManagerInterface $entityManager,
    ): Response {
        // Call the getSettings method of GetSettings class to retrieve the data
        $data = $this->getSettings->getSettings();

        if ($this->getUser() instanceof UserInterface) {
            $this->addFlash(
                'error',
                $this->translator->trans('cantAccessThisPageLoggedIn', [], 'controllers')
            );
            return $this->redirectToRoute('app_landing');
        }

        // Check if the user clicked on the 'sms' variable present only on the SMS authentication buttons
        if ($data['PLATFORM_MODE']['value']) {
            $this->addFlash(
                'error',
                $this->translator->trans('portalInDemoMode', [], 'controllers')
            );
            return $this->redirectToRoute('app_landing');
        }

        if ($data['AUTH_METHOD_REGISTER_ENABLED']['value'] !== 'true') {
            $this->addFlash(
                'error',
                $this->translator->trans('verificationMethodNotEnabled', [], 'controllers')
            );
            return $this->redirectToRoute('app_landing');
        }

        $user = new User();
        $form = $this->createForm(ForgotPasswordSMSType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $user = $this->userRepository->findOneBy(['phoneNumber' => $user->getPhoneNumber()]);
            if ($user) {
                $latestEvent = $this->eventRepository->findLatestRequestAttemptEvent(
                    $user,
                    AnalyticalEventType::FORGOT_PASSWORD_SMS_REQUEST->value
                );
                // Retrieve the SMS resend interval from the settings
                $smsResendInterval = $data['SMS_TIMER_RESEND']['value'];
                $minInterval = new DateInterval('PT' . $smsResendInterval . 'M');
                $currentTime = new DateTime();
                // Check if the user has not exceeded the attempt limit
                $latestEventMetadata = $latestEvent instanceof Event ? $latestEvent->getEventMetadata() : [];
                $lastVerificationCodeTime = isset($latestEventMetadata['lastVerificationCodeTime'])
                    ? new DateTime($latestEventMetadata['lastVerificationCodeTime'])
                    : null;
                $verificationAttempts = $latestEventMetadata['verificationAttempts'] ?? 0;
                if (!$latestEvent || $verificationAttempts < 4) {
                    // Check if enough time has passed since the last attempt
                    if (
                        !$latestEvent || ($lastVerificationCodeTime instanceof DateTime &&
                            $lastVerificationCodeTime->add($minInterval) < $currentTime)
                    ) {
                        // Increment the attempt count
                        $attempts = $verificationAttempts + 1;

                        // Save event with attempt count and current time
                        if (!$latestEvent instanceof Event) {
                            $latestEvent = new Event();
                            $latestEvent->setUser($user);
                            $latestEvent->setEventDatetime(new DateTime());
                            $latestEvent->setEventName(AnalyticalEventType::FORGOT_PASSWORD_SMS_REQUEST->value);
                            $latestEventMetadata = [
                                'platform' => PlatformMode::LIVE->value,
                                'ip' => $request->getClientIp(),
                                'uuid' => $user->getUuid(),
                            ];
                        }

                        $latestEventMetadata['lastVerificationCodeTime'] = $currentTime->format(
                            DateTimeInterface::ATOM
                        );
                        $latestEventMetadata['verificationAttempts'] = $attempts;
                        $latestEvent->setEventMetadata($latestEventMetadata);

                        $user->setForgotPasswordRequest(true);
                        $user->setIsVerified(true);
                        $this->eventRepository->save($latestEvent, true);

                        // save a new password hashed on the db for the user
                        $randomPassword = bin2hex(random_bytes(4));
                        $hashedPassword = $userPasswordHasher->hashPassword($user, $randomPassword);
                        $user->setPassword($hashedPassword);
                        $entityManager->persist($user);
                        $entityManager->flush();
                        $recipient = "+" .
                            $user->getPhoneNumber()->getCountryCode() .
                            $user->getPhoneNumber()->getNationalNumber();
                        // Send SMS
                        $message = $this->translator->trans(
                            'newRandomPasswordMessage',
                            ['%password%' => $randomPassword],
                            'controllers'
                        );
                        $this->sendSMS->sendSmsNoValidation($recipient, $message);

                        $attemptsLeft = 3 - $verificationAttempts;
                        $message = $this->translator->trans(
                            'messageSentWithAttemptsLeft',
                            [
                                '%uuid%' => $user->getUuid(),
                                '%attempts%' => $attemptsLeft
                            ],
                            'controllers'
                        );
                        $this->addFlash('success', $message);
                    } else {
                        // Inform the user to wait before trying again
                        $this->addFlash(
                            'warning',
                            $this->translator->trans(
                                'waitBeforeRetry',
                                [
                                    '%minutes%' => $data['SMS_TIMER_RESEND']['value']
                                ],
                                'controllers'
                            )
                        );
                    }
                } else {
                    $this->addFlash(
                        'warning',
                        $this->translator->trans('exceededLimitsRequestForNewPassword', [], 'controllers')
                    );
                }
            } else {
                $this->addFlash(
                    'warning',
                    $this->translator->trans('PhoneNumberDoesntExist', [], 'controllers')
                );
            }
        }
        return $this->render('landing/forgotPassword/forgot_password_sms.html.twig', [
            'forgotPasswordSMSForm' => $form->createView(),
            'data' => $data,
            'context' => FirewallType::LANDING->value
        ]);
    }

    /**
     * @throws Exception
     */
    #[Route('/forgot-password/checker', name: 'app_site_forgot_password_checker')]
    #[IsGranted('ROLE_USER')]
    public function forgotPasswordUserChecker(
        Request $request,
        EntityManagerInterface $entityManager,
        UserPasswordHasherInterface $userPasswordHasher,
    ): Response {
        /** @var User $currentUser */
        $currentUser = $this->getUser();
        if (!$currentUser) {
            $this->addFlash(
                'error',
                $this->translator->trans('onlyAccessThisPageLoggedIn', [], 'controllers')
            );
            return $this->redirectToRoute('app_landing');
        }

        // Call the getSettings method of GetSettings class to retrieve the data
        $data = $this->getSettings->getSettings();

        if ($data['PLATFORM_MODE']['value']) {
            $this->addFlash(
                'error',
                $this->translator->trans('portalInDemoMode', [], 'controllers')
            );
            return $this->redirectToRoute('app_landing');
        }

        if (!$currentUser->isForgotPasswordRequest()) {
            $this->addFlash(
                'error',
                $this->translator->trans('cannotAccessThisPageWithoutValidRequest', [], 'controllers')
            );
            return $this->redirectToRoute('app_landing');
        }

        // Checks if the user has a "forgot_password_request", if not, return to the landing page
        if ($this->userRepository->findOneBy(['id' => $currentUser->getId(), 'forgot_password_request' => false])) {
            $this->addFlash(
                'error',
                $this->translator->trans('cantAccessThisPageWithoutRequest', [], 'controllers')
            );
            return $this->redirectToRoute('app_landing');
        }

        $form = $this->createForm(NewPasswordAccountType::class, $currentUser);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $currentPasswordDB = $currentUser->getPassword();
            $typedPassword = $form->get('password')->getData();

            // Compare the typed password with the hashed password from the database
            if (!password_verify((string)$typedPassword, $currentPasswordDB)) {
                $this->addFlash(
                    'error',
                    $this->translator->trans('passwordInvalid', [], 'controllers')
                );
                return $this->redirectToRoute('app_landing');
            }

            if ($form->get('newPassword')->getData() !== $form->get('confirmPassword')->getData()) {
                $this->addFlash(
                    'error',
                    $this->translator->trans('typeTheSamePasswordBothFields', [], 'controllers')
                );
                return $this->redirectToRoute('app_landing');
            }

            $currentUser->setPassword(
                $userPasswordHasher->hashPassword(
                    $currentUser,
                    $form->get('newPassword')->getData()
                )
            );
            $currentUser->setForgotPasswordRequest(false);
            $entityManager->persist($currentUser);
            $entityManager->flush();

            $eventMetadata = [
                'ip' => $request->getClientIp(),
                'user_agent' => $request->headers->get('User-Agent'),
                'platform' => PlatformMode::LIVE->value,
                'uuid' => $currentUser->getUuid(),
            ];
            $this->eventActions->saveEvent(
                $currentUser,
                AnalyticalEventType::FORGOT_PASSWORD_EMAIL_REQUEST_ACCEPTED->value,
                new DateTime(),
                $eventMetadata
            );

            $this->addFlash(
                'success',
                $this->translator->trans('passwordUpdatedSuccessfully', [], 'controllers')
            );
            return $this->redirectToRoute('app_landing');
        }

        return $this->render('landing/forgotPassword/forgot_password_checker.html.twig', [
            'forgotPasswordChecker' => $form->createView(),
            'data' => $data,
            'context' => FirewallType::LANDING->value,
            'user' => $currentUser,
        ]);
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
     * @throws Exception
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
                $session = $request->getSession();
                $email = $this->verificationCodeGenerator->createEmailLanding(
                    $currentUser,
                    $session->get('_locale')
                );
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

                $message = $this->translator->trans(
                    'emailVerification.newCodeSentEmail',
                    ['%email%' => $currentUser->getEmail()],
                    'landing'
                );
                $this->addFlash('success', $message);
            } else {
                // Inform the user to wait before trying again
                $this->addFlash(
                    'error',
                    $this->translator->trans(
                        'emailVerification.waitBeforeTryingAgain',
                        ['%time%' => 2],
                        'landing'
                    )
                );
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
        $data = $this->getSettings->getSettings();

        if ($data['USER_VERIFICATION']['value'] !== OperationMode::ON->value) {
            return $this->redirectToRoute('app_landing');
        }

        // Get the current user
        /** @var User $currentUser */
        $currentUser = $this->getUser();

        if (!$currentUser) {
            $this->addFlash(
                'error',
                $this->translator->trans(
                    'accessLoggedInOnly',
                    [],
                    'landing'
                )
            );
            return $this->redirectToRoute('app_landing');
        }

        if (!$currentUser->isVerified()) {
            $formTOS = $this->createForm(TOSType::class);
            // Render the template with the verification code
            return $this->render('landing/landing.html.twig', [
                'data' => $data,
                'formTOS' => $formTOS,
                'user' => $currentUser,
                'context' => FirewallType::LANDING->value
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

        if (!$currentUser) {
            $this->addFlash(
                'error',
                $this->translator->trans(
                    'accessLoggedInOnly',
                    [],
                    'landing'
                )
            );
            return $this->redirectToRoute('app_landing');
        }

        // Checks if the user has a "forgot_password_request", if yes, return to the password-reset form
        if ($this->userRepository->findOneBy(['id' => $currentUser->getId(), 'forgot_password_request' => true])) {
            $this->addFlash(
                'error',
                $this->translator->trans(
                    'confirmNewPasswordBeforeDownload',
                    [],
                    'landing'
                )
            );
            return $this->redirectToRoute('app_site_forgot_password_checker');
        }

        // Get the entered code from the form
        $enteredCode = $requestStack->getCurrentRequest()->request->get('code');

        if ($enteredCode === $currentUser->getVerificationCode()) {
            // Set the user as verified
            $currentUser->setIsVerified(true);
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

            $this->addFlash(
                'success',
                $this->translator->trans(
                    'emailVerification.accountSuccessfullyVerified',
                    [],
                    'landing'
                )
            );
            return $this->redirectToRoute('app_landing');
        }

        // Code is incorrect, display the error message and redirect again to the check email page
        $this->addFlash(
            'error',
            $this->translator->trans('verificationCodeIncorrect', [], 'controllers')
        );
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
        $data = $this->getSettings->getSettings();

        /** @var User $currentUser */
        $currentUser = $this->getUser();

        if (!$currentUser) {
            $this->addFlash(
                'error',
                $this->translator->trans('onlyAccessThisPageLoggedIn', [], 'controllers')
            );
            return $this->redirectToRoute('app_landing');
        }

        // Checks if the user has a "forgot_password_request", if yes, return to the password-reset form
        if ($this->userRepository->findOneBy(['id' => $currentUser->getId(), 'forgot_password_request' => true])) {
            $this->addFlash(
                'error',
                $this->translator->trans('confirmNewPasswordBeforeDownloadProfile', [], 'controllers')
            );
            return $this->redirectToRoute('app_site_forgot_password_checker');
        }

        try {
            $result = $sendSmsService->regenerateSmsCode($currentUser);

            if ($result) {
                // If the service returns true, show the attempts left with a message
                $latestEvent = $eventRepository->findLatestSmsAttemptEvent($currentUser);

                // Check if $latestEvent to avoid null conflicts
                if ($latestEvent instanceof Event) {
                    $latestEventMetadata = $latestEvent->getEventMetadata();
                    $verificationAttempts = $latestEventMetadata['verificationAttempts'] ?? 0;
                    $attemptsLeft = 3 - $verificationAttempts;

                    $message = $this->translator->trans(
                        'codeSentWithAttemptsLeft',
                        [
                            '%phone%' => $currentUser->getPhoneNumber(),
                            '%attempts%' => $attemptsLeft
                        ],
                        'controllers'
                    );
                    $this->addFlash('success', $message);
                }
            } else {
                // If regeneration failed, show an appropriate error message
                $this->addFlash(
                    'error',
                    $this->translator->trans(
                        'smsRegenerationError',
                        [
                            '%minutes%' => $data['SMS_TIMER_RESEND']['value']
                        ],
                        'controllers'
                    )
                );
            }
        } catch (\RuntimeException $e) {
            // Handle generic exception and display a message to the user
            $this->addFlash('error', $e->getMessage());
        } catch (Exception) {
            // Handle exceptions thrown by the service (e.g., network issues, API errors)
            $this->addFlash(
                'error',
                $this->translator->trans('errorOccurredWhileRegeneratingSMSCode', [], 'controllers')
            );
        }
        return $this->redirectToRoute('app_landing');
    }
}
