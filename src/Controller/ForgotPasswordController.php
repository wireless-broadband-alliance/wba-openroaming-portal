<?php

namespace App\Controller;

use App\Entity\Event;
use App\Entity\User;
use App\Enum\AnalyticalEventType;
use App\Enum\FirewallType;
use App\Enum\PlatformMode;
use App\Enum\UserProvider;
use App\Form\ForgotPasswordEmailType;
use App\Form\ForgotPasswordSMSType;
use App\Form\NewPasswordAccountType;
use App\Form\ResetPasswordSMSConfirmationType;
use App\Repository\EventRepository;
use App\Repository\SettingRepository;
use App\Repository\UserExternalAuthRepository;
use App\Repository\UserRepository;
use App\Service\EventActions;
use App\Service\GetSettings;
use App\Service\PasswordResetRequestHandler;
use App\Service\SendSMS;
use DateInterval;
use DateTime;
use DateTimeInterface;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Random\RandomException;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * @method getParameterBag()
 */
class ForgotPasswordController extends AbstractController
{
    public function __construct(
        private readonly UserRepository $userRepository,
        private readonly UserExternalAuthRepository $userExternalAuthRepository,
        private readonly ParameterBagInterface $parameterBag,
        private readonly GetSettings $getSettings,
        private readonly EventRepository $eventRepository,
        private readonly EventActions $eventActions,
        private readonly SendSMS $sendSMS,
        private readonly SettingRepository $settingRepository,
        private readonly PasswordResetRequestHandler $passwordResetRequestHandler,
        private readonly TranslatorInterface $translator,
    ) {
    }

    /**
     * @throws TransportExceptionInterface
     * @throws Exception
     */
    #[Route('/forgot-password/email', name: 'app_site_forgot_password_email')]
    public function forgotPasswordUserEmail(
        Request $request,
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

        if ($data['PLATFORM_MODE']['value'] === true) {
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
        $form = $this->createForm(ForgotPasswordEmailType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $user = $this->userRepository->findOneBy(['uuid' => $user->getEmail()]);
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
                    $resetPasswordTimer = $data['EMAIL_TIMER_RESEND']['value'];
                    $minInterval = new DateInterval('PT' . $resetPasswordTimer . 'M');
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
                        $latestEvent = new Event();
                        $latestEvent->setUser($user);
                        $latestEvent->setEventDatetime(new DateTime());
                        $latestEvent->setEventName(AnalyticalEventType::FORGOT_PASSWORD_EMAIL_REQUEST->value);
                        $latestEventMetadata = [
                            'platform' => PlatformMode::LIVE->value,
                            'ip' => $request->getClientIp(),
                            'uuid' => $user->getUuid(),
                        ];

                        $latestEventMetadata['lastVerificationCodeTime'] =
                            $currentTime->format(DateTimeInterface::ATOM);
                        $latestEvent->setEventMetadata($latestEventMetadata);
                        $user->setTwoFAcode(random_int(100000, 999999));
                        $user->setTwoFACodeGeneratedAt(new DateTime());
                        $user->setTwoFAcodeIsActive(true);

                        $entityManager->persist($latestEvent);
                        $entityManager->persist($user);
                        $entityManager->flush();


                        $customerLogo = $data['CUSTOMER_LOGO']['value'];
                        $projectDir =  $this->parameterBag->get('kernel.project_dir');
                        $logoPath = $projectDir . '/public' . $customerLogo;
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
                                'forgotPasswordUser' => true,
                                'uuid' => $user->getUuid(),
                                'emailTitle' => $data['PAGE_TITLE']['value'],
                                'contactEmail' => $data['CONTACT_EMAIL']['value'],
                                'verificationCode' => $user->getTwoFAcode(),
                                'context' => FirewallType::LANDING->value,
                            ])
                            ->embedFromPath($logoPath, 'logo_cid');

                        $mailer->send($email);

                        $message = $this->translator->trans(
                            'emailSentMessage',
                            ['%email%' => $user->getEmail()],
                            'controllers'
                        );
                        $this->addFlash('success', $message);
                    } else {
                        // Inform the user to wait before trying again
                        $emailTimeIntervalSetting = $data['EMAIL_TIMER_RESEND']['value'];
                        $this->addFlash(
                            'error',
                            $this->translator->trans(
                                'waitBeforeTryingAgain',
                                ['%minutes%' => $emailTimeIntervalSetting],
                                'controllers'
                            )
                        );
                    }
                } else {
                    $this->addFlash(
                        'error',
                        $this->translator->trans('emailNotAssociatedWithValidAccount', [], 'controllers')
                    );
                }
            } else {
                $this->addFlash(
                    'error',
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
    #[Route(
        'forgot-password/sms',
        name: 'app_site_forgot_password_sms',
    )]
    public function forgotPasswordUserSMS(
        Request $request,
        EntityManagerInterface $entityManager,
    ): Response {
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

                        $user->setTwoFAcode(random_int(100000, 999999));
                        $user->setTwoFACodeGeneratedAt(new DateTime());
                        $user->setTwoFAcodeIsActive(true);
                        $this->eventRepository->save($latestEvent, true);

                        $entityManager->persist($user);
                        $entityManager->flush();

                        $message = $this->translator->trans(
                            'password_reset_code',
                            ['%code%' => $user->getTwoFAcode()],
                            'controllers'
                        );
                        $this->sendSMS->sendSmsNoValidation($user, $message);

                        $attemptsLeft = 3 - $verificationAttempts;
                        $message = $this->translator->trans(
                            'messageSentWithAttemptsLeft',
                            [
                                '%uuid%' => $user->getUuid(),
                                '%attempts%' => $attemptsLeft,
                            ],
                            'controllers'
                        );
                        $this->addFlash('success', $message);

                        $request->getSession()->set('forgot_password_uuid', $user->getUuid());

                        return $this->redirectToRoute('app_site_forgot_password_code');
                    }

                    $this->addFlash(
                        'error',
                        $this->translator->trans(
                            'waitBeforeRetry',
                            [
                                '%minutes%' => $data['SMS_TIMER_RESEND']['value']
                            ],
                            'controllers'
                        )
                    );
                } else {
                    $this->addFlash(
                        'error',
                        $this->translator->trans(
                            'exceededLimitsRequestForNewPassword',
                            [],
                            'controllers'
                        )
                    );
                }
            } else {
                $this->addFlash(
                    'error',
                    $this->translator->trans(
                        'PhoneNumberDoesntExist',
                        [],
                        'controllers'
                    )
                );
            }
        }

        return $this->render('landing/forgotPassword/forgot_password_sms.html.twig', [
            'forgotPasswordSMSForm' => $form->createView(),
            'data' => $data,
            'context' => FirewallType::LANDING->value,
        ]);
    }

    /**
     * @throws RandomException
     */
    #[Route('/forgot-password/link', name: 'app_site_forgot_password_link')]
    public function forgotPasswordLink(
        Request $request,
    ): Response {
        // Get the uuid and verification code from the URL query parameters
        $uuid = $request->query->get('uuid');
        $twoFaCode = $request->query->get('twoFaCode');


        // Get the user with the matching email, excluding admin users
        $user = $this->userRepository->findOneBy([ 'uuid' => $uuid]);
        if (!$user instanceof User) {
            $this->addFlash(
                'error',
                $this->translator->trans(
                    'cannotAccessThisPageWithoutValidRequest',
                    [],
                    'controllers'
                )
            );

            return $this->redirectToRoute('app_landing');
        }

        if (
            $this->settingRepository->findOneBy(['name' => 'PLATFORM_MODE'])->getValue() !== PlatformMode::LIVE->value
        ) {
            $this->addFlash(
                'error',
                $this->translator->trans(
                    'portalInDemoMode',
                    [],
                    'controllers'
                )
            );
            return $this->redirectToRoute('app_landing');
        }

        if ($user->getUuid() === $uuid && $user->getTwoFAcode() === $twoFaCode) {
            $lastEvent = $this->eventRepository->findLatest2FACodeAttemptEvent(
                $user,
                AnalyticalEventType::FORGOT_PASSWORD_EMAIL_REQUEST->value
            );
            $linkTimeInterval = $this->settingRepository->findOneBy(['name' => 'LINK_VALIDITY'])->getValue();
            $lastAttemptTime = $lastEvent instanceof Event ?
                $lastEvent->getEventDatetime() : $linkTimeInterval;
            $now = new DateTime();
            $interval = date_diff($now, $lastAttemptTime);
            $interval_minutes = $interval->days * 1440;
            $interval_minutes += $interval->h * 60;
            $interval_minutes += $interval->i;
            if ($interval_minutes > ((int)$linkTimeInterval)) {
                $this->addFlash(
                    'error',
                    $this->translator->trans(
                        'invalidVerificationCodeLink',
                        [],
                        'controllers'
                    )
                );
                return $this->redirectToRoute('app_landing');
            }
            // Create a token manually for the user
            $this->passwordResetRequestHandler->handle($user);

            $this->addFlash(
                'success',
                $this->translator->trans(
                    'passwordRequestAccepted',
                    [],
                    'controllers'
                )
            );
            if (in_array('ROLE_ADMIN', $user->getRoles(), true)) {
                $session = $request->getSession();
                $session->remove('_security_dashboard');
            }
            return $this->redirectToRoute('app_site_forgot_password_checker');
        }

        $this->addFlash(
            'error',
            $this->translator->trans(
                'invalidVerificationCodeLink',
                [],
                'controllers'
            )
        );
        return $this->redirectToRoute('app_landing');
    }

    /**
     * @throws RandomException
     */
    #[Route('/forgot-password/code', name: 'app_site_forgot_password_code')]
    public function forgotPasswordCode(
        Request $request,
    ): Response {
        $data = $this->getSettings->getSettings();

        // Get the uuid and verification code from the URL query parameters
        $uuid = $request->getSession()->get('forgot_password_uuid');
        if (!$uuid) {
            $this->addFlash(
                'error',
                $this->translator->trans('cannotAccessThisPageWithoutValidRequest', [], 'controllers')
            );
            return $this->redirectToRoute('app_landing');
        }

        // Get the user with the matching email, excluding admin users
        $user = $this->userRepository->findOneByUUIDExcludingAdmin($uuid);
        if (!$user instanceof User) {
            $this->addFlash(
                'error',
                $this->translator->trans('cannotAccessThisPageWithoutValidRequest', [], 'controllers')
            );

            return $this->redirectToRoute('app_landing');
        }

        if (
            $this->settingRepository->findOneBy(['name' => 'PLATFORM_MODE'])->getValue() !== PlatformMode::LIVE->value
        ) {
            $this->addFlash(
                'error',
                $this->translator->trans('portalInDemoMode', [], 'controllers')
            );

            return $this->redirectToRoute('app_landing');
        }

        $form = $this->createForm(ResetPasswordSMSConfirmationType::class);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $code = $form->get('verificationCode')->getData();
            if ($user->getUuid() === $uuid && $user->getTwoFAcode() === $code) {
                // Create a token manually for the user
                $this->passwordResetRequestHandler->handle($user);

                $this->addFlash(
                    'success',
                    $this->translator->trans(
                        'passwordRequestAccepted',
                        [],
                        'controllers'
                    )
                );

                return $this->redirectToRoute('app_site_forgot_password_checker');
            }

            $this->addFlash(
                'error',
                $this->translator->trans(
                    'incorrectVerificationCode',
                    [],
                    'controllers'
                )
            );
        }

        return $this->render('/landing/forgotPassword/forgot_password_code.html.twig', [
            'forgotPasswordCode' => $form->createView(),
            'data' => $data,
            'context' => FirewallType::LANDING->value,
        ]);
    }

    /**
     * @throws Exception
     */
    #[Route(
        '{context}/forgot-password/checker',
        name: 'app_site_forgot_password_checker',
        requirements: [
            'context' => 'landing|dashboard'
        ],
        defaults: [
            'context' => FirewallType::LANDING->value
        ]
    )]
    #[IsGranted('ROLE_USER')]
    public function forgotPasswordUserChecker(
        Request $request,
        EntityManagerInterface $entityManager,
        UserPasswordHasherInterface $userPasswordHasher,
        string $context
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

            return $this->redirectToRoute(
                $context === FirewallType::DASHBOARD->value
                    ? 'admin_page'
                    : 'app_landing'
            );
        }

        // Checks if the user has a "forgot_password_request", if not, return to the landing page
        if ($this->userRepository->findOneBy(['id' => $currentUser->getId(), 'forgot_password_request' => false])) {
            $this->addFlash(
                'error',
                $this->translator->trans('cantAccessThisPageWithoutRequest', [], 'controllers')
            );

            return $this->redirectToRoute(
                $context === FirewallType::DASHBOARD->value
                    ? 'admin_page'
                    : 'app_landing'
            );
        }

        $form = $this->createForm(
            NewPasswordAccountType::class,
            $currentUser,
            ['require_current_password' => false]
        );
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            if ($form->get('newPassword')->getData() !== $form->get('confirmPassword')->getData()) {
                $this->addFlash(
                    'error',
                    $this->translator->trans('typeTheSamePasswordBothFields', [], 'controllers')
                );

                return $this->redirectToRoute(
                    $context === FirewallType::DASHBOARD->value
                        ? 'admin_page'
                        : 'app_landing'
                );
            }

            $currentUser->setPassword(
                $userPasswordHasher->hashPassword(
                    $currentUser,
                    $form->get('newPassword')->getData()
                )
            );
            $currentUser->setForgotPasswordRequest(false);
            $currentUser->setIsVerified(true);
            $currentUser->setTwoFAcode(random_int(100000, 999999));
            $currentUser->setTwoFACodeGeneratedAt(new DateTime());
            $currentUser->setTwoFAcodeIsActive(true);
            $session = $request->getSession();
            $session->set('session_verified', true);
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
                AnalyticalEventType::FORGOT_PASSWORD_REQUEST_ACCEPTED->value,
                new DateTime(),
                $eventMetadata
            );

            $this->addFlash(
                'success',
                $this->translator->trans('passwordUpdatedSuccessfully', [], 'controllers')
            );

            return $this->redirectToRoute(
                $context === FirewallType::DASHBOARD->value
                    ? 'admin_page'
                    : 'app_landing'
            );
        }

        return $this->render('landing/forgotPassword/forgot_password_checker.html.twig', [
            'forgotPasswordChecker' => $form->createView(),
            'data' => $data,
            'context' => $context,
            'user' => $currentUser,
        ]);
    }
}
