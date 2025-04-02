<?php

namespace App\Controller;

use App\Entity\Event;
use App\Entity\User;
use App\Enum\AnalyticalEventType;
use App\Enum\FirewallType;
use App\Enum\UserTwoFactorAuthenticationStatus;
use App\Form\TwoFACode;
use App\Repository\EventRepository;
use App\Repository\SettingRepository;
use App\Repository\UserRepository;
use App\Service\GetSettings;
use App\Service\TOTPService;
use App\Service\TwoFAService;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Endroid\QrCode\QrCode;
use Endroid\QrCode\Writer\PngWriter;
use libphonenumber\PhoneNumber;
use Random\RandomException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\User\UserInterface;

class TwoFAController extends AbstractController
{
    public function __construct(
        private readonly UserRepository $userRepository,
        private readonly SettingRepository $settingRepository,
        private readonly GetSettings $getSettings,
        private readonly TOTPService $totpService,
        private readonly EntityManagerInterface $entityManager,
        private readonly TwoFAService $twoFAService,
        private readonly EventRepository $eventRepository,
    ) {
    }

    #[Route(
        '/{context}/configure2FA',
        name: 'app_configure2FA',
        defaults: [
            'context' => FirewallType::LANDING->value
        ]
    )]
    public function configure2FA(string $context): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        // Ensure the user is logged in
        if (!$user instanceof UserInterface) {
            $this->addFlash('error', 'You can only access this page logged in.');
            return $this->redirectToRoute('app_landing');
        }

        // Handle access restrictions based on the context
        if ($context === 'dashboard' && !$this->isGranted('ROLE_ADMIN')) {
            $this->addFlash('error', 'Only admin users can access this page.');
            return $this->redirectToRoute('app_dashboard_login');
        }

        $data = $this->getSettings->getSettings($this->userRepository, $this->settingRepository);

        return $this->render('site/twoFAAuthentication/base_configuration.html.twig', [
            'user' => $user,
            'data' => $data,
            'context' => $context
        ]);
    }

    #[Route(
        '/{context}/enable2FA/TOTP',
        name: 'app_enable2FA_TOTP',
        defaults: [
            'context' => FirewallType::LANDING->value
        ]
    )]
    public function enable2FATOTP(
        string $context,
        Request $request
    ): Response {
        /** @var User $user */
        $user = $this->getUser();

        // Ensure user is logged in
        if (!$user instanceof UserInterface) {
            $this->addFlash('error', 'You can only access this page logged in.');
            return $this->redirectToRoute('app_landing');
        }

        // Handle access restrictions based on the context
        if ($context === FirewallType::DASHBOARD->value && !$this->isGranted('ROLE_ADMIN')) {
            $this->addFlash('error', 'Only admin users can access this page.');
            return $this->redirectToRoute('app_dashboard_login');
        }

        if ($user->getTwoFAtype() === UserTwoFactorAuthenticationStatus::TOTP->value) {
            return $this->redirectToRoute('app_landing');
        }

        $data = $this->getSettings->getSettings($this->userRepository, $this->settingRepository);
        $form = $this->createForm(TwoFACode::class);
        $session = $request->getSession();
        if ($form->handleRequest($request)->isSubmitted() && $form->isValid()) {
            // Get the introduced code
            $code = $form->get('code')->getData();
            if ($user instanceof User) {
                // Get the secret code to communicate with app.
                $secret = $user->gettwoFASecret();
                // Check if the code used is the one generated in the application.
                if ($this->totpService->verifyTOTP($secret, $code)) {
                    $session->set('2fa_verified', true);
                    $this->twoFAService->event2FA(
                        $request->getClientIp(),
                        $user,
                        AnalyticalEventType::ENABLE_TOTP_2FA->value,
                        $request->headers->get('User-Agent')
                    );
                    $user->setTwoFAtype(UserTwoFactorAuthenticationStatus::TOTP->value);
                    $this->entityManager->persist($user);
                    $this->entityManager->flush();
                    return $this->redirectToRoute('app_otpCodes', [
                        'context' => $context
                    ]);
                }
                $this->addFlash('error', 'Invalid code');
            }
        }
        $secret = $user->getTwoFAsecret() ?: $this->totpService->generateSecret();
        if ($user instanceof User) {
            if (
                $user->getTwoFAtype() === UserTwoFactorAuthenticationStatus::SMS->value ||
                $user->getTwoFAtype() === UserTwoFactorAuthenticationStatus::EMAIL->value
            ) {
                return $this->redirectToRoute('app_2FA_generate_code_swap_method', [
                    'context' => $context
                ]);
            }
            $user->setTwoFAsecret($secret);
            $this->entityManager->persist($user);
            $this->entityManager->flush();
        } else {
            $this->addFlash('error', 'You must be logged in to access this page');
            if ($context === FirewallType::DASHBOARD->value) {
                return $this->redirectToRoute('admin_page');
            }
            return $this->redirectToRoute('app_landing');
        }
        $formattedSecret = implode(' ', str_split($secret, 10));

        $provisioningUri = $this->totpService->generateTOTP($secret);
        // Generate the qr code to activate 2fa through the app.
        $qrCode = new QrCode($provisioningUri);
        $writer = new PngWriter();
        $qrCodeResult = $writer->write($qrCode);
        $qrCodeImage = base64_encode($qrCodeResult->getString());

        return $this->render('site/twoFAAuthentication/actions/enable2faTOTP.html.twig', [
            'qrCodeImage' => $qrCodeImage,
            'provisioningUri' => $provisioningUri,
            'secret' => $formattedSecret,
            'data' => $data,
            'user' => $user,
            'form' => $form,
            'context' => $context
        ]);
    }

    #[Route(
        '/{context}/verify2FA/TOTP',
        name: 'app_verify2FA_TOTP',
        defaults: [
            'context' => FirewallType::LANDING->value
        ]
    )]
    public function verify2FA(string $context, Request $request): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        $session = $request->getSession();

        // If the user isn't logged in, redirect to the landing page
        if (!$user instanceof UserInterface) {
            $this->addFlash('error', 'You can only access this page logged in.');
            return $this->redirectToRoute('app_landing');
        }

        // Handle access restrictions based on the context
        if ($context === 'dashboard' && !$this->isGranted('ROLE_ADMIN')) {
            $this->addFlash('error', 'Only admin users can access this page.');
            return $this->redirectToRoute('app_dashboard_login');
        }

        // Check if 2FA has already been verified
        if ($session->has('2fa_verified')) {
            return $this->redirectToRoute('app_landing');
        }
        $data = $this->getSettings->getSettings($this->userRepository, $this->settingRepository);
        $form = $this->createForm(TwoFACode::class);
        if ($form->handleRequest($request)->isSubmitted() && $form->isValid()) {
            // Get the introduced code
            $code = $form->get('code')->getData();
            if ($user instanceof User) {
                // Get the secret code to communicate with app.
                $secret = $user->gettwoFASecret();
                // Check if the used code is one of the OTP codes
                if ($this->twoFAService->validateOTPCodes($user, $code)) {
                    $session->set('2fa_verified', true);
                    $this->twoFAService->event2FA(
                        $request->getClientIp(),
                        $user,
                        AnalyticalEventType::VERIFY_OTP_2FA->value,
                        $request->headers->get('User-Agent')
                    );
                    if ($context === FirewallType::DASHBOARD->value) {
                        return $this->redirectToRoute('admin_page');
                    }
                    return $this->redirectToRoute('app_landing');
                }
                // Check if the code used is the one generated in the application.
                if ($this->totpService->verifyTOTP($secret, $code)) {
                    $session->set('2fa_verified', true);
                    $this->twoFAService->event2FA(
                        $request->getClientIp(),
                        $user,
                        AnalyticalEventType::VERIFY_TOTP_2FA->value,
                        $request->headers->get('User-Agent')
                    );
                    if ($context === FirewallType::DASHBOARD->value) {
                        return $this->redirectToRoute('admin_page');
                    }
                    return $this->redirectToRoute('app_landing');
                }
                $this->addFlash('error', 'Invalid code');
            }
        }
        return $this->render('site/twoFAAuthentication/verify/verify2FA.html.twig', [
            'data' => $data,
            'form' => $form,
            'user' => $user,
        ]);
    }

    #[Route(
        '/{context}/verify2FA',
        name: 'app_verify2FA_portal',
        defaults: [
            'context' => FirewallType::LANDING->value
        ]
    )]
    public function verify2FAPortal(string $context, Request $request): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        if (!$user instanceof UserInterface) {
            $this->addFlash('error', 'You can only access this page logged in. ');
            return $this->redirectToRoute('app_landing');
        }

        // Handle access restrictions based on the context
        if ($context === 'dashboard' && !$this->isGranted('ROLE_ADMIN')) {
            $this->addFlash('error', 'Only admin users can access this page.');
            return $this->redirectToRoute('app_dashboard_login');
        }

        $data = $this->getSettings->getSettings($this->userRepository, $this->settingRepository);
        $form = $this->createForm(TwoFACode::class);
        $session = $request->getSession();
        if ($session->has('2fa_verified')) {
            return $this->redirectToRoute('app_landing');
        }
        if ($form->handleRequest($request)->isSubmitted() && $form->isValid()) {
            // Get the introduced code
            $formCode = $form->get('code')->getData();
            // Check if the used code is one of the OTP codes
            if ($this->twoFAService->validateOTPCodes($user, $formCode)) {
                $session->set('2fa_verified', true);
                $this->twoFAService->event2FA(
                    $request->getClientIp(),
                    $user,
                    AnalyticalEventType::VERIFY_OTP_2FA->value,
                    $request->headers->get('User-Agent')
                );
                if ($context === FirewallType::DASHBOARD->value) {
                    return $this->redirectToRoute('admin_page');
                }
                return $this->redirectToRoute('app_landing');
            }
            // Check if the code used is the one generated in the BD.
            if ($this->twoFAService->validate2FACode($user, $formCode)) {
                $session->set('2fa_verified', true);
                $this->twoFAService->event2FA(
                    $request->getClientIp(),
                    $user,
                    AnalyticalEventType::VERIFY_LOCAL_2FA->value,
                    $request->headers->get('User-Agent')
                );
                if ($context === FirewallType::DASHBOARD->value) {
                    return $this->redirectToRoute('admin_page');
                }
                return $this->redirectToRoute('app_landing');
            }
            $this->addFlash('error', 'Invalid code please try again or resend the code');
        }
        return $this->render('site/twoFAAuthentication/verify/verify2FA.html.twig', [
            'data' => $data,
            'form' => $form,
            'user' => $user,
            'context' => $context
        ]);
    }

    #[Route(
        '/{context}/disable2FA',
        name: 'app_disable2FA',
        defaults: [
            'context' => FirewallType::LANDING->value
        ]
    )]
    public function disable2FA(string $context, Request $request): RedirectResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        // Ensure the user is logged in
        if (!$user instanceof UserInterface) {
            $this->addFlash('error', 'You can only access this page logged in.');
            return $this->redirectToRoute('app_landing');
        }
        // Handle access restrictions based on the context
        if ($context === 'dashboard' && !$this->isGranted('ROLE_ADMIN')) {
            $this->addFlash('error', 'Only admin users can access this page.');
            return $this->redirectToRoute('app_dashboard_login');
        }


        if (
            $user->getTwoFAtype() === UserTwoFactorAuthenticationStatus::SMS->value ||
            $user->getTwoFAtype() === UserTwoFactorAuthenticationStatus::EMAIL->value
        ) {
            $data = $this->getSettings->getSettings($this->userRepository, $this->settingRepository);
            $timeToResetAttempts = $data["TWO_FACTOR_AUTH_TIME_RESET_ATTEMPTS"]["value"];
            $limitTime = new DateTime();
            $limitTime->modify('-' . $timeToResetAttempts . ' minutes');
            if ($this->twoFAService->canValidationCode($user, AnalyticalEventType::TWO_FA_CODE_DISABLE->value)) {
                $this->twoFAService->generate2FACode(
                    $user,
                    $request->getClientIp(),
                    $request->headers->get('User-Agent'),
                    AnalyticalEventType::TWO_FA_CODE_DISABLE->value
                );
                $this->addFlash(
                    'success',
                    'A confirmation code was sent to you successfully.'
                );
                return $this->redirectToRoute('app_disable2FA_local', [
                    'context' => $context
                ]);
            }
            $interval_minutes = $this->twoFAService->timeLeftToResendCode(
                $user,
                AnalyticalEventType::TWO_FA_CODE_DISABLE->value
            );
            $this->addFlash(
                'error',
                'Your code has already been sent to you previously. Wait ' .
                $interval_minutes . ' minutes to request a code again'
            );
            return $this->redirectToRoute('app_disable2FA_local', [
                'context' => $context
            ]);
        }
        if ($user->getTwoFAtype() === UserTwoFactorAuthenticationStatus::TOTP->value) {
            return $this->redirectToRoute('app_disable2FA_TOTP', [
                'context' => $context
            ]);
        }
        if ($user->getTwoFAtype() === UserTwoFactorAuthenticationStatus::DISABLED->value) {
            $this->addFlash('error', 'Two-Factor authentication is already disabled');
            if ($context === FirewallType::DASHBOARD->value) {
                return $this->redirectToRoute('admin_page');
            }
            return $this->redirectToRoute('app_landing');
        }

        return $this->redirectToRoute('app_landing');
    }

    #[Route(
        '/{context}/disable2FA/local',
        name: 'app_disable2FA_local',
        defaults: [
            'context' => FirewallType::LANDING->value
        ]
    )]
    public function disable2FALocal(string $context, Request $request): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        // Ensure the user is logged in
        if (!$user instanceof UserInterface) {
            $this->addFlash('error', 'You can only access this page logged in.');
            return $this->redirectToRoute('app_landing');
        }

        // Handle access restrictions based on the context
        if ($context === 'dashboard' && !$this->isGranted('ROLE_ADMIN')) {
            $this->addFlash('error', 'Only admin users can access this page.');
            return $this->redirectToRoute('app_dashboard_login');
        }
        $data = $this->getSettings->getSettings($this->userRepository, $this->settingRepository);
        $form = $this->createForm(TwoFACode::class);
        if ($form->handleRequest($request)->isSubmitted() && $form->isValid()) {
            // Get the introduced code
            $formCode = $form->get('code')->getData();
            // Check if the code used is valid
            if (
                $this->twoFAService->validateOTPCodes($user, $formCode) ||
                $this->twoFAService->validate2FACode($user, $formCode)
            ) {
                $this->twoFAService->disable2FA($user);
                $this->twoFAService->event2FA(
                    $request->getClientIp(),
                    $user,
                    AnalyticalEventType::DISABLE_2FA->value,
                    $request->headers->get('User-Agent')
                );
                $this->addFlash(
                    'success',
                    'Two factor authentication successfully disabled'
                );
                if ($context === FirewallType::DASHBOARD->value) {
                    return $this->redirectToRoute('admin_page');
                }
                return $this->redirectToRoute('app_landing');
            }
        }
        return $this->render('site/twoFAAuthentication/actions/disable2FA.html.twig', [
            'data' => $data,
            'form' => $form,
            'user' => $user,
            'swap' => false,
            'context' => $context
        ]);
    }

    #[Route(
        '/{context}/disable2FA/TOTP',
        name: 'app_disable2FA_TOTP',
        defaults: [
            'context' => FirewallType::LANDING->value
        ]
    )]
    public function disable2FAApp(string $context, Request $request): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        // Ensure the user is logged in
        if (!$user instanceof UserInterface) {
            $this->addFlash('error', 'You can only access this page logged in.');
            return $this->redirectToRoute('app_landing');
        }

        // Handle access restrictions based on the context
        if ($context === 'dashboard' && !$this->isGranted('ROLE_ADMIN')) {
            $this->addFlash('error', 'Only admin users can access this page.');
            return $this->redirectToRoute('app_dashboard_login');
        }
        $data = $this->getSettings->getSettings($this->userRepository, $this->settingRepository);
        $form = $this->createForm(TwoFACode::class);
        if ($form->handleRequest($request)->isSubmitted() && $form->isValid()) {
            // Get the introduced code
            $formCode = $form->get('code')->getData();
            // Get the secret code to communicate with app.
            $secret = $user->gettwoFASecret();
            // Check if the code used is valid
            if (
                $this->twoFAService->validateOTPCodes($user, $formCode) ||
                $this->totpService->verifyTOTP($secret, $formCode)
            ) {
                $this->twoFAService->disable2FA($user);
                $this->twoFAService->event2FA(
                    $request->getClientIp(),
                    $user,
                    AnalyticalEventType::DISABLE_2FA->value,
                    $request->headers->get('User-Agent')
                );
                $this->addFlash(
                    'success',
                    'Two factor authentication successfully disabled'
                );
                if ($context === FirewallType::DASHBOARD->value) {
                    return $this->redirectToRoute('admin_page');
                }
                return $this->redirectToRoute('app_landing');
            }
        }
        return $this->render('site/twoFAAuthentication/actions/disable2FA.html.twig', [
            'data' => $data,
            'form' => $form,
            'user' => $user,
            'swap' => false
        ]);
    }

    #[Route(
        '/{context}/2FAFirstSetup/codes',
        name: 'app_otpCodes',
        defaults: [
            'context' => FirewallType::LANDING->value
        ]
    )]
    public function twoFACodes(string $context, Request $request): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        // Ensure the user is logged in
        if (!$user instanceof UserInterface) {
            $this->addFlash('error', 'You can only access this page logged in.');
            return $this->redirectToRoute('app_landing');
        }

        // Handle access restrictions based on the context
        if ($context === FirewallType::DASHBOARD->value && !$this->isGranted('ROLE_ADMIN')) {
            $this->addFlash('error', 'Only admin users can access this page.');
            return $this->redirectToRoute('app_dashboard_login');
        }
        $data = $this->getSettings->getSettings($this->userRepository, $this->settingRepository);
        $session = $request->getSession();
        if ($this->twoFAService->hasValidOTPCodes($user)) {
            return $this->redirectToRoute('app_landing');
        }
        if ($this->twoFAService->twoFAisActive($user)) {
            $session_admin = $session->get('session_admin');
            if ($session_admin) {
                return $this->redirectToRoute('admin_page');
            }
            return $this->redirectToRoute('app_landing');
        }
        if ($user instanceof User) {
            if ($user->getOTPcodes()->isEmpty()) {
                $this->twoFAService->generateOTPCodes($user);
            }
            return $this->render('site/twoFAAuthentication/otpCodes.html.twig', [
                'data' => $data,
                'codes' => $user->getOTPcodes(),
                'user' => $user,
                'context' => $context
            ]);
        }
        $this->addFlash('error', 'User not found');
        if ($context === FirewallType::DASHBOARD->value) {
            return $this->redirectToRoute('admin_page');
        }
        return $this->redirectToRoute('app_landing');
    }

    /**
     * @throws \JsonException
     */
    #[Route(
        '/{context}/2FAFirstSetup/codes/save',
        name: 'app_otpCodes_save',
        defaults: [
            'context' => FirewallType::LANDING->value
        ]
    )]
    public function saveCodes(string $context, Request $request): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        // Ensure the user is logged in
        if (!$user instanceof UserInterface) {
            $this->addFlash('error', 'You can only access this page logged in.');
            return $this->redirectToRoute('app_landing');
        }

        // Handle access restrictions based on the context
        if ($context === FirewallType::DASHBOARD->value && !$this->isGranted('ROLE_ADMIN')) {
            $this->addFlash('error', 'Only admin users can access this page.');
            return $this->redirectToRoute('app_dashboard_login');
        }

        $this->twoFAService->saveCodes($user);
        $this->twoFAService->event2FA(
            $request->getClientIp(),
            $user,
            AnalyticalEventType::GENERATE_OTP_2FA->value,
            $request->headers->get('User-Agent')
        );
        if ($context === FirewallType::DASHBOARD->value) {
            return $this->redirectToRoute('admin_page');
        }
        return $this->redirectToRoute('app_landing');
    }

    /**
     * @throws \DateMalformedStringException
     * @throws RandomException
     */
    #[Route(
        '/{context}/verify2FA/resend',
        name: 'app_2FA_local_resend_code',
        defaults: [
            'context' => FirewallType::LANDING->value
        ]
    )]
    public function resendCode(string $context, Request $request): RedirectResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        // Ensure the user is logged in
        if (!$user instanceof UserInterface) {
            $this->addFlash('error', 'You can only access this page while logged in.');
            return $this->redirectToRoute('app_landing');
        }

        // Handle access restrictions based on the context
        if ($context === FirewallType::DASHBOARD->value && !$this->isGranted('ROLE_ADMIN')) {
            $this->addFlash('error', 'Only admin users can access this page.');
            return $this->redirectToRoute('app_dashboard_login');
        }
        $data = $this->getSettings->getSettings($this->userRepository, $this->settingRepository);
        $timeToResetAttempts = $data["TWO_FACTOR_AUTH_TIME_RESET_ATTEMPTS"]["value"];
        $nrAttempts = $data["TWO_FACTOR_AUTH_ATTEMPTS_NUMBER_RESEND_CODE"]["value"];
        $timeIntervalToResendCode = $data["TWO_FACTOR_AUTH_RESEND_INTERVAL"]["value"];
        $limitTime = new DateTime();
        $limitTime->modify('-' . $timeToResetAttempts . ' minutes');
        if ($this->twoFAService->canResendCode($user) && $this->twoFAService->timeIntervalToResendCode($user)) {
            $this->twoFAService->resendCode(
                $user,
                $request->getClientIp(),
                $request->headers->get('User-Agent'),
                AnalyticalEventType::TWO_FA_CODE_RESEND->value
            );
            $attempts = $this->eventRepository->find2FACodeAttemptEvent(
                $user,
                $nrAttempts,
                $limitTime,
                AnalyticalEventType::TWO_FA_CODE_RESEND->value
            );
            $attemptsLeft = $nrAttempts - count($attempts);
            $this->addFlash(
                'success',
                'The code was resent successfully. You have ' . $attemptsLeft . ' attempts.'
            );
        } else {
            $lastEvent = $this->eventRepository->findLatest2FACodeAttemptEvent(
                $user,
                AnalyticalEventType::TWO_FA_CODE_RESEND->value
            );
            $now = new DateTime();
            if (!$this->twoFAService->canResendCode($user)) {
                $lastAttemptTime = $lastEvent instanceof Event ?
                    $lastEvent->getEventDatetime() : $timeToResetAttempts;
                $limitTime = $lastAttemptTime;
                $limitTime->modify('+' . $timeToResetAttempts . ' minutes');
                $interval = date_diff($now, $limitTime);
                $interval_minutes = $interval->days * 1440;
                $interval_minutes += $interval->h * 60;
                $interval_minutes += $interval->i;
                $this->addFlash(
                    'error',
                    'You have exceeded the number of attempts, wait ' .
                    $interval_minutes . ' minutes to request a code again'
                );
            } else {
                $lastAttemptTime = $lastEvent instanceof Event ?
                    $lastEvent->getEventDatetime() : $timeIntervalToResendCode;
                $limitTime = $lastAttemptTime;
                $limitTime->modify('+' . $timeIntervalToResendCode . ' seconds');
                $interval = date_diff($now, $limitTime);
                $interval_seconds = $interval->days * 1440;
                $interval_seconds += $interval->h * 60;
                $interval_seconds += $interval->i;
                $interval_seconds += $interval->s;
                $this->addFlash(
                    'error',
                    'You must wait ' .
                    $interval_seconds . ' seconds before you can resend code'
                );
            }
        }
        $lastPage = $request->headers->get('referer', '/');
        return $this->redirect($lastPage);
    }

    #[Route(
        '/{context}/generate2FACode',
        name: 'app_2FA_generate_code',
        defaults: [
            'context' => FirewallType::LANDING->value
        ]
    )]
    public function generateCode(string $context, Request $request): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        // Ensure the user is logged in
        if (!$user instanceof UserInterface) {
            $this->addFlash('error', 'You can only access this page while logged in.');
            return $this->redirectToRoute('app_landing');
        }

        // Handle access restrictions based on the context
        if ($context === FirewallType::DASHBOARD->value && !$this->isGranted('ROLE_ADMIN')) {
            $this->addFlash('error', 'Only admin users can access this page.');
            return $this->redirectToRoute('app_verify2FA_portal', [
                'context' => $context
            ]);
        }

        if ($this->twoFAService->canValidationCode($user, AnalyticalEventType::TWO_FA_CODE_VERIFY->value)) {
            $this->twoFAService->generate2FACode(
                $user,
                $request->getClientIp(),
                $request->headers->get('User-Agent'),
                AnalyticalEventType::TWO_FA_CODE_VERIFY->value
            );
            $this->addFlash(
                'success',
                'A confirmation code was sent to you successfully.'
            );
            return $this->redirectToRoute('app_verify2FA_portal', [
                'context' => $context
            ]);
        }
        $interval_minutes = $this->twoFAService->timeLeftToResendCode(
            $user,
            AnalyticalEventType::TWO_FA_CODE_VERIFY->value
        );
        $this->addFlash(
            'error',
            'Your code has already been sent to you previously. Wait ' .
            $interval_minutes . ' minutes to request a code again'
        );
        return $this->redirectToRoute('app_verify2FA_portal', [
            'context' => $context
        ]);
    }

    /**
     * @throws \JsonException
     */
    #[Route(
        '/{context}/downloadCodes',
        name: 'app_download_codes',
        defaults: [
            'context' => FirewallType::LANDING->value
        ]
    )]
    public function downloadCodes(string $context): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        // Ensure the user is logged in
        if (!$user instanceof UserInterface) {
            $this->addFlash('error', 'You can only access this page while logged in.');
            return $this->redirectToRoute('app_landing');
        }

        // Handle access restrictions based on the context
        if ($context === FirewallType::DASHBOARD->value && !$this->isGranted('ROLE_ADMIN')) {
            $this->addFlash('error', 'Only admin users can access this page.');
            return $this->redirectToRoute('app_dashboard_login');
        }

        $codes = [];
        foreach ($user->getOTPcodes() as $code) {
            $codes[] = $code->getCode();
        }

        // create a content of the file
        $fileContent = implode("\n", $codes);

        // response for file download
        $response = new StreamedResponse(function () use ($fileContent): void {
            echo $fileContent;
        });

        // headers for download
        $response->headers->set('Content-Type', 'text/plain');
        $response->headers->set('Content-Disposition', 'attachment;filename="codes.txt"');

        return $response;
    }

    #[Route(
        '/{context}/2FAFirstSetup/portal',
        name: 'app_2FA_firstSetup_local',
        defaults: [
            'context' => FirewallType::LANDING->value
        ]
    )]
    public function firstSetupPortal(string $context, Request $request): RedirectResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        // Ensure the user is logged in
        if (!$user instanceof UserInterface) {
            $this->addFlash('error', 'You can only access this page while logged in.');
            return $this->redirectToRoute('app_landing');
        }

        // Handle access restrictions based on the context
        if ($context === FirewallType::DASHBOARD->value && !$this->isGranted('ROLE_ADMIN')) {
            $this->addFlash('error', 'Only admin users can access this page.');
            return $this->redirectToRoute('app_dashboard_login');
        }

        if ($user->getTwoFAtype() === UserTwoFactorAuthenticationStatus::TOTP->value) {
            return $this->redirectToRoute('app_swap2FA_disable_TOTP', [
                'context' => $context
            ]);
        }
        $data = $this->getSettings->getSettings($this->userRepository, $this->settingRepository);
        $timeToResetAttempts = $data["TWO_FACTOR_AUTH_TIME_RESET_ATTEMPTS"]["value"];
        $limitTime = new DateTime();
        $limitTime->modify('-' . $timeToResetAttempts . ' minutes');
        if ($this->twoFAService->canValidationCode($user, AnalyticalEventType::TWO_FA_CODE_ENABLE->value)) {
            $this->twoFAService->generate2FACode(
                $user,
                $request->getClientIp(),
                $request->headers->get('User-Agent'),
                AnalyticalEventType::TWO_FA_CODE_ENABLE->value
            );
            $this->addFlash(
                'success',
                'A confirmation code was sent to you successfully.'
            );
            return $this->redirectToRoute('app_2FA_first_verification_local', [
                'context' => $context
            ]);
        }
        $interval_minutes = $this->twoFAService->timeLeftToResendCode(
            $user,
            AnalyticalEventType::TWO_FA_CODE_ENABLE->value
        );
        $this->addFlash(
            'error',
            'Your code has already been sent to you previously. Wait ' .
            $interval_minutes . ' minutes to request a code again'
        );

        return $this->redirectToRoute('app_2FA_first_verification_local', [
            'context' => $context
        ]);
    }

    #[Route(
        '/{context}/2FAFirstSetup/verification',
        name: 'app_2FA_first_verification_local',
        defaults: [
            'context' => FirewallType::LANDING->value
        ]
    )]
    public function firstVerificationLocal(string $context, Request $request): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        // Ensure the user is logged in
        if (!$user instanceof UserInterface) {
            $this->addFlash('error', 'You can only access this page while logged in.');
            return $this->redirectToRoute('app_landing');
        }

        // Handle access restrictions based on the context
        if ($context === FirewallType::DASHBOARD->value && !$this->isGranted('ROLE_ADMIN')) {
            $this->addFlash('error', 'Only admin users can access this page.');
            return $this->redirectToRoute('app_dashboard_login');
        }

        $session = $request->getSession();
        $data = $this->getSettings->getSettings($this->userRepository, $this->settingRepository);
        $form = $this->createForm(TwoFACode::class);
        if ($form->handleRequest($request)->isSubmitted() && $form->isValid()) {
            // Get the introduced code
            $formCode = $form->get('code')->getData();
            // Check if the code used is the one generated in the BD.
            if ($this->twoFAService->validate2FACode($user, $formCode)) {
                $session->set('2fa_verified', true);
                if ($user->getPhoneNumber() instanceof PhoneNumber) {
                    if ($user instanceof User) {
                        $user->setTwoFAtype(UserTwoFactorAuthenticationStatus::SMS->value);
                        $this->entityManager->persist($user);
                        $this->twoFAService->event2FA(
                            $request->getClientIp(),
                            $user,
                            AnalyticalEventType::ENABLE_LOCAL_2FA->value,
                            $request->headers->get('User-Agent')
                        );
                        $this->entityManager->flush();
                    } else {
                        $this->addFlash('error', 'You must be logged in to access this page');
                        if ($context === FirewallType::DASHBOARD->value) {
                            return $this->redirectToRoute('admin_page');
                        }
                        return $this->redirectToRoute('app_landing');
                    }
                    return $this->redirectToRoute('app_otpCodes', [
                        'context' => $context
                    ]);
                }
                if ($user->getEmail()) {
                    $user->setTwoFAtype(UserTwoFactorAuthenticationStatus::EMAIL->value);
                    $this->entityManager->persist($user);
                    $this->twoFAService->event2FA(
                        $request->getClientIp(),
                        $user,
                        AnalyticalEventType::ENABLE_LOCAL_2FA->value,
                        $request->headers->get('User-Agent')
                    );
                    $this->entityManager->flush();
                } else {
                    $this->addFlash('error', 'You must be logged in to access this page');
                    if ($context === FirewallType::DASHBOARD->value) {
                        return $this->redirectToRoute('admin_page');
                    }
                    return $this->redirectToRoute('app_landing');
                }
                return $this->redirectToRoute('app_otpCodes', [
                    'context' => $context
                ]);
            }
            $this->addFlash(
                'error',
                'Invalid code! The code may be wrong or may have already expired. Please try again or resend the code'
            );
        }
        return $this->render('site/twoFAAuthentication/validate/validate2FA.html.twig', [
            'data' => $data,
            'form' => $form,
            'user' => $user,
            'context' => $context
        ]);
    }

    #[Route(
        '/{context}/2FASwapMethod/disableLocal',
        name: 'app_swap2FA_disable_Local',
        defaults: [
            'context' => FirewallType::LANDING->value
        ]
    )]
    public function swapMethod2FADisableLocal(string $context, Request $request): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        // Ensure the user is logged in
        if (!$user instanceof UserInterface) {
            $this->addFlash('error', 'You can only access this page while logged in.');
            return $this->redirectToRoute('app_landing');
        }

        // Handle access restrictions based on the context
        if ($context === FirewallType::DASHBOARD->value && !$this->isGranted('ROLE_ADMIN')) {
            $this->addFlash('error', 'Only admin users can access this page.');
            return $this->redirectToRoute('app_dashboard_login');
        }

        if ($user->getTwoFAtype() === UserTwoFactorAuthenticationStatus::DISABLED->value) {
            $this->addFlash(
                'error',
                'This account already has two factor authentication disabled.'
            );
            return $this->redirectToRoute('app_configure2FA', [
                'context' => $context
            ]);
        }
        $data = $this->getSettings->getSettings($this->userRepository, $this->settingRepository);
        $form = $this->createForm(TwoFACode::class);
        if ($form->handleRequest($request)->isSubmitted() && $form->isValid()) {
            // Get the introduced code
            $formCode = $form->get('code')->getData();
            // Check if the code used is valid
            if (
                $this->twoFAService->validateOTPCodes($user, $formCode) ||
                $this->twoFAService->validate2FACode($user, $formCode)
            ) {
                $this->twoFAService->disable2FA($user);
                $this->twoFAService->event2FA(
                    $request->getClientIp(),
                    $user,
                    AnalyticalEventType::DISABLE_2FA->value,
                    $request->headers->get('User-Agent')
                );
                $this->addFlash(
                    'success',
                    'Two factor authentication successfully disabled'
                );
                return $this->redirectToRoute('app_enable2FA_TOTP', [
                    'context' => $context
                ]);
            }
            $this->addFlash('error', 'Invalid code please try again or resend the code');
        }
        return $this->render('site/twoFAAuthentication/actions/disable2FA.html.twig', [
            'data' => $data,
            'form' => $form,
            'user' => $user,
            'swap' => true,
            'context' => $context
        ]);
    }

    #[Route(
        '/{context}/generate2FACode/swapMethod',
        name: 'app_2FA_generate_code_swap_method',
        defaults: [
            'context' => FirewallType::LANDING->value
        ]
    )]
    public function generateCodeSwapMethod(string $context, Request $request): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        // Ensure the user is logged in
        if (!$user instanceof UserInterface) {
            $this->addFlash('error', 'You can only access this page while logged in.');
            return $this->redirectToRoute('app_landing');
        }

        // Handle access restrictions based on the context
        if ($context === FirewallType::DASHBOARD->value && !$this->isGranted('ROLE_ADMIN')) {
            $this->addFlash('error', 'Only admin users can access this page.');
            return $this->redirectToRoute('app_dashboard_login');
        }

        if ($this->twoFAService->canValidationCode($user, AnalyticalEventType::TWO_FA_CODE_DISABLE->value)) {
            $this->twoFAService->generate2FACode(
                $user,
                $request->getClientIp(),
                $request->headers->get('User-Agent'),
                AnalyticalEventType::TWO_FA_CODE_DISABLE->value
            );
            $this->addFlash(
                'success',
                'A confirmation code was sent to you successfully.'
            );
            return $this->redirectToRoute('app_swap2FA_disable_Local', [
                'context' => $context
            ]);
        }
        $interval_minutes = $this->twoFAService->timeLeftToResendCode(
            $user,
            AnalyticalEventType::TWO_FA_CODE_DISABLE->value
        );
        $this->addFlash(
            'error',
            'Your code has already been sent to you previously. Wait ' .
            $interval_minutes . ' minutes to request a code again'
        );
        return $this->redirectToRoute('app_swap2FA_disable_Local', [
            'context' => $context
        ]);
    }

    #[Route(
        '/{context}/2FASwapMethod/disable/TOTP',
        name: 'app_swap2FA_disable_TOTP',
        defaults: [
            'context' => FirewallType::LANDING->value
        ]
    )]
    public function swapMethod2FADisableTOTP(string $context, Request $request): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        // Ensure the user is logged in
        if (!$user instanceof UserInterface) {
            $this->addFlash('error', 'You can only access this page while logged in.');
            return $this->redirectToRoute('app_landing');
        }

        // Handle access restrictions based on the context
        if ($context === FirewallType::DASHBOARD->value && !$this->isGranted('ROLE_ADMIN')) {
            $this->addFlash('error', 'Only admin users can access this page.');
            return $this->redirectToRoute('app_dashboard_login');
        }

        if ($user->getTwoFAtype() === UserTwoFactorAuthenticationStatus::DISABLED->value) {
            $this->addFlash(
                'error',
                'This account already has two factor authentication disabled.'
            );
            return $this->redirectToRoute('app_configure2FA', [
                'context' => $context
            ]);
        }
        $data = $this->getSettings->getSettings($this->userRepository, $this->settingRepository);
        $form = $this->createForm(TwoFACode::class);
        if ($form->handleRequest($request)->isSubmitted() && $form->isValid()) {
            // Get the introduced code
            $formCode = $form->get('code')->getData();
            // Get the secret code to communicate with app.
            $secret = $user->gettwoFASecret();
            // Check if the code used is valid
            if (
                $this->twoFAService->validateOTPCodes($user, $formCode) ||
                $this->totpService->verifyTOTP($secret, $formCode)
            ) {
                $this->twoFAService->disable2FA($user);
                $this->twoFAService->event2FA(
                    $request->getClientIp(),
                    $user,
                    AnalyticalEventType::DISABLE_2FA->value,
                    $request->headers->get('User-Agent')
                );
                $this->addFlash(
                    'success',
                    'Two factor authentication successfully disabled'
                );
                return $this->redirectToRoute('app_2FA_firstSetup_local', [
                    'context' => $context
                ]);
            }
            $this->addFlash('error', 'Invalid code');
        }
        return $this->render('site/twoFAAuthentication/actions/disable2FA.html.twig', [
            'data' => $data,
            'form' => $form,
            'user' => $user,
            'swap' => true,
            'context' => $context
        ]);
    }
}
