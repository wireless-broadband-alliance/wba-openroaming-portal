<?php

namespace App\Controller;

use App\DTO\LoginChoiceDTO;
use App\Entity\Event;
use App\Entity\User;
use App\Enum\AnalyticalEventType;
use App\Enum\FirewallType;
use App\Enum\OperationMode;
use App\Enum\PlatformMode;
use App\Enum\SettingName;
use App\Enum\SMSResponse;
use App\Enum\UserProvider;
use App\Enum\UserTwoFactorAuthenticationStatus;
use App\Form\LoginType;
use App\Form\TwoFACode;
use App\Repository\UserExternalAuthRepository;
use App\Repository\UserRepository;
use App\Service\EventActions;
use App\Service\GetSettings;
use App\Service\MagicLinkService;
use App\Service\EmailGenerator;
use App\Service\SendSMS;
use App\Service\TwoFAService;
use App\Service\UserCreationService;
use App\Service\UserProviderDetectorResolverService;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\NonUniqueResultException;
use libphonenumber\NumberParseException;
use libphonenumber\PhoneNumber;
use libphonenumber\PhoneNumberUtil;
use Psr\EventDispatcher\EventDispatcherInterface;
use Random\RandomException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\Exception\LogicException;
use Symfony\Component\Form\Exception\RuntimeException;
use Symfony\Component\HttpFoundation\Exception\SessionNotFoundException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;
use Symfony\Component\Security\Http\Event\InteractiveLoginEvent;
use Symfony\Contracts\Translation\TranslatorInterface;

class SecurityController extends AbstractController
{
    /**
     * SiteController constructor.
     * @param UserRepository $userRepository The repository for accessing user data.
     * @param GetSettings $getSettings The instance of GetSettings class.
     *  of the user account
     */
    public function __construct(
        private readonly UserRepository $userRepository,
        private readonly UserExternalAuthRepository $userExternalAuthRepository,
        private readonly GetSettings $getSettings,
        private readonly TwoFAService $twoFAService,
        private readonly TranslatorInterface $translator,
        private readonly EntityManagerInterface $entityManager,
        private readonly SendSMS $sendSMS,
        private readonly MagicLinkService $magicLinkService,
        private readonly EventActions $eventActions,
        private readonly EmailGenerator $emailGenerator,
        private readonly UserCreationService $userCreationService,
        private readonly TokenStorageInterface $tokenStorage,
        private readonly UserProviderDetectorResolverService $userProviderDetectorResolverService,
        private readonly AuthenticationUtils $authenticationUtils,
        private readonly UserPasswordHasherInterface $userPasswordHasher,
        private readonly SessionInterface $session,
        private readonly EventDispatcherInterface $eventDispatcher,
    ) {
    }

    /**
     * @throws NonUniqueResultException
     */
    #[Route('/login', name: 'app_login')]
    public function login(
        Request $request,
    ): Response {
        if ($this->isGranted('IS_AUTHENTICATED_FULLY')) {
            return $this->redirectToRoute('app_landing');
        }

        // Call the getSettings method of GetSettings class to retrieve the data
        /** @var array<string, array{value: string, description: string}> $data */
        $data = $this->getSettings->getSettings();
        if ($data[SettingName::PLATFORM_MODE->value]['value'] === PlatformMode::DEMO->value) {
            return $this->redirectToRoute('app_landing');
        }

        if ($data[SettingName::LOGIN_WITH_UUID_ONLY->value]['value'] === OperationMode::ON->value) {
            return $this->redirectToRoute('app_login_magic');
        }

        // Last username entered by the user (this will be empty if the user clicked the verification link)
        $uuid = (string)$request->request->get('uuid');
        $email = (string)$request->request->get('email');
        $phoneNumber = $request->request->get('phoneNumber') ?? $request->query->get('phoneNumber');

        $resolved = null;
        if ($uuid !== '' && $uuid !== '0') {
            $resolved = $this->userProviderDetectorResolverService->resolve($uuid);
        }

        // Create the DTO with injected default regions and required password for this login method
        $dto = new LoginChoiceDTO();
        $phoneUtil = PhoneNumberUtil::getInstance();
        $defaultRegion = $data[SettingName::DEFAULT_REGION_PHONE_INPUTS->value]['value'];

        if ($email !== '' && $email !== '0') {
            $dto->email = $email;
        } elseif (!empty($phoneNumber)) {
            try {
                $dto->phoneNumber = $phoneUtil->parse((string)$phoneNumber, $defaultRegion);
            } catch (NumberParseException) {
                $dto->phoneNumber = null;
            }
        } elseif ($resolved && $resolved['uuidType'] === UserProvider::EMAIL->value) {
            $dto->email = $uuid;
        } elseif ($resolved && $resolved['uuidType'] === UserProvider::PHONE_NUMBER->value) {
            try {
                $dto->phoneNumber = $phoneUtil->parse($uuid, $defaultRegion);
            } catch (NumberParseException) {
                $dto->phoneNumber = null;
            }
        }

        $emailMethod = $data[SettingName::AUTH_METHOD_REGISTER_ENABLED->value]['value'];
        $phoneNumberMethod = $data[SettingName::AUTH_METHOD_SMS_REGISTER_ENABLED->value]['value'];
        if ($emailMethod === 'false' && $phoneNumberMethod) {
            $dto->loginMethod = UserProvider::PHONE_NUMBER->value;
            $dto->requireLoginMethod = false;
        } elseif ($emailMethod === 'true' && !$phoneNumberMethod) {
            $dto->loginMethod = UserProvider::EMAIL->value;
            $dto->requireLoginMethod = false;
        } else {
            $dto->loginMethod = UserProvider::EMAIL->value;
            $dto->requireLoginMethod = true;
        }

        $dto->requirePassword = true;

        $request->getSession();
        $lastMethod = $this->session->get('last_login_method');

        if ($lastMethod) {
            $dto->loginMethod = $lastMethod;
        }

        // Create the form bound to the DTO
        $form = $this->createForm(LoginType::class, $dto);

        // Get the login error if there is one
        $error = $this->authenticationUtils->getLastAuthenticationError();

        // Show an error message if the login attempt fails
        if ($error instanceof AuthenticationException) {
            $this->addFlash('error', $error->getMessage());
        }

        return $this->render('landing/login/login_landing.html.twig', [
            'error' => $error,
            'data' => $data,
            'form' => $form,
            'context' => FirewallType::LANDING->value,
            'loginChoiceDTO' => $dto,
        ]);
    }

    /**
     * @throws TransportExceptionInterface
     * @throws RandomException
     */
    #[Route('/login/magic', name: 'app_login_magic')]
    public function loginMagic(
        Request $request,
    ): Response {
        /** @var array<string, array{value: string, description: string}> $data */
        $data = $this->getSettings->getSettings();

        if ($data[SettingName::LOGIN_WITH_UUID_ONLY->value]['value'] === OperationMode::OFF->value) {
            return $this->redirectToRoute('app_login');
        }

        $loginChoiceDTO = new LoginChoiceDTO();
        $emailMethod = $data[SettingName::AUTH_METHOD_REGISTER_ENABLED->value]['value'];
        $phoneNumberMethod = $data[SettingName::AUTH_METHOD_SMS_REGISTER_ENABLED->value]['value'];
        if ($emailMethod === 'false' && $phoneNumberMethod) {
            $loginChoiceDTO->loginMethod = UserProvider::PHONE_NUMBER->value;
            $loginChoiceDTO->requireLoginMethod = false;
        } elseif ($emailMethod === 'true' && !$phoneNumberMethod) {
            $loginChoiceDTO->loginMethod = UserProvider::EMAIL->value;
            $loginChoiceDTO->requireLoginMethod = false;
        } else {
            $loginChoiceDTO->loginMethod = UserProvider::EMAIL->value;
            $loginChoiceDTO->requireLoginMethod = true;
        }
        $loginChoiceDTO->requirePassword = false;

        $form = $this->createForm(LoginType::class, $loginChoiceDTO);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            if ($loginChoiceDTO->loginMethod === UserProvider::EMAIL->value) {
                $loginUser = $this->userRepository->findOneBy(['uuid' => $loginChoiceDTO->email]);

                if ($loginUser instanceof User) {
                    if ($loginUser->getUserExternalAuths()[0]->getProvider() !== UserProvider::PORTAL_ACCOUNT->value) {
                        $this->addFlash(
                            'error',
                            $this->translator->trans('emailInUse', [], 'controllers')
                        );
                        return $this->redirectToRoute('app_login_magic');
                    }

                    $event = $this->magicLinkService->canSendLink($loginUser);
                    if (!($event instanceof Event)) {
                        $this->emailGenerator->sendRegistrationEmail(
                            $loginUser
                        );

                        $eventMetaData = [
                            'platform' => PlatformMode::LIVE->value,
                            'user_agent' => $request->headers->get('User-Agent'),
                            'uuid' => $loginUser->getUuid(),
                            'ip' => $request->getClientIp(),
                        ];
                        $this->eventActions->saveEvent(
                            $loginUser,
                            AnalyticalEventType::LOGIN_WITH_UUID_ONLY_LINK->value,
                            new DateTime(),
                            $eventMetaData
                        );
                        $this->addFlash(
                            'success',
                            $this->translator->trans('loginSentSuccessfully', [], 'controllers')
                        );
                    } else {
                        $timeIntervalToResendCode = $data[SettingName::TWO_FACTOR_AUTH_RESEND_INTERVAL->value]['value'];
                        $message = $this->magicLinkService->timeToResend($timeIntervalToResendCode, $event);
                        $this->addFlash(
                            'error',
                            $message
                        );
                    }
                } elseif (filter_var($loginChoiceDTO->email, FILTER_VALIDATE_EMAIL)) {
                    $user = new User();
                    $user->setUuid($loginChoiceDTO->email);
                    $user->setEmail($loginChoiceDTO->email);

                    // Generate a random password
                    $randomPassword = bin2hex(random_bytes(4));

                    // Hash the password
                    $hashedPassword = $this->userPasswordHasher->hashPassword($user, $randomPassword);

                    $user = $this->userCreationService->createUser(
                        $user,
                        $hashedPassword,
                        UserProvider::EMAIL->value,
                        $request
                    );

                    $this->emailGenerator->sendRegistrationEmail($user, $randomPassword);

                    $this->addFlash(
                        'success',
                        $this->translator->trans('loginLinkSent', [], 'controllers'),
                    );
                } else {
                    $this->addFlash(
                        'error',
                        $this->translator->trans('invalidEmailFormat', [], 'controllers')
                    );
                }
            } else {
                $phoneNumber = '+' . $loginChoiceDTO->phoneNumber->getCountryCode() .
                    $loginChoiceDTO->phoneNumber->getNationalNumber();
                $loginUser = $this->userRepository->findOneBy(['uuid' => $phoneNumber]);
                if ($loginUser instanceof User) {
                    if ($loginUser->getUserExternalAuths()[0]->getProvider() !== UserProvider::PORTAL_ACCOUNT->value) {
                        $this->addFlash(
                            'error',
                            $this->translator->trans('phoneInUse', [], 'controllers')
                        );
                        return $this->redirectToRoute('app_login_magic');
                    }
                    $event = $this->magicLinkService->canSendLink($loginUser);
                    if (!($event instanceof Event)) {
                        $link = $this->magicLinkService->magicToken($loginUser);

                        $message = $this->translator->trans(
                            'loginLinkMessage',
                            [
                                '%link%' => $link
                            ],
                            'controllers'
                        );

                        $smsResponse = $this->sendSMS->sendSmsNoValidation($loginUser, $message);

                        if ($smsResponse === SMSResponse::SMS_SUCCESS_LINK->value) {
                            // Save event for link sent
                            $eventMetaData = [
                                'platform' => PlatformMode::LIVE->value,
                                'user_agent' => $request->headers->get('User-Agent'),
                                'uuid' => $loginUser->getUuid(),
                                'ip' => $request->getClientIp(),
                            ];
                            $this->eventActions->saveEvent(
                                $loginUser,
                                AnalyticalEventType::LOGIN_WITH_UUID_ONLY_LINK->value,
                                new DateTime(),
                                $eventMetaData
                            );

                            $this->addFlash(
                                'success',
                                $this->translator->trans('loginLinkSentCheckSMS', [], 'controllers')
                            );
                        } elseif ($smsResponse === SMSResponse::SMS_SUCCESS_CODE->value) {
                            // Save event for code sent
                            $eventMetaData = [
                                'platform' => PlatformMode::LIVE->value,
                                'user_agent' => $request->headers->get('User-Agent'),
                                'uuid' => $loginUser->getUuid(),
                                'ip' => $request->getClientIp(),
                            ];
                            $this->eventActions->saveEvent(
                                $loginUser,
                                AnalyticalEventType::LOGIN_WITH_UUID_ONLY_CODE->value,
                                new DateTime(),
                                $eventMetaData
                            );
                            $this->addFlash(
                                'success',
                                $this->translator->trans('loginLinkSentCheckSMS', [], 'controllers')
                            );

                            // Soft Authenticate the user for code confirmation
                            $token = new UsernamePasswordToken(
                                $loginUser,
                                FirewallType::LANDING->value,
                                $loginUser->getRoles()
                            );
                            $this->tokenStorage->setToken($token);

                            // Store the authentication token in the session
                            $this->session->set('_security_main', serialize($token));

                            return $this->redirectToRoute('app_login_confirmation');
                        } else {
                            $this->addFlash(
                                'error',
                                $this->translator->trans('unableSendLoginLink', [], 'controllers')
                            );
                        }
                    } else {
                        $timeIntervalToResendCode = $data[SettingName::TWO_FACTOR_AUTH_RESEND_INTERVAL->value]['value'];
                        $message = $this->magicLinkService->timeToResend($timeIntervalToResendCode, $event);
                        $this->addFlash(
                            'error',
                            $message
                        );
                    }
                } else {
                    $user = new User();
                    // Generate a random password
                    $randomPassword = bin2hex(random_bytes(4));
                    // Hash the password
                    $hashedPassword = $this->userPasswordHasher->hashPassword($user, $randomPassword);

                    $user->setUuid($phoneNumber);
                    $user->setPhoneNumber($loginChoiceDTO->phoneNumber);
                    $user = $this->userCreationService->createUser(
                        $user,
                        $hashedPassword,
                        UserProvider::PHONE_NUMBER->value,
                        $request
                    );

                    $link = $this->magicLinkService->magicToken($user);

                    $message = $this->translator->trans(
                        'loginLinkConfirmMessage',
                        [
                            '%link%' => $link
                        ],
                        'controllers'
                    );

                    $smsResponse = $this->sendSMS->sendSmsNoValidation($user, $message);

                    if ($smsResponse === SMSResponse::SMS_SUCCESS_LINK->value) {
                        $this->addFlash(
                            'success',
                            $this->translator->trans('loginLinkSentCheckSMS', [], 'controllers')
                        );
                    } elseif ($smsResponse === SMSResponse::SMS_SUCCESS_CODE->value) {
                        $this->addFlash(
                            'success',
                            $this->translator->trans('loginVerificationCodeSent', [], 'controllers')
                        );

                        // Soft Authenticate the user for code confirmation
                        $token = new UsernamePasswordToken(
                            $user,
                            FirewallType::LANDING->value,
                            $user->getRoles()
                        );
                        $this->tokenStorage->setToken($token);

                        // Store the authentication token in the session
                        $this->session->set('_security_main', serialize($token));

                        return $this->redirectToRoute('app_login_confirmation');
                    } else {
                        $this->addFlash(
                            'error',
                            $this->translator->trans('unableSendLoginLink', [], 'controllers')
                        );
                    }
                }
            }
        }

        return $this->render('landing/login_UUID_landing.html.twig', [
            'data' => $data,
            'form' => $form,
            'context' => FirewallType::LANDING->value,
            'loginChoiceDTO' => $loginChoiceDTO,
        ]);
    }

    /**
     * @throws NonUniqueResultException
     */
    #[Route('/dashboard/login', name: 'app_dashboard_login')]
    public function dashboardLogin(Request $request): Response
    {
        if ($this->isGranted('IS_AUTHENTICATED_FULLY')) {
            return $this->redirectToRoute('admin_page');
        }

        // Call the getSettings method of GetSettings class to retrieve the data
        /** @var array<string, array{value: string, description: string}> $data */
        $data = $this->getSettings->getSettings();

        // Last username entered by the user (this will be empty if the user clicked the verification link)
        $email = $request->request->get('email') ?? $request->query->get('email');
        $phoneNumber = $request->request->get('phoneNumber') ?? $request->query->get('phoneNumber');
        if (!empty($email)) {
            $lastUsername = $email;
        } elseif (!empty($phoneNumber)) {
            $lastUsername = $phoneNumber;
        } else {
            // Fallback to Symfony's AuthenticationUtils
            $lastUsername = $this->authenticationUtils->getLastUsername();
        }

        // Create the DTO with injected default regions and required password for this login method
        $dto = new LoginChoiceDTO();
        $dto->requirePassword = true;
        $dto->requireLoginMethod = false;
        $dto->loginMethod = UserProvider::EMAIL->value;

        // Create the form bound to the DTO
        $form = $this->createForm(LoginType::class, $dto);

        // Get the login error if there is one
        $error = $this->authenticationUtils->getLastAuthenticationError();

        // Show an error message if the login attempt fails
        if ($error instanceof AuthenticationException) {
            $this->addFlash('error', $error->getMessage());
        }

        return $this->render('dashboard/login/login_admin_landing.html.twig', [
            'last_username' => $lastUsername,
            'error' => $error,
            'data' => $data,
            'form' => $form,
            'context' => FirewallType::DASHBOARD->value,
            'loginChoiceDTO' => $dto,
        ]);
    }

    /**
     * @throws HttpException
     * @throws SessionNotFoundException
     * @throws RuntimeException
     * @throws LogicException
     */
    #[Route('/login/confirmation', name: 'app_login_confirmation')]
    #[IsGranted("ROLE_USER")]
    public function loginConfirmation(
        Request $request,
    ): Response {
        /** @var User $user */
        $user = $this->getUser();


        $userExternalAuths = $this->userExternalAuthRepository->findBy(['user' => $user]);

        // Check if the user is already verified
        $request->getSession();
        if (
            $userExternalAuths[0]->getProvider() !== UserProvider::PORTAL_ACCOUNT->value ||
            $this->session->has('session_verified')
        ) {
            return $this->redirectToRoute('app_landing');
        }

        /** @var array<string, array{value: string, description: string}> $data */
        $data = $this->getSettings->getSettings();

        $form = $this->createForm(TwoFACode::class);
        $form->handleRequest($request);
        $request->getSession();
        if ($form->isSubmitted() && $form->isValid()) {
            /** @var array<string, mixed> $formData */
            $formData = $form->getData();
            $code = $formData['code'] ?? null;
            if ($this->twoFAService->validate2FACode($user, $code)) {
                $user->setIsVerified(true);
                $user->setForgotPasswordRequest(false);
                $this->entityManager->persist($user);
                $this->entityManager->flush();
                $this->session->set('session_verified', true);

                return $this->redirectToRoute('app_landing');
            }

            $this->addFlash(
                'error',
                $this->translator->trans('invalidCode', [], 'controllers')
            );
        }

        return $this->render('landing/login/login_landing_code_confirmation.html.twig', [
            'data' => $data,
            'form' => $form,
            'context' => FirewallType::LANDING->value,
            'user' => $user,
        ]);
    }

    #[Route('/login/magic/link', name: 'app_login_magic_link')]
    public function confirmAccountMagicLink(
        Request $request,
    ): Response {
        // Get the uuid and verification code from the URL query parameters
        $token = $request->query->get('token');

        // Get the user with the matching email, excluding admin users
        $user = $this->userRepository->findOneBy(['twoFAcode' => $token]);

        if ($user && $user->getTwoFAcodeIsActive() && $this->magicLinkService->linkValidity($user)) {
            try {
                // Create a token manually for the user
                $token = new UsernamePasswordToken($user, FirewallType::LANDING->value, $user->getRoles());

                // Set the token in the token storage
                $this->tokenStorage->setToken($token);

                // Dispatch the login event
                $event = new InteractiveLoginEvent($request, $token);
                $this->eventDispatcher->dispatch($event);
                $session = $request->getSession();

                if (!$user->isVerified()) {
                    $user->setIsVerified(true);
                    $session->set('session_verified', true);
                }

                $user->setTwoFAcodeIsActive(false);
                $this->userRepository->save($user, true);

                if (
                    $user->getTwoFAtype() === UserTwoFactorAuthenticationStatus::EMAIL->value ||
                    $user->getTwoFAtype() === UserTwoFactorAuthenticationStatus::SMS->value
                ) {
                    $session->set('2fa_verified_' . FirewallType::LANDING->value, true);
                }

                // Defines the Event to the table
                $eventMetadata = [
                    'ip' => $request->getClientIp(),
                    'user_agent' => $request->headers->get('User-Agent'),
                    'platform' => PlatformMode::LIVE->value,
                    'uuid' => $user->getUuid(),
                ];
                $this->eventActions->saveEvent(
                    $user,
                    AnalyticalEventType::LOGIN_WITH_UUID_ONLY_LOGIN->value,
                    new DateTime(),
                    $eventMetadata
                );

                $this->addFlash(
                    'success',
                    $this->translator->trans('loginSuccessfully', [], 'controllers')
                );

                return $this->redirectToRoute('app_landing');
            } catch (CustomUserMessageAuthenticationException) {
                // Invalid link in case the try catch fails
                $this->addFlash(
                    'error',
                    $this->translator->trans('invalidLogin', [], 'controllers')
                );
            }
        } else {
            // Invalid operation in case the link is actually expired based on the service timer
            $this->addFlash(
                'error',
                $this->translator->trans('invalidLogin', [], 'controllers')
            );
        }

        return $this->redirectToRoute('app_login');
    }

    #[Route(path: '/dashboard/logout', name: 'app_dashboard_logout')]
    public function dashboardLogout(Request $request): Response
    {
        $session = $request->getSession();
        $session->clear();
        return $this->redirectToRoute('app_dashboard_login');
    }

    #[Route(path: '/logout', name: 'app_logout')]
    public function logout(Request $request): Response
    {
        $session = $request->getSession();
        $session->clear();

        return $this->redirectToRoute('app_landing');
    }
}
