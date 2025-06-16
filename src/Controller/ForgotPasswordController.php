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
use App\Service\SendSMS;
use DateInterval;
use DateTime;
use DateTimeInterface;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Psr\EventDispatcher\EventDispatcherInterface;
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
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Security\Http\Event\InteractiveLoginEvent;

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
                "You can't access this page logged in."
            );

            return $this->redirectToRoute('app_landing');
        }

        // Call the getSettings method of GetSettings class to retrieve the data
        $data = $this->getSettings->getSettings($this->userRepository, $this->settingRepository);

        if ($data['PLATFORM_MODE']['value'] === true) {
            $this->addFlash(
                'error',
                'The portal is in Demo mode - it is not possible to use this verification method.'
            );

            return $this->redirectToRoute('app_landing');
        }

        if ($data['AUTH_METHOD_REGISTER_ENABLED']['value'] !== 'true') {
            $this->addFlash(
                'error',
                "This verification method it's not enabled!"
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
                        $user->setVerificationCode(random_int(100000, 999999));

                        $this->eventRepository->save($latestEvent, true);
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
                                'Reset Your OpenRoaming Password'
                            )
                            ->htmlTemplate('email/user_forgot_password_request.html.twig')
                            ->context([
                                'forgotPasswordUser' => true,
                                'uuid' => $user->getUuid(),
                                'emailTitle' => $data['title']['value'],
                                'contactEmail' => $data['contactEmail']['value'],
                                'verificationCode' => $user->getVerificationCode(),
                                'context' => FirewallType::LANDING->value,
                            ]);

                        $mailer->send($email);

                        $message = "We have sent you a new email to: {$user->getEmail()}.";
                        $this->addFlash('success', $message);
                    } else {
                        // Inform the user to wait before trying again
                        $this->addFlash(
                            'warning',
                            'Please wait 2 minutes before trying again.'
                        );
                    }
                } else {
                    $this->addFlash(
                        'warning',
                        'This email is not associated with a valid account. 
                        Please submit a valid email from the system, ensuring it is from the platform 
                        and not from another provider.'
                    );
                }
            } else {
                $this->addFlash(
                    'warning',
                    'This email doesn\'t exist, 
                    please make sure to create a account with a email on the platform!'
                );
            }
        }

        return $this->render('site/forgot_password_email_landing.html.twig', [
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
        UserPasswordHasherInterface $userPasswordHasher,
        EntityManagerInterface $entityManager,
    ): Response {
        // Call the getSettings method of GetSettings class to retrieve the data
        $data = $this->getSettings->getSettings($this->userRepository, $this->settingRepository);

        if ($this->getUser() instanceof UserInterface) {
            $this->addFlash(
                'error',
                'You can\'t access this page logged in.'
            );

            return $this->redirectToRoute('app_landing');
        }

        // Check if the user clicked on the 'sms' variable present only on the SMS authentication buttons
        if ($data['PLATFORM_MODE']['value']) {
            $this->addFlash(
                'error',
                'The portal is in Demo mode - it is not possible to use this verification method.'
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

                        $user->setVerificationCode(random_int(100000, 999999));
                        $this->eventRepository->save($latestEvent, true);

                        $entityManager->persist($user);
                        $entityManager->flush();
                        $recipient = "+" .
                            $user->getPhoneNumber()->getCountryCode() .
                            $user->getPhoneNumber()->getNationalNumber();

                        $message = "If you requested a password reset for your OpenRoaming account, 
                        use this code to proceed: {$user->getVerificationCode()}";
                        $encodedMessage = urlencode($message);
                        $this->sendSMS->sendSmsNoValidation($recipient, $encodedMessage);

                        $attemptsLeft = 3 - $verificationAttempts;
                        $message = "We have sent you a message to: {$user->getUuid()}. 
                        You have {$attemptsLeft} attempt(s) left.";
                        $this->addFlash('success', $message);

                        return $this->redirectToRoute('app_site_forgot_password_code', [
                            'uuid' => $user->getUuid(),
                        ]);
                    }

                    // Inform the user to wait before trying again
                    $this->addFlash(
                        'warning',
                        "Please wait {$data['SMS_TIMER_RESEND']['value']} minute(s) before trying again."
                    );

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
            'context' => FirewallType::LANDING->value,
        ]);
    }

    /**
     * @throws RandomException
     */
    #[Route('/forgot-password/link', name: 'app_site_forgot_password_link')]
    public function forgotPasswordLinkAction(
        Request $request,
        EntityManagerInterface $entityManager,
        TokenStorageInterface $tokenStorage,
        EventDispatcherInterface $eventDispatcher
    ): Response {
        // Get the uuid and verification code from the URL query parameters
        $uuid = $request->query->get('uuid');
        $verificationCode = $request->query->get('verificationCode');

        // Get the user with the matching email, excluding admin users
        $user = $this->userRepository->findOneByUUIDExcludingAdmin($uuid);
        if (!$user) {
            $this->addFlash(
                'error',
                'You can not access this page without a valid request!'
            );

            return $this->redirectToRoute('app_landing');
        }

        if ($this->settingRepository->findOneBy(['name' => 'PLATFORM_MODE'])->getValue() !== PlatformMode::LIVE->value
        ) {
            $this->addFlash(
                'error',
                'The portal is in Demo mode - it is not possible to use this verification method.'
            );

            return $this->redirectToRoute('app_landing');
        }

        if ($user->getUuid() === $uuid && $user->getVerificationCode() === $verificationCode) {
            // TODO MAKE A SERVICE of this code
            // Create a token manually for the user
            $token = new UsernamePasswordToken($user, FirewallType::LANDING->value, $user->getRoles());

            // Set the token in the token storage
            $tokenStorage->setToken($token);

            // Dispatch the login event
            $event = new InteractiveLoginEvent($request, $token);
            $eventDispatcher->dispatch($event);

            $user->setForgotPasswordRequest(true);
            $user->setVerificationCode(random_int(100000, 999999));
            $entityManager->persist($user);
            $entityManager->flush();

            // Defines the Event to the table
            $eventMetadata = [
                'ip' => $request->getClientIp(),
                'user_agent' => $request->headers->get('User-Agent'),
                'platform' => PlatformMode::LIVE->value,
                'uuid' => $user->getUuid(),
            ];

            $this->eventActions->saveEvent(
                $user,
                AnalyticalEventType::FORGOT_PASSWORD_EMAIL_REQUEST_ACCEPTED->value,
                new DateTime(),
                $eventMetadata
            );

            $this->addFlash(
                'success',
                'Your account password-request has been accepted!'
            );

            return $this->redirectToRoute('app_site_forgot_password_checker');
        }

        return $this->redirectToRoute('app_landing');
    }

    /**
     * @throws RandomException
     */
    #[Route('/forgot-password/code', name: 'app_site_forgot_password_code')]
    public function forgotPasswordCodeAction(
        Request $request,
    ): Response {
        $data = $this->getSettings->getSettings($this->userRepository, $this->settingRepository);

        // Get the uuid and verification code from the URL query parameters
        $uuid = $request->query->get('uuid');

        // Get the user with the matching email, excluding admin users
        $user = $this->userRepository->findOneByUUIDExcludingAdmin($uuid);
        if (!$user) {
            $this->addFlash(
                'error',
                'You can not access this page without a valid request!'
            );

            return $this->redirectToRoute('app_landing');
        }

        if ($this->settingRepository->findOneBy(['name' => 'PLATFORM_MODE'])->getValue() !== PlatformMode::LIVE->value
        ) {
            $this->addFlash(
                'error',
                'The portal is in Demo mode - it is not possible to use this verification method.'
            );

            return $this->redirectToRoute('app_landing');
        }

        $form = $this->createForm(ResetPasswordSMSConfirmationType::class);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $code = $form->get('verificationCode')->getData();
            if ($user->getUuid() === $uuid && $user->getVerificationCode() === $code) {
                // TODO MAKE A SERVICE of this code
                dd('Make the service', $uuid, $user, $code);
                return $this->redirectToRoute('app_site_forgot_password_checker');
            }

            $this->addFlash(
                'error',
                'The verification code is incorrect. Please check and try again.'
            );
        }

        return $this->render('site/forgot_password_code_landing.html.twig', [
            'forgotPasswordCode' => $form->createView(),
            'data' => $data,
            'context' => FirewallType::LANDING->value,
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
                'You can only access this page logged in.'
            );

            return $this->redirectToRoute('app_landing');
        }

        // Call the getSettings method of GetSettings class to retrieve the data
        $data = $this->getSettings->getSettings($this->userRepository, $this->settingRepository);

        if ($data['PLATFORM_MODE']['value']) {
            $this->addFlash(
                'error',
                'The portal is in Demo mode - it is not possible to use this verification method.'
            );

            return $this->redirectToRoute('app_landing');
        }

        if (!$currentUser->isForgotPasswordRequest()) {
            $this->addFlash(
                'error',
                'You can not access this page without a valid request!'
            );

            return $this->redirectToRoute('app_landing');
        }

        // Checks if the user has a "forgot_password_request", if not, return to the landing page
        if ($this->userRepository->findOneBy(['id' => $currentUser->getId(), 'forgot_password_request' => false])) {
            $this->addFlash(
                'error',
                'You can\'t access this page if you don\'t have a request!'
            );

            return $this->redirectToRoute('app_landing');
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
                    'Please make sure to type the same password on both fields. 
                    If the problem keep occurring contact our support!'
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
            $currentUser->setIsVerified(true);
            $currentUser->setVerificationCode(random_int(100000, 999999));
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
                AnalyticalEventType::FORGOT_PASSWORD_EMAIL_REQUEST_ACCEPTED->value,
                new DateTime(),
                $eventMetadata
            );

            $this->addFlash(
                'success',
                'Your password has been updated successfully!'
            );

            return $this->redirectToRoute('app_landing');
        }

        return $this->render('site/forgot_password_checker_landing.html.twig', [
            'forgotPasswordChecker' => $form->createView(),
            'data' => $data,
            'context' => FirewallType::LANDING->value,
            'user' => $currentUser,
        ]);
    }
}
