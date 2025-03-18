<?php

namespace App\Controller;

use App\Entity\Event;
use App\Entity\User;
use App\Enum\AnalyticalEventType;
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

    #[Route('/configure2FA', name: 'app_configure2FA')]
    public function configure2FA(): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        if (!$user instanceof UserInterface) {
            $this->addFlash('error', 'You can\'t access this page logged in. ');
            return $this->redirectToRoute('app_landing');
        }
        $data = $this->getSettings->getSettings($this->userRepository, $this->settingRepository);

        return $this->render('site/twoFAAuthentication/base_configuration.html.twig', [
            'user' => $user,
            'data' => $data,
        ]);
    }

    #[Route('/enable2FA/TOTP', name: 'app_enable2FA_TOTP')]
    public function enable2FATOTP(Request $request): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        if (!$user instanceof UserInterface) {
            $this->addFlash('error', 'You can\'t access this page logged in. ');
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
                    return $this->redirectToRoute('app_otpCodes');
                }
                $this->addFlash('error', 'Invalid code');
            }
        }
        $secret = $this->totpService->generateSecret();
        if ($user instanceof User) {
            if (
                $user->getTwoFAtype() === UserTwoFactorAuthenticationStatus::SMS->value ||
                $user->getTwoFAtype() === UserTwoFactorAuthenticationStatus::EMAIL->value
            ) {
                return $this->redirectToRoute('app_2FA_generate_code_swap_method');
            }
            $user->setTwoFAsecret($secret);
            $this->entityManager->persist($user);
            $this->entityManager->flush();
        } else {
            $this->addFlash('error', 'You must be logged in to access this page');
            $session_admin = $session->get('session_admin');
            if ($session_admin) {
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
        ]);
    }

    #[Route('/verify2FA/TOTP', name: 'app_verify2FA_TOTP')]
    public function verify2FA(Request $request): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        $session = $request->getSession();
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
                    $session_admin = $session->get('session_admin');
                    if ($session_admin) {
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
                    $session_admin = $session->get('session_admin');
                    if ($session_admin) {
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

    #[Route('/verify2FA', name: 'app_verify2FA_portal')]
    public function verify2FAPortal(Request $request): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        if (!$user instanceof UserInterface) {
            $this->addFlash('error', 'You can\'t access this page logged in. ');
            return $this->redirectToRoute('app_landing');
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
                $session_admin = $session->get('session_admin');
                if ($session_admin) {
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
                $session_admin = $session->get('session_admin');
                if ($session_admin) {
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
        ]);
    }

    #[Route('/disable2FA', name: 'app_disable2FA', methods: ['POST'])]
    public function disable2FA(Request $request): RedirectResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        if (!$user instanceof UserInterface) {
            $this->addFlash('error', 'You can\'t access this page logged in. ');
            return $this->redirectToRoute('app_landing');
        }
        $session = $request->getSession();

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
                    'The code was sent successfully.'
                );
                return $this->redirectToRoute('app_disable2FA_local');
            }
            $this->addFlash(
                'error',
                'Your code has already been sent to you previously.'
            );
            return $this->redirectToRoute('app_disable2FA_local');
        }
        if ($user->getTwoFAtype() === UserTwoFactorAuthenticationStatus::TOTP->value) {
            return $this->redirectToRoute('app_disable2FA_TOTP');
        }
        if ($user->getTwoFAtype() === UserTwoFactorAuthenticationStatus::DISABLED->value) {
            $sessionAdmin = $session->get('session_admin');
            $this->addFlash('error', 'Two-Factor authentication is already disabled');
            if ($sessionAdmin) {
                return $this->redirectToRoute('admin_page');
            }
            return $this->redirectToRoute('app_landing');
        }

        return $this->redirectToRoute('app_landing');
    }

    #[Route('/disable2FA/local', name: 'app_disable2FA_local')]
    public function disable2FALocal(Request $request): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        if (!$user instanceof UserInterface) {
            $this->addFlash('error', 'You can\'t access this page logged in. ');
            return $this->redirectToRoute('app_landing');
        }
        $data = $this->getSettings->getSettings($this->userRepository, $this->settingRepository);
        $form = $this->createForm(TwoFACode::class);
        $session = $request->getSession();
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
                $session_admin = $session->get('session_admin');
                if ($session_admin) {
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

    #[Route('/disable2FA/TOTP', name: 'app_disable2FA_TOTP')]
    public function disable2FAApp(Request $request): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        if (!$user instanceof UserInterface) {
            $this->addFlash('error', 'You can\'t access this page logged in. ');
            return $this->redirectToRoute('app_landing');
        }
        $session = $request->getSession();
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
                $session_admin = $session->get('session_admin');
                if ($session_admin) {
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

    #[Route('/2FAFirstSetup/codes', name: 'app_otpCodes')]
    public function twoFACodes(Request $request): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        if (!$user instanceof UserInterface) {
            $this->addFlash('error', 'You can\'t access this page logged in. ');
            return $this->redirectToRoute('app_landing');
        }
        $data = $this->getSettings->getSettings($this->userRepository, $this->settingRepository);
        $session = $request->getSession();
        if (!$user->getOTPcodes()->isEmpty()) {
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
            $codes = $this->twoFAService->generateOTPCodes($user);
            return $this->render('site/twoFAAuthentication/otpCodes.html.twig', [
                'data' => $data,
                'codes' => $codes,
                'user' => $user,
            ]);
        }
        $this->addFlash('error', 'User not found');
        $session_admin = $session->get('session_admin');
        if ($session_admin) {
            return $this->redirectToRoute('admin_page');
        }
        return $this->redirectToRoute('app_landing');
    }

    /**
     * @throws \JsonException
     */
    #[Route('/2FAFirstSetup/codes/save', name: 'app_otpCodes_save')]
    public function saveCodes(Request $request): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        if (!$user instanceof UserInterface) {
            $this->addFlash('error', 'You can\'t access this page logged in. ');
            return $this->redirectToRoute('app_landing');
        }
        $session = $request->getSession();
        $codes = $request->query->get('codes');
        // Check if the codes was ben sent
        if (!$codes) {
            $data = json_decode($codes, true, 512, JSON_THROW_ON_ERROR);
            $codes = $data["codes"] ?? null;
        }

        // Decrypt the data sent
        $codesJson = urldecode((string)$codes);
        $codes = json_decode($codesJson, true, 512, JSON_THROW_ON_ERROR);
        $this->twoFAService->saveCodes($codes, $user);
        $this->twoFAService->event2FA(
            $request->getClientIp(),
            $user,
            AnalyticalEventType::GENERATE_OTP_2FA->value,
            $request->headers->get('User-Agent')
        );
        $session_admin = $session->get('session_admin');
        if ($session_admin) {
            return $this->redirectToRoute('admin_page');
        }
        return $this->redirectToRoute('app_landing');
    }

    /**
     * @throws \DateMalformedStringException
     * @throws RandomException
     */
    #[Route('/verify2FA/resend', name: 'app_2FA_local_resend_code')]
    public function resendCode(Request $request): RedirectResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        if (!$user instanceof UserInterface) {
            $this->addFlash('error', 'You can\'t access this page logged in. ');
            return $this->redirectToRoute('app_landing');
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

    #[Route('/generate2FACode', name: 'app_2FA_generate_code')]
    public function generateCode(Request $request): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        if (!$user instanceof UserInterface) {
            $this->addFlash('error', 'You can\'t access this page logged in. ');
            return $this->redirectToRoute('app_landing');
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
                'The code was sent successfully.'
            );
            return $this->redirectToRoute('app_verify2FA_portal');
        }
        $this->addFlash(
            'error',
            'Your code has already been sent to you previously.'
        );
        return $this->redirectToRoute('app_verify2FA_portal');
    }

    /**
     * @throws \JsonException
     */
    #[Route('/downloadCodes', name: 'app_download_codes')]
    public function downloadCodes(Request $request): Response
    {
        $codes = $request->query->get('codes');
        // Check if the codes was ben sent
        if (!$codes) {
            $data = json_decode($codes, true, 512, JSON_THROW_ON_ERROR);
            $codes = $data["codes"] ?? null;
        }
        // decrypt the data sent
        $codesJson = urldecode((string)$codes);
        $codes = json_decode($codesJson, true, 512, JSON_THROW_ON_ERROR);
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

    #[Route('/2FAFirstSetup/portal', name: 'app_2FA_firstSetup_local')]
    public function firstSetupPortal(Request $request): RedirectResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        if (!$user instanceof UserInterface) {
            $this->addFlash('error', 'You can\'t access this page logged in. ');
            return $this->redirectToRoute('app_landing');
        }
        if ($user->getTwoFAtype() === UserTwoFactorAuthenticationStatus::TOTP->value) {
            return $this->redirectToRoute('app_swap2FA_disable_TOTP');
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
                'The code was sent successfully.'
            );
            return $this->redirectToRoute('app_2FA_first_verification_local');
        }
        $this->addFlash(
            'error',
            'Your code has already been sent to you previously.'
        );

        return $this->redirectToRoute('app_2FA_first_verification_local');
    }

    #[Route('/2FAFirstSetup/verification', name: 'app_2FA_first_verification_local')]
    public function firstVerificationLocal(Request $request): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        if (!$user instanceof UserInterface) {
            $this->addFlash('error', 'You can\'t access this page logged in. ');
            return $this->redirectToRoute('app_landing');
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
                        $session_admin = $session->get('session_admin');
                        if ($session_admin) {
                            return $this->redirectToRoute('admin_page');
                        }
                        return $this->redirectToRoute('app_landing');
                    }
                    return $this->redirectToRoute('app_otpCodes');
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
                    $session_admin = $session->get('session_admin');
                    if ($session_admin) {
                        return $this->redirectToRoute('admin_page');
                    }
                    return $this->redirectToRoute('app_landing');
                }
                return $this->redirectToRoute('app_otpCodes');
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
        ]);
    }

    #[Route('/2FASwapMethod/disableLocal', name: 'app_swap2FA_disable_Local')]
    public function swapMethod2FADisableLocal(Request $request): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        if (!$user instanceof UserInterface) {
            $this->addFlash('error', 'You can\'t access this page logged in. ');
            return $this->redirectToRoute('app_landing');
        }
        if ($user->getTwoFAtype() === UserTwoFactorAuthenticationStatus::DISABLED->value) {
            $this->addFlash(
                'error',
                'This account already has two factor authentication disabled.'
            );
            return $this->redirectToRoute('app_configure2FA');
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
                return $this->redirectToRoute('app_enable2FA_TOTP');
            }
            $this->addFlash('error', 'Invalid code please try again or resend the code');
        }
        return $this->render('site/twoFAAuthentication/actions/disable2FA.html.twig', [
            'data' => $data,
            'form' => $form,
            'user' => $user,
            'swap' => true
        ]);
    }

    #[Route('/generate2FACode/swapMethod', name: 'app_2FA_generate_code_swap_method')]
    public function generateCodeSwapMethod(Request $request): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        if (!$user instanceof UserInterface) {
            $this->addFlash('error', 'You can\'t access this page logged in. ');
            return $this->redirectToRoute('app_landing');
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
                'The code was sent successfully.'
            );
            return $this->redirectToRoute('app_swap2FA_disable_Local');
        }
        $this->addFlash(
            'error',
            'Your code has already been sent to you previously.'
        );
        return $this->redirectToRoute('app_swap2FA_disable_Local');
    }

    #[Route('/2FASwapMethod/disable/TOTP', name: 'app_swap2FA_disable_TOTP')]
    public function swapMethod2FADisableTOTP(Request $request): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        if (!$user instanceof UserInterface) {
            $this->addFlash('error', 'You can\'t access this page logged in. ');
            return $this->redirectToRoute('app_landing');
        }
        if ($user->getTwoFAtype() === UserTwoFactorAuthenticationStatus::DISABLED->value) {
            $this->addFlash(
                'error',
                'This account already has two factor authentication disabled.'
            );
            return $this->redirectToRoute('app_configure2FA');
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
                return $this->redirectToRoute('app_2FA_firstSetup_local');
            }
            $this->addFlash('error', 'Invalid code');
        }
        return $this->render('site/twoFAAuthentication/actions/disable2FA.html.twig', [
            'data' => $data,
            'form' => $form,
            'user' => $user,
            'swap' => true
        ]);
    }
}
