<?php

namespace App\Controller;

use App\DTO\NewPasswordAccountDTO;
use App\Entity\User;
use App\Enum\AnalyticalEventType;
use App\Enum\EventMetadataKeysType;
use App\Enum\FirewallType;
use App\Enum\ForgotPasswordEnum;
use App\Enum\PlatformMode;
use App\Enum\SessionStatus;
use App\Enum\SettingName;
use App\Enum\UserProvider;
use App\Form\ForgotPasswordEmailType;
use App\Form\ForgotPasswordSMSType;
use App\Form\NewPasswordAccountType;
use App\Form\ResetPasswordSMSConfirmationType;
use App\Repository\SettingRepository;
use App\Repository\UserExternalAuthRepository;
use App\Repository\UserRepository;
use App\Service\EmailGenerator;
use App\Service\EncryptionService;
use App\Service\EventActions;
use App\Service\GetSettings;
use App\Service\HashArgon2idService;
use App\Service\MagicLinkService;
use App\Service\PasswordResetAttemptGuard;
use App\Service\PasswordResetRequestHandler;
use App\Service\SendSMS;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use libphonenumber\PhoneNumber;
use libphonenumber\PhoneNumberFormat;
use libphonenumber\PhoneNumberUtil;
use Random\RandomException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;
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
        private readonly GetSettings $getSettings,
        private readonly EventActions $eventActions,
        private readonly SendSMS $sendSMS,
        private readonly SettingRepository $settingRepository,
        private readonly PasswordResetRequestHandler $passwordResetRequestHandler,
        private readonly TranslatorInterface $translator,
        private readonly EmailGenerator $emailGenerator,
        private readonly EntityManagerInterface $entityManager,
        private readonly MagicLinkService $magicLinkService,
        private readonly UserPasswordHasherInterface $userPasswordHasher,
        private readonly RateLimiterFactoryInterface $verifyAccountLimiter,
        private readonly PasswordResetAttemptGuard $passwordResetAttemptGuard,
        private readonly EncryptionService $encryptionService,
        private readonly HashArgon2idService $hashArgon2idService
    ) {
    }

    /**
     * @throws TransportExceptionInterface
     * @throws Exception
     */
    #[Route('/forgot-password/email', name: 'app_site_forgot_password_email')]
    public function forgotPasswordUserEmail(
        Request $request,
    ): Response {
        if ($this->getUser() instanceof UserInterface) {
            $this->addFlash(
                'error',
                $this->translator->trans('cantAccessThisPageLoggedIn', [], 'controllers')
            );
            return $this->redirectToRoute('app_landing');
        }

        // Call the getSettings method of GetSettings class to retrieve the data
        /** @var array<string, array{value: string, description: string}> $data */
        $data = $this->getSettings->getSettings();

        if ($data[SettingName::PLATFORM_MODE->value]['value'] === PlatformMode::DEMO->value) {
            $this->addFlash(
                'error',
                $this->translator->trans('portalInDemoMode', [], 'controllers')
            );
            return $this->redirectToRoute('app_landing');
        }

        if ($data[SettingName::AUTH_METHOD_REGISTER_ENABLED->value]['value'] !== 'true') {
            $this->addFlash(
                'error',
                $this->translator->trans('verificationMethodNotEnabled', [], 'controllers')
            );
            return $this->redirectToRoute('app_landing');
        }

        $formUser = new User();
        $form = $this->createForm(ForgotPasswordEmailType::class, $formUser);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $submittedEmail = $formUser->getEmail();

            // Find existing user by email (or uuid if configured as such)
            $user = $this->userRepository->findOneBy(['email' => $submittedEmail])
                ?? $this->userRepository->findOneBy(['uuid' => $submittedEmail]);

            if (!$user) {
                $this->addFlash(
                    'error',
                    $this->translator->trans('emailDoesntExist', [], 'controllers')
                );
                return $this->redirectToRoute('app_site_forgot_password_email');
            }

            $breakingGlassSetting = $data[SettingName::BREAKING_GLASS_ADMIN_EMAIL->value]['value'] ?? null;
            if (!empty($breakingGlassSetting)) {
                $breakingGlassEmail = $this->encryptionService->decrypt($breakingGlassSetting);
                if ($user->getEmail() === $breakingGlassEmail) {
                    $this->addFlash(
                        'error',
                        $this->translator->trans('emailDoesntExist', [], 'controllers')
                    );
                    return $this->redirectToRoute('app_site_forgot_password_email');
                }
            }

            // Check if the provider is "PORTAL_ACCOUNT" and the providerId "EMAIL"
            $userExternalAuths = $this->userExternalAuthRepository->findBy(['user' => $user]);
            $hasValidPortalAccount = false;

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
                // Same atomic check-and-record as the API reset endpoints: the limits are checked and the attempt
                // is saved under a row lock on the user, and nothing below runs unless it was accepted
                $attempt = $this->passwordResetAttemptGuard->tryRegisterAttempt(
                    $user,
                    false,
                    $request->getClientIp(),
                    $request->headers->get('User-Agent')
                );
                if ($attempt[PasswordResetAttemptGuard::ALLOWED]) {
                    $rawCode = (string)random_int(100000, 999999);
                    $user->setTwoFAcode($rawCode);
                    $user->setTwoFACodeGeneratedAt(new DateTime());
                    $user->setTwoFAcodeIsActive(true);

                    $this->entityManager->persist($user);
                    $this->entityManager->flush();

                    // Send email with the raw plain-text 2FA code
                    $this->emailGenerator->sendForgotPasswordEmail($user, $rawCode);

                    $message = $this->translator->trans(
                        'emailSentMessage',
                        ['%email%' => $user->getEmail()],
                        'controllers'
                    );
                    $this->addFlash('success', $message);
                } else {
                    // Inform the user to wait before trying again
                    $timeLeft = $attempt[PasswordResetAttemptGuard::TIME_LEFT];
                    if (
                        $attempt[PasswordResetAttemptGuard::REASON] ===
                        ForgotPasswordEnum::ATTEMPTS_EXCEEDED->value
                    ) {
                        $minutes = ($timeLeft->days * 24 * 60)
                            + ($timeLeft->h * 60)
                            + $timeLeft->i;
                        $this->addFlash(
                            'error',
                            $this->translator->trans(
                                'tooManyAttemptsMinutes',
                                ['%minutes%' => $minutes],
                                'controllers'
                            )
                        );
                    } elseif (
                        $attempt[PasswordResetAttemptGuard::REASON] ===
                        ForgotPasswordEnum::TIME_BETWEEN_REQUESTS->value
                    ) {
                        $seconds = ($timeLeft->days * 24 * 3600)
                            + ($timeLeft->h * 3600)
                            + ($timeLeft->i * 60)
                            + $timeLeft->s;
                        $this->addFlash(
                            'error',
                            $this->translator->trans(
                                'replyCodeTotp',
                                ['%remainingTime%' => $seconds],
                                'controllers'
                            )
                        );
                    } else {
                        $timeToResetAttempts =
                            $data[SettingName::EMAIL_TIME_INTERVAL_TO_RESET_ATTEMPTS->value]['value'];
                        $this->addFlash(
                            'error',
                            $this->translator->trans(
                                'waitBeforeTryingAgain',
                                ['%minutes%' => $timeToResetAttempts],
                                'controllers'
                            )
                        );
                    }
                }
            } else {
                $this->addFlash(
                    'error',
                    $this->translator->trans('emailNotAssociatedWithValidAccount', [], 'controllers')
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
    ): Response {
        /** @var array<string, array{value: string, description: string}> $data */
        $data = $this->getSettings->getSettings();

        if ($this->getUser() instanceof UserInterface) {
            $this->addFlash(
                'error',
                $this->translator->trans('cantAccessThisPageLoggedIn', [], 'controllers')
            );
            return $this->redirectToRoute('app_landing');
        }

        // Check if the user clicked on the 'sms' variable present only on the SMS authentication buttons
        if ($data[SettingName::PLATFORM_MODE->value]['value'] === PlatformMode::DEMO->value) {
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
            $phoneNumber = $user->getPhoneNumber();
            // phoneNumber is encrypted (random IV) and can't be queried; phone users have their E.164 number as uuid
            $user = $phoneNumber instanceof PhoneNumber
                ? $this->userRepository->findOneBy([
                    'uuid' => PhoneNumberUtil::getInstance()->format($phoneNumber, PhoneNumberFormat::E164),
                ])
                : null;
            if ($user) {
                // Same atomic check-and-record as the API reset endpoints (cooldown + attempts limit, shared
                // counter): the attempt is saved under a row lock on the user before any code is generated or sent
                $attempt = $this->passwordResetAttemptGuard->tryRegisterAttempt(
                    $user,
                    true,
                    $request->getClientIp(),
                    $request->headers->get('User-Agent')
                );
                if ($attempt[PasswordResetAttemptGuard::ALLOWED]) {
                    $rawCode = (string)random_int(100000, 999999);
                    $user->setTwoFAcode($rawCode);
                    $user->setTwoFACodeGeneratedAt(new DateTime());
                    $user->setTwoFAcodeIsActive(true);

                    $this->entityManager->persist($user);
                    $this->entityManager->flush();

                    $message = $this->translator->trans(
                        'password_reset_code',
                        ['%code%' => $rawCode],
                        'controllers'
                    );
                    $this->sendSMS->sendSmsNoValidation($user, $message);

                    $message = $this->translator->trans(
                        'messageSentWithAttemptsLeft',
                        [
                            '%uuid%' => $user->getUuid(),
                        ],
                        'controllers'
                    );
                    $this->addFlash('success', $message);

                    $request->getSession()->set('forgot_password_uuid', $user->getUuid());

                    return $this->redirectToRoute('app_site_forgot_password_code');
                }
                // Inform the user to wait before trying again
                $timeLeft = $attempt[PasswordResetAttemptGuard::TIME_LEFT];
                if (
                    $attempt[PasswordResetAttemptGuard::REASON] ===
                    ForgotPasswordEnum::ATTEMPTS_EXCEEDED->value
                ) {
                    $minutes = ($timeLeft->days * 24 * 60)
                        + ($timeLeft->h * 60)
                        + $timeLeft->i;
                    $this->addFlash(
                        'error',
                        $this->translator->trans(
                            'tooManyAttemptsMinutes',
                            ['%minutes%' => $minutes],
                            'controllers'
                        )
                    );
                } elseif (
                    $attempt[PasswordResetAttemptGuard::REASON] ===
                    ForgotPasswordEnum::TIME_BETWEEN_REQUESTS->value
                ) {
                    $seconds = ($timeLeft->days * 24 * 3600)
                        + ($timeLeft->h * 3600)
                        + ($timeLeft->i * 60)
                        + $timeLeft->s;
                    $this->addFlash(
                        'error',
                        $this->translator->trans(
                            'replyCodeTotp',
                            ['%seconds%' => $seconds],
                            'controllers'
                        )
                    );
                } else {
                    $timeToResetAttempts = $data[SettingName::SMS_TIME_INTERVAL_TO_RESET_ATTEMPTS->value]['value'];
                    $this->addFlash(
                        'error',
                        $this->translator->trans(
                            'waitBeforeTryingAgain',
                            ['%minutes%' => $timeToResetAttempts],
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
    public function forgotPasswordLink(Request $request): Response
    {
        // Get the uuid and verification code from the URL query parameters
        $uuid = (string)$request->query->get('uuid');
        $twoFaCode = (string)$request->query->get('twoFaCode');
        $key = $request->getClientIp() . '_' . $uuid;

        $limiter = $this->verifyAccountLimiter->create($key);
        $limit = $limiter->consume();

        if (!$limit->isAccepted()) {
            $retryAfter = $limit->getRetryAfter();
            $seconds = $retryAfter->getTimestamp() - time();

            throw new TooManyRequestsHttpException(
                $seconds,
                $this->translator->trans(
                    'tooManyAttempts',
                    ['%seconds%' => $seconds],
                    'controllers'
                )
            );
        }

        // Get the user with the matching email, excluding admin users
        $user = $this->userRepository->findOneBy(['uuid' => $uuid]);
        if (!$user instanceof User) {
            $this->addFlash(
                'error',
                $this->translator->trans('cannotAccessThisPageWithoutValidRequest', [], 'controllers')
            );

            return $this->redirectToRoute('app_landing');
        }

        if (
            $this->settingRepository->findOneBy(
                ['name' => SettingName::PLATFORM_MODE->value]
            )->getValue() !== PlatformMode::LIVE->value
        ) {
            $this->addFlash('error', $this->translator->trans('portalInDemoMode', [], 'controllers'));

            return $this->redirectToRoute('app_landing');
        }

        $isCodeValid = $user->getTwoFAcode() !== null
            && $twoFaCode !== ''
            && $this->hashArgon2idService->verifyHash($twoFaCode, $user->getTwoFAcode());

        if ($isCodeValid && $user->getUuid() === $uuid) {
            if (
                $this->magicLinkService->linkCanBeUsed(
                    $user,
                    AnalyticalEventType::FORGOT_PASSWORD_EMAIL_REQUEST->value
                )
            ) {
                $this->addFlash('error', $this->translator->trans('invalidVerificationCodeLink', [], 'controllers'));

                return $this->redirectToRoute('app_landing');
            }
            // Create a token manually for the user
            $this->passwordResetRequestHandler->handle($user);

            $this->addFlash('success', $this->translator->trans('passwordRequestAccepted', [], 'controllers'));

            if (in_array('ROLE_ADMIN', $user->getRoles(), true)) {
                $session = $request->getSession();
                $session->remove('_security_dashboard');
            }

            return $this->redirectToRoute('app_site_forgot_password_checker');
        }

        $this->addFlash('error', $this->translator->trans('invalidVerificationCodeLink', [], 'controllers'));

        return $this->redirectToRoute('app_landing');
    }

    /**
     * @throws RandomException
     */
    #[Route('/forgot-password/code', name: 'app_site_forgot_password_code')]
    public function forgotPasswordCode(Request $request): Response
    {
        /** @var array<string, array{value: string, description: string}> $data */
        $data = $this->getSettings->getSettings();

        // Get the uuid and verification code from the URL query parameters
        $uuid = $request->getSession()->get('forgot_password_uuid');

        $key = $request->getClientIp() . '_' . $uuid;

        $limiter = $this->verifyAccountLimiter->create($key);
        $limit = $limiter->consume();

        if (!$limit->isAccepted()) {
            $retryAfter = $limit->getRetryAfter();
            $seconds = $retryAfter->getTimestamp() - time();

            throw new TooManyRequestsHttpException(
                $seconds,
                $this->translator->trans(
                    'tooManyAttempts',
                    ['%seconds%' => $seconds],
                    'controllers'
                )
            );
        }

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
            $this->settingRepository->findOneBy(
                ['name' => SettingName::PLATFORM_MODE->value]
            )->getValue() !== PlatformMode::LIVE->value
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
            $code = (string)$form->get('verificationCode')->getData();
            $isCodeValid = $user->getTwoFAcode() !== null
                && $code !== ''
                && $this->hashArgon2idService->verifyHash($code, $user->getTwoFAcode());

            if ($isCodeValid && $user->getUuid() === $uuid) {
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
        string $context
    ): Response {
        /** @var User|null $currentUser */
        $currentUser = $this->getUser();
        if (!$currentUser) {
            $this->addFlash(
                'error',
                $this->translator->trans('onlyAccessThisPageLoggedIn', [], 'controllers')
            );
            return $this->redirectToRoute('app_landing');
        }

        // Call the getSettings method of GetSettings class to retrieve the data
        /** @var array<string, array{value: string, description: string}> $data */
        $data = $this->getSettings->getSettings();

        if ($data[SettingName::PLATFORM_MODE->value]['value'] === PlatformMode::DEMO->value) {
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

        $passwordDTO = new NewPasswordAccountDTO();
        $form = $this->createForm(
            NewPasswordAccountType::class,
            $passwordDTO,
            [
                'require_current_password' => false,
            ]
        );
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $currentUser->setPassword(
                $this->userPasswordHasher->hashPassword(
                    $currentUser,
                    $passwordDTO->newPassword
                )
            );
            $currentUser->setForgotPasswordRequest(false);
            $currentUser->setIsVerified(true);
            $currentUser->setTwoFAcode((string)random_int(100000, 999999));
            $currentUser->setTwoFACodeGeneratedAt(new DateTime());
            $currentUser->setTwoFAcodeIsActive(true);
            $session = $request->getSession();
            $session->set(SessionStatus::VERIFIED->value, true);

            $this->entityManager->persist($currentUser);
            $this->entityManager->flush();

            $eventMetadata = [
                EventMetadataKeysType::IP->value => $request->getClientIp(),
                EventMetadataKeysType::USER_AGENT->value => $request->headers->get('User-Agent'),
                EventMetadataKeysType::UUID->value => $currentUser->getUuid(),
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
