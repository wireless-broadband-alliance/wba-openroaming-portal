<?php

namespace App\Controller;

use App\Entity\Event;
use App\Entity\Setting;
use App\Entity\TextEditor;
use App\Entity\User;
use App\Entity\UserExternalAuth;
use App\Enum\AnalyticalEventType;
use App\Enum\EmailConfirmationStrategy;
use App\Enum\OSTypes;
use App\Enum\PlatformMode;
use App\Enum\TextEditorName;
use App\Enum\TextInputType;
use App\Enum\UserProvider;
use App\Form\AccountUserUpdateLandingType;
use App\Form\ForgotPasswordEmailType;
use App\Form\ForgotPasswordSMSType;
use App\Form\NewPasswordAccountType;
use App\Form\RegistrationFormType;
use App\Form\RevokeProfilesType;
use App\Form\TOStype;
use App\Repository\EventRepository;
use App\Repository\SettingRepository;
use App\Repository\UserExternalAuthRepository;
use App\Repository\UserRepository;
use App\Security\PasswordAuthenticator;
use App\Service\EventActions;
use App\Service\GetSettings;
use App\Service\ProfileManager;
use App\Service\SendSMS;
use App\Service\VerificationCodeGenerator;
use DateInterval;
use DateTime;
use DateTimeInterface;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\NonUniqueResultException;
use Exception;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\IsGranted;
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
use Symfony\Component\Mime\Email;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Authentication\UserAuthenticatorInterface;

/**
 * @method getParameterBag()
 */
class SiteController extends AbstractController
{
    private UserRepository $userRepository;
    private UserExternalAuthRepository $userExternalAuthRepository;
    private ParameterBagInterface $parameterBag;
    private SettingRepository $settingRepository;
    private GetSettings $getSettings;
    private EventRepository $eventRepository;
    private EventActions $eventActions;
    private VerificationCodeGenerator $verificationCodeGenerator;
    private ProfileManager $profileManager;
    private SendSMS $sendSMS;

    /**
     * SiteController constructor.
     *
     * @param UserRepository $userRepository The repository for accessing user data.
     * @param UserExternalAuthRepository $userExternalAuthRepository The repository required to fetch the provider.
     * @param ParameterBagInterface $parameterBag The parameter bag for accessing application configuration.
     * @param SettingRepository $settingRepository The setting repository is used to create the getSettings function.
     * @param GetSettings $getSettings The instance of GetSettings class.
     * @param EventRepository $eventRepository The entity returns the last events data related to each user.
     * @param EventActions $eventActions Used to generate event related to the User creation
     * @param VerificationCodeGenerator $verificationCodeGenerator Generates a new verification code of the user account
     * @param ProfileManager $profileManager Calls the functions to enable/disable provisioning profiles
     * @param SendSMS $sendSMS
     */
    public function __construct(
        UserRepository $userRepository,
        UserExternalAuthRepository $userExternalAuthRepository,
        ParameterBagInterface $parameterBag,
        SettingRepository $settingRepository,
        GetSettings $getSettings,
        EventRepository $eventRepository,
        EventActions $eventActions,
        VerificationCodeGenerator $verificationCodeGenerator,
        ProfileManager $profileManager,
        SendSMS $sendSMS
    ) {
        $this->userRepository = $userRepository;
        $this->userExternalAuthRepository = $userExternalAuthRepository;
        $this->parameterBag = $parameterBag;
        $this->settingRepository = $settingRepository;
        $this->getSettings = $getSettings;
        $this->eventRepository = $eventRepository;
        $this->eventActions = $eventActions;
        $this->verificationCodeGenerator = $verificationCodeGenerator;
        $this->profileManager = $profileManager;
        $this->sendSMS = $sendSMS;
    }

    /**
     * @param Request $request
     * @param UserPasswordHasherInterface $userPasswordHasher
     * @param UserAuthenticatorInterface $userAuthenticator
     * @param PasswordAuthenticator $authenticator
     * @param EntityManagerInterface $entityManager
     * @param RequestStack $requestStack
     * @return Response
     */
    #[Route('/', name: 'app_landing')]
    public function landing(
        Request $request,
        UserPasswordHasherInterface $userPasswordHasher,
        UserAuthenticatorInterface $userAuthenticator,
        PasswordAuthenticator $authenticator,
        EntityManagerInterface $entityManager,
        RequestStack $requestStack
    ): Response {
        // Call the getSettings method of GetSettings class to retrieve the data
        $data = $this->getSettings->getSettings($this->userRepository, $this->settingRepository);
        /** @var User $currentUser */
        $currentUser = $this->getUser();

        // Check if the user is logged in and verification of the user
        // And Check if the user dont have a forgot_password_request active
        if (
            isset($data["USER_VERIFICATION"]["value"]) &&
            $data["USER_VERIFICATION"]["value"] === EmailConfirmationStrategy::EMAIL &&
            $this->getUser()
        ) {
            $verification = $currentUser->isVerified();
            // Check if the user is verified
            if (!$verification) {
                return $this->redirectToRoute('app_email_code');
            }
            // Checks if the user has a "forgot_password_request", if yes, return to password reset form
            if ($this->userRepository->findOneBy(['id' => $currentUser->getId(), 'forgot_password_request' => true])) {
                $this->addFlash(
                    'error',
                    'You need to confirm the new password before download a profile!'
                );
                return $this->redirectToRoute('app_site_forgot_password_checker');
            }
            if ($currentUser->getDeletedAt()) {
                return $this->redirectToRoute('saml_logout');
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
        $actionName = $requestStack->getCurrentRequest()->attributes->get('_route');
        if ($data['PLATFORM_MODE']['value']) {
            if ($request->isMethod('POST')) {
                $payload = $request->request->all();
                if (empty($payload['radio-os']) && empty($payload['detected-os'])) {
                    $this->addFlash('error', 'Please select Operating System!');
                } elseif ($this->getUser() === null) {
                    $user = new User();
                    $userAuths = new UserExternalAuth();
                    $form = $this->createForm(RegistrationFormType::class, $user);
                    $form->handleRequest($request);
                    if ($form->isSubmitted() && $form->isValid()) {
                        $user = $form->getData();

                        $user->setEmail($user->getEmail());
                        $user->setCreatedAt(new \DateTime());
                        $user->setPassword($userPasswordHasher->hashPassword($user, uniqid("", true)));
                        $user->setUuid(str_replace('@', "-DEMO-" . uniqid("", true) . "-", $user->getEmail()));
                        $userAuths->setProvider(UserProvider::PORTAL_ACCOUNT);
                        $userAuths->setProviderId(UserProvider::EMAIL);
                        $userAuths->setUser($user);
                        $entityManager->persist($user);
                        $entityManager->persist($userAuths);
                        // Defines the Event to the table
                        $eventMetadata = [
                            'platform' => PlatformMode::DEMO,
                            'uuid' => $user->getUuid(),
                            'ip' => $request->getClientIp(),
                            'registrationType' => UserProvider::EMAIL,
                        ];
                        $this->eventActions->saveEvent(
                            $user,
                            AnalyticalEventType::USER_CREATION,
                            new DateTime(),
                            $eventMetadata
                        );

                        $userAuthenticator->authenticateUser(
                            $user,
                            $authenticator,
                            $request
                        );
                    }

                    if ($data["USER_VERIFICATION"]['value'] === EmailConfirmationStrategy::EMAIL) {
                        return $this->redirectToRoute('app_regenerate_email_code');
                    }
                    if ($data["USER_VERIFICATION"]['value'] === EmailConfirmationStrategy::NO_EMAIL) {
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
                if ($this->getUser() !== null && $payload['radio-os'] !== 'none') {
                    /*
                     * Overriding macOS to iOS due to the profiles being the same and there being no route for the macOS
                     * enum value, so the UI shows macOS but on the logic to generate the profile iOS is used instead
                    */
                    if ($payload['radio-os'] === OSTypes::MACOS) {
                        $payload['radio-os'] = OSTypes::IOS;
                    }
                    return $this->redirectToRoute(
                        'profile_' . strtolower($payload['radio-os']),
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
            if ($this->getUser() !== null && $payload['radio-os'] !== 'none') {
                /*
                    * Overriding macOS to iOS due to the profiles being the same and there being no route for the macOS
                    * enum value, so the UI shows macOS but on the logic to generate the profile iOS is used instead
                   */
                if ($payload['radio-os'] === OSTypes::MACOS) {
                    $payload['radio-os'] = OSTypes::IOS;
                }
                return $this->redirectToRoute(
                    'profile_' . strtolower($payload['radio-os']),
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
                OSTypes::WINDOWS => ['alt' => 'Windows Logo'],
                OSTypes::IOS => ['alt' => 'Apple Logo'],
                OSTypes::ANDROID => ['alt' => 'Android Logo']
            ]
        ];

        if ($data['os']['selected'] == OSTypes::NONE && $currentUser && $currentUser->isVerified()) {
            $this->addFlash('error', 'Please select Operating System!');
        }

        $form = $this->createForm(AccountUserUpdateLandingType::class, $this->getUser());
        $formPassword = $this->createForm(NewPasswordAccountType::class, $this->getUser());
        $formRegistrationDemo = $this->createForm(RegistrationFormType::class, $this->getUser());
        $formRevokeProfiles = $this->createForm(RevokeProfilesType::class, $this->getUser());
        $formTOS = $this->createForm(TOStype::class);

        return $this->render('site/landing.html.twig', [
            'form' => $form->createView(),
            'formPassword' => $formPassword->createView(),
            'formTOS' => $formTOS,
            'formRevokeProfiles' => $formRevokeProfiles->createView(),
            'registrationFormDemo' => $formRegistrationDemo->createView(),
            'data' => $data,
            'userExternalAuths' => $externalAuthsData,
            'user' => $currentUser
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
            $tosFormat->getValue() === TextInputType::TEXT_EDITOR
        ) {
            if ($textEditorRepository->findOneBy(['name' => TextEditorName::TOS])) {
                $content = $textEditorRepository->findOneBy(['name' => TextEditorName::TOS])->getContent();
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
            $tosFormat->getValue() === TextInputType::LINK &&
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
            $privacyPolicyFormat->getValue() === TextInputType::TEXT_EDITOR
        ) {
            if ($textEditorRepository->findOneBy(['name' => TextEditorName::PRIVACY_POLICY])) {
                $content = $textEditorRepository->findOneBy(['name' => TextEditorName::PRIVACY_POLICY])->getContent();
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
            $privacyPolicyFormat->getValue() === TextInputType::LINK &&
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
     * @throws \Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface
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
            $revokeProfiles = $this->profileManager->disableProfiles($user, true);
            if (!$revokeProfiles) {
                $this->addFlash('error', 'This account doesn\'t have profiles associated!');
                return $this->redirectToRoute('app_landing');
            }
            $eventMetaData = [
                'platform' => PlatformMode::LIVE,
                'uuid' => $user->getUuid(),
                'ip' => $request->getClientIp(),
            ];
            $this->eventActions->saveEvent(
                $user,
                AnalyticalEventType::USER_REVOKE_PROFILES,
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
                'platform' => PlatformMode::LIVE,
                'uuid' => $user->getUuid(),
                'ip' => $request->getClientIp(),
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
                AnalyticalEventType::USER_ACCOUNT_UPDATE,
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
            if (!password_verify($typedPassword, $currentPasswordDB)) {
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

            $em->persist($user);
            $em->flush();

            $eventMetaData = [
                'platform' => PlatformMode::LIVE,
                'uuid' => $user->getUuid(),
                'ip' => $request->getClientIp(),
            ];
            $this->eventActions->saveEvent(
                $user,
                AnalyticalEventType::USER_ACCOUNT_UPDATE_PASSWORD,
                new DateTime(),
                $eventMetaData
            );

            $this->addFlash('success', 'Your password has been updated successfully!');
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
        // Call the getSettings method of GetSettings class to retrieve the data
        $data = $this->getSettings->getSettings($this->userRepository, $this->settingRepository);

        if ($this->getUser()) {
            $this->addFlash('error', 'You can\'t access this page logged in. ');
            return $this->redirectToRoute('app_landing');
        }

        // Check if the user clicked on the 'sms' variable present only on the SMS authentication buttons
        if ($data['PLATFORM_MODE']['value'] === true) {
            $this->addFlash(
                'error',
                'The portal is in Demo mode - it is not possible to use this verification method.'
            );
            return $this->redirectToRoute('app_landing');
        }

        if ($data['EMAIL_REGISTER_ENABLED']['value'] !== true) {
            $this->addFlash('error', 'This verification method it\'s not enabled!');
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
                        $auth->getProvider() === UserProvider::PORTAL_ACCOUNT &&
                        $auth->getProviderId() === UserProvider::EMAIL
                    ) {
                        $hasValidPortalAccount = true;
                        break;
                    }
                }
                if ($hasValidPortalAccount) {
                    $latestEvent = $this->eventRepository->findLatestRequestAttemptEvent(
                        $user,
                        AnalyticalEventType::FORGOT_PASSWORD_EMAIL_REQUEST
                    );
                    $minInterval = new DateInterval('PT2M');
                    $currentTime = new DateTime();
                    // Check if enough time has passed since the last attempt
                    $latestEventMetadata = $latestEvent ? $latestEvent->getEventMetadata() : [];
                    $lastVerificationCodeTime = isset($latestEventMetadata['lastVerificationCodeTime'])
                        ? new DateTime($latestEventMetadata['lastVerificationCodeTime'])
                        : null;

                    if (
                        !$latestEvent || ($lastVerificationCodeTime instanceof DateTime &&
                            $lastVerificationCodeTime->add($minInterval) < $currentTime)
                    ) {
                        // Save event with attempt count and current time
                        if (!$latestEvent) {
                            $latestEvent = new Event();
                            $latestEvent->setUser($user);
                            $latestEvent->setEventDatetime(new DateTime());
                            $latestEvent->setEventName(AnalyticalEventType::FORGOT_PASSWORD_EMAIL_REQUEST);
                            $latestEventMetadata = [
                                'platform' => PlatformMode::LIVE,
                                'ip' => $request->getClientIp(),
                                'uuid' => $user->getUuid(),
                            ];
                        }

                        $latestEventMetadata['lastVerificationCodeTime'] = $currentTime->format(DateTime::ATOM);
                        $latestEvent->setEventMetadata($latestEventMetadata);

                        $user->setForgotPasswordRequest(true);
                        $user->setIsVerified(true);
                        $this->eventRepository->save($latestEvent, true);

                        $randomPassword = bin2hex(random_bytes(4));
                        $hashedPassword = $userPasswordHasher->hashPassword($user, $randomPassword);
                        $user->setPassword($hashedPassword);
                        $entityManager->persist($user);
                        $entityManager->flush();

                        $email = (new TemplatedEmail())
                            ->from(
                                new Address(
                                    $this->parameterBag->get('app.email_address'),
                                    $this->parameterBag->get('app.sender_name')
                                )
                            )
                            ->to($user->getEmail())
                            ->subject('Your Openroaming - Password Request')
                            ->htmlTemplate('email/user_forgot_password_request.html.twig')
                            ->context([
                                'password' => $randomPassword,
                                'forgotPasswordUser' => true,
                                'uuid' => $user->getUuid(),
                                'currentPassword' => $randomPassword,
                                'verificationCode' => $user->getVerificationCode(),
                            ]);

                        $mailer->send($email);

                        $message = sprintf('We have sent you a new email to: %s.', $user->getEmail());
                        $this->addFlash('success', $message);
                    } else {
                        // Inform the user to wait before trying again
                        $this->addFlash('warning', 'Please wait 2 minutes before trying again.');
                    }
                } else {
                    $this->addFlash(
                        'warning',
                        'This email is not associated with a valid account. 
                        Please submit a valid email from the system, 
                        ensuring it is from the platform and not from another provider.'
                    );
                }
            } else {
                $this->addFlash(
                    'warning',
                    'This email doesn\'t exist, please make sure to create a account with a email on the platform!'
                );
            }
        }
        return $this->render('site/forgot_password_email_landing.html.twig', [
            'forgotPasswordEmailForm' => $form->createView(),
            'data' => $data,
        ]);
    }

    /**
     * @throws Exception
     * @throws \Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface
     */
    #[Route('/forgot-password/sms', name: 'app_site_forgot_password_sms')]
    public function forgotPasswordUserSMS(
        Request $request,
        UserPasswordHasherInterface $userPasswordHasher,
        EntityManagerInterface $entityManager,
    ): Response {
        // Call the getSettings method of GetSettings class to retrieve the data
        $data = $this->getSettings->getSettings($this->userRepository, $this->settingRepository);

        if ($this->getUser()) {
            $this->addFlash('error', 'You can\'t access this page logged in. ');
            return $this->redirectToRoute('app_landing');
        }

        // Check if the user clicked on the 'sms' variable present only on the SMS authentication buttons
        if ($data['PLATFORM_MODE']['value'] === true) {
            $this->addFlash(
                'error',
                'The portal is in Demo mode - it is not possible to use this verification method.'
            );
            return $this->redirectToRoute('app_landing');
        }

        if ($data['EMAIL_REGISTER_ENABLED']['value'] !== true) {
            $this->addFlash('error', 'This verification method it\'s not enabled!');
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
                    AnalyticalEventType::FORGOT_PASSWORD_SMS_REQUEST
                );
                // Retrieve the SMS resend interval from the settings
                $smsResendInterval = $data['SMS_TIMER_RESEND']['value'];
                $minInterval = new DateInterval('PT' . $smsResendInterval . 'M');
                $currentTime = new DateTime();
                // Check if the user has not exceeded the attempt limit
                $latestEventMetadata = $latestEvent ? $latestEvent->getEventMetadata() : [];
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
                        if (!$latestEvent) {
                            $latestEvent = new Event();
                            $latestEvent->setUser($user);
                            $latestEvent->setEventDatetime(new DateTime());
                            $latestEvent->setEventName(AnalyticalEventType::FORGOT_PASSWORD_SMS_REQUEST);
                            $latestEventMetadata = [
                                'platform' => PlatformMode::LIVE,
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

                        // save new password hashed on the db for the user
                        $randomPassword = bin2hex(random_bytes(4));
                        $hashedPassword = $userPasswordHasher->hashPassword($user, $randomPassword);
                        $user->setPassword($hashedPassword);
                        $entityManager->persist($user);
                        $entityManager->flush();
                        // phpcs:disable Generic.Files.LineLength.TooLong
                        $recipient = "+" . $user->getPhoneNumber()->getCountryCode() . $user->getPhoneNumber()->getNationalNumber();
                        // phpcs:enable
                        // Send SMS
                        $message = "Your new random account password is: "
                            . $randomPassword
                            . "%0A" . "Please make sure to login to complete the request";
                        $this->sendSMS->sendSmsReset($recipient, $message);

                        $attemptsLeft = 3 - $verificationAttempts;
                        $message = sprintf(
                            'We have sent you a message to: %s. You have %d attempt(s) left.',
                            $user->getUuid(),
                            $attemptsLeft
                        );
                        $this->addFlash('success', $message);
                    } else {
                        // Inform the user to wait before trying again
                        $this->addFlash(
                            'warning',
                            "Please wait " . $data['SMS_TIMER_RESEND']['value'] . " minutes before trying again."
                        );
                    }
                } else {
                    $this->addFlash(
                        'warning',
                        'You have exceeded the limits of request for a new password. 
                            Please contact our support for help.'
                    );
                }
            } else {
                $this->addFlash(
                    'warning',
                    'This phone number doesn\'t exist, please submit a valid one from the system!'
                );
            }
        }
        return $this->render('site/forgot_password_sms_landing.html.twig', [
            'forgotPasswordSMSForm' => $form->createView(),
            'data' => $data,
        ]);
    }

    /**
     * @throws TransportExceptionInterface
     * @throws Exception
     */
    #[Route('/forgot-password/checker', name: 'app_site_forgot_password_checker')]
    public function forgotPasswordUserChecker(
        Request $request,
        UserPasswordHasherInterface $userPasswordHasher,
        EntityManagerInterface $entityManager,
        MailerInterface $mailer,
        UserPasswordHasherInterface $passwordHasher,
    ): Response {
        // Call the getSettings method of GetSettings class to retrieve the data
        $data = $this->getSettings->getSettings($this->userRepository, $this->settingRepository);

        if ($data['PLATFORM_MODE']['value'] == true) {
            $this->addFlash(
                'error',
                'The portal is in Demo mode - it is not possible to use this verification method!'
            );
            return $this->redirectToRoute('app_landing');
        }

        /** @var User $currentUser */
        $currentUser = $this->getUser();
        if (!$currentUser) {
            $this->addFlash('error', 'You must be logged in to access this page.');
            return $this->redirectToRoute('app_landing');
        }

        // Checks if the user has a "forgot_password_request", if don't, return to landing page
        if ($this->userRepository->findOneBy(['id' => $currentUser->getId(), 'forgot_password_request' => false])) {
            $this->addFlash('error', 'You can\'t access this page if you don\'t have a request!');
            return $this->redirectToRoute('app_landing');
        }

        $user = new User();
        $form = $this->createForm(NewPasswordAccountType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            /** @var User $user */
            $user = $this->getUser();

            $currentPasswordDB = $user->getPassword();
            $typedPassword = $form->get('password')->getData();

            // Compare the typed password with the hashed password from the database
            if (!password_verify($typedPassword, $currentPasswordDB)) {
                $this->addFlash('error', 'Current password Invalid. Please try again.');
                return $this->redirectToRoute('app_landing');
            }

            if ($form->get('newPassword')->getData() !== $form->get('confirmPassword')->getData()) {
                $this->addFlash(
                    'error',
                    'Please make sure to type the same password on both fields. 
                    If the problem keep occurring contact our support!'
                );
                return $this->redirectToRoute('app_landing');
            }

            $user->setPassword($passwordHasher->hashPassword($user, $form->get('newPassword')->getData()));
            $user->setForgotPasswordRequest(false);
            $entityManager->persist($user);
            $entityManager->flush();

            $eventMetadata = [
                'platform' => PlatformMode::LIVE,
                'ip' => $request->getClientIp(),
                'uuid' => $user->getUuid(),
            ];
            $this->eventActions->saveEvent(
                $user,
                AnalyticalEventType::FORGOT_PASSWORD_EMAIL_REQUEST_ACCEPTED,
                new DateTime(),
                $eventMetadata
            );

            $this->addFlash('success', 'Your password has been updated successfully!');
            return $this->redirectToRoute('app_landing');
        }

        return $this->render('site/forgot_password_checker_landing.html.twig', [
            'forgotPasswordChecker' => $form->createView(),
            'data' => $data,
        ]);
    }


    /**
     * @param $userAgent
     * @return string
     */
    private function detectDevice($userAgent)
    {
        $os = OSTypes::NONE;

        // Windows
        if (preg_match('/windows|win32/i', $userAgent)) {
            $os = OSTypes::WINDOWS;
        }

        // macOS
        if (preg_match('/macintosh|mac os x/i', $userAgent)) {
            $os = OSTypes::MACOS;
        }

        // iOS
        if (preg_match('/iphone|ipod|ipad/i', $userAgent)) {
            $os = OSTypes::IOS;
        }

        // Android
        if (preg_match('/android/i', $userAgent)) {
            $os = OSTypes::ANDROID;
        }

        // Linux
//        if (preg_match('/linux/i', $userAgent)) {
//            $os = OSTypes::LINUX;
//        }

        return $os;
    }

    /**
     * Create an email message with the verification code.
     *
     * @param string $email The recipient's email address.
     * @return Email The email with the code.
     * @throws Exception
     */
    protected function createEmailCode(string $email): Email
    {
        // Get the values from the services.yaml file using $parameterBag on the __construct
        $emailSender = $this->parameterBag->get('app.email_address');
        $nameSender = $this->parameterBag->get('app.sender_name');

        // If the verification code is not provided, generate a new one
        /** @var User $currentUser */
        $currentUser = $this->getUser();
        $verificationCode = $this->verificationCodeGenerator->generateVerificationCode($currentUser);

        return (new TemplatedEmail())
            ->from(new Address($emailSender, $nameSender))
            ->to($email)
            ->subject('Your OpenRoaming Authentication Code is: ' . $verificationCode)
            ->htmlTemplate('email/user_code.html.twig')
            ->context([
                'verificationCode' => $verificationCode,
            ]);
    }

    /**
     * Regenerate the verification code for the user and send a new email.
     *
     * @param EventRepository $eventRepository
     * @param MailerInterface $mailer
     * @param Request $request
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
                AnalyticalEventType::USER_EMAIL_ATTEMPT
            );
            $minInterval = new DateInterval('PT2M');
            $currentTime = new DateTime();

            // Check if enough time has passed since the last attempt
            $latestEventMetadata = $latestEvent ? $latestEvent->getEventMetadata() : [];
            $lastVerificationCodeTime = isset($latestEventMetadata['lastVerificationCodeTime'])
                ? new DateTime($latestEventMetadata['lastVerificationCodeTime'])
                : null;
            $verificationAttempts = isset($latestEventMetadata['verificationAttempts'])
                ? $latestEventMetadata['verificationAttempts']
                : 0;

            if (
                !$latestEvent || ($lastVerificationCodeTime instanceof DateTime &&
                    $lastVerificationCodeTime->add($minInterval) < $currentTime)
            ) {
                // Increment the attempt count
                $attempts = $verificationAttempts + 1;

                $email = $this->createEmailCode($currentUser->getEmail());
                $mailer->send($email);

                // Save event with attempt count and current time
                if (!$latestEvent) {
                    $latestEvent = new Event();
                    $latestEvent->setUser($currentUser);
                    $latestEvent->setEventDatetime(new DateTime());
                    $latestEvent->setEventName(AnalyticalEventType::USER_EMAIL_ATTEMPT);
                    $latestEventMetadata = [
                        'platform' => PlatformMode::LIVE,
                        'uuid' => $currentUser->getEmail(),
                        'ip' => $request->getClientIp(),
                    ];
                }

                $latestEventMetadata['lastVerificationCodeTime'] = $currentTime->format(DateTime::ATOM);
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
            $this->addFlash('error', 'You must be logged in to access this page.');
            return $this->redirectToRoute('app_landing');
        }

        if (!$currentUser->isVerified()) {
            $formTOS = $this->createForm(TOStype::class);
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


    /**
     * @param RequestStack $requestStack
     * @param UserRepository $userRepository
     * @param Request $request
     * @return Response
     */
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
            $this->addFlash('error', 'You must be logged in to access this page.');
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
            $event = new Event();
            // Set the user as verified
            $currentUser->setIsVerified(true);
            $userRepository->save($currentUser, true);

            $eventMetadata = [
                'platform' => PlatformMode::LIVE,
                'ip' => $request->getClientIp(),
                'uuid' => $currentUser->getUuid(),
            ];
            $this->eventActions->saveEvent(
                $currentUser,
                AnalyticalEventType::USER_VERIFICATION,
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
            $this->addFlash('error', 'You must be logged in to access this page.');
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
                if ($latestEvent) {
                    $latestEventMetadata = $latestEvent->getEventMetadata();
                    $verificationAttempts = isset($latestEventMetadata['verificationAttempts'])
                        ? $latestEventMetadata['verificationAttempts']
                        : 0;
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

    /**
     * @param $user
     * @return void
     */
    private function disableProfiles($user): void
    {
        $this->profileManager->disableProfiles($user);
    }
}
