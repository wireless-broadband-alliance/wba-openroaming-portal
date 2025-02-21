<?php

namespace App\Controller;

use App\Entity\TwoFactorAuthentication;
use App\Entity\User;
use App\Enum\AnalyticalEventType;
use App\Enum\PlatformMode;
use App\Enum\UserTwoFactorAuthenticationStatus;
use App\Form\TwoFAcode;
use App\Form\TwoFactorPhoneNumber;
use App\Repository\SettingRepository;
use App\Repository\UserRepository;
use App\Service\EventActions;
use App\Service\GetSettings;
use App\Service\TOTPService;
use App\Service\TwoFAService;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Endroid\QrCode\QrCode;
use Endroid\QrCode\Writer\PngWriter;
use libphonenumber\PhoneNumber;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class TwoFAController extends AbstractController
{
    /**
     * SiteController constructor.
     * @param UserRepository $userRepository The repository for accessing user data.
     * @param SettingRepository $settingRepository The setting repository is used to create the getSettings function.
     * @param GetSettings $getSettings The instance of GetSettings class.
     * @param TOTPService $totpService The service for communicate with two factor authentication applications
     * @param EntityManagerInterface $entityManager The service for manage all entities
     * @param TwoFAService $twoFAService Generates a new codes and configure 2FA
     *  of the user account
     */
    public function __construct(
        private readonly UserRepository $userRepository,
        private readonly SettingRepository $settingRepository,
        private readonly GetSettings $getSettings,
        private readonly TOTPService $totpService,
        private readonly EntityManagerInterface $entityManager,
        private readonly EventActions $eventActions,
        private readonly TwoFAService $twoFAService,
    ) {
    }

    #[Route(path: '/enable2FA', name: 'app_enable2FA')]
    public function enable2FA(Request $request): Response
    {
        $data = $this->getSettings->getSettings($this->userRepository, $this->settingRepository);
        /** @var User $user */
        $user = $this->getUser();
        if (!$user instanceof User) {
            $this->addFlash('error', 'You must be logged in to access this page');
            return $this->redirectToRoute('app_landing');
        }
        // If the user doesn't have an instance of 'TwoFactorAuthentication' we need to create one for him.
        if (!$user->getTwoFactorAuthentication() instanceof TwoFactorAuthentication) {
            $twoFA = new TwoFactorAuthentication();
            $twoFA->setUser($user);
            $twoFA->setType(UserTwoFactorAuthenticationStatus::DISABLED->value);
            $user->setTwoFactorAuthentication($twoFA);
            $this->entityManager->persist($twoFA);
        }
        $form = $this->createForm(TwoFactorPhoneNumber::class, $user);
        // if the user already has a phone number in the bd, there is no need to type it again.
        if ($user->getPhoneNumber() instanceof PhoneNumber) {
            if ($user instanceof User) {
                $user->getTwoFactorAuthentication()->setType(UserTwoFactorAuthenticationStatus::SMS->value);
                $this->entityManager->persist($user);
                $eventMetaData = [
                    'platform' => PlatformMode::LIVE->value,
                    'uuid' => $user->getUuid(),
                    'ip' => $request->getClientIp(),
                ];
                $this->eventActions->saveEvent(
                    $user,
                    AnalyticalEventType::ENABLE_LOCAL_2FA->value,
                    new DateTime(),
                    $eventMetaData
                );
                $this->entityManager->flush();
            } else {
                $this->addFlash('error', 'You must be logged in to access this page');
                return $this->redirectToRoute('app_landing');
            }
            return $this->redirectToRoute('app_otpCodes');
        }
        // If he doesn't have it, he has to type it
        if ($form->handleRequest($request)->isSubmitted() && $form->isValid()) {
            $phoneNumber = $form->get('phoneNumber')->getData();
            if ($user instanceof User) {
                $user->getTwoFactorAuthentication()->setType(UserTwoFactorAuthenticationStatus::SMS->value);
                $user->setPhoneNumber($phoneNumber);
                $this->entityManager->persist($user);
                $this->entityManager->flush();
                $eventMetaData = [
                    'platform' => PlatformMode::LIVE->value,
                    'uuid' => $user->getUuid(),
                    'ip' => $request->getClientIp(),
                ];
                $this->eventActions->saveEvent(
                    $user,
                    AnalyticalEventType::ENABLE_LOCAL_2FA->value,
                    new DateTime(),
                    $eventMetaData
                );
            } else {
                $this->addFlash('error', 'You must be logged in to access this page');
                return $this->redirectToRoute('app_landing');
            }
            // After that we give him the otp codes
            return $this->redirectToRoute('app_otpCodes');
        }
        return $this->render('site/enable2FA.html.twig', [
            'data' => $data,
            'form' => $form,
        ]);
    }

    #[Route(path: '/enable2FAapp', name: 'app_enable2FA_app')]
    public function enable2FAapp(): Response
    {
        $data = $this->getSettings->getSettings($this->userRepository, $this->settingRepository);
        $user = $this->getUser();
        $secret = $this->totpService->generateSecret();
        if ($user instanceof User) {
            // If the user doesn't have an instance of 'TwoFactorAuthentication' we need to create one for him.
            if (!$user->getTwoFActorAuthentication() instanceof TwoFactorAuthentication) {
                $twoFA = new TwoFactorAuthentication();
                $twoFA->setUser($user);
                $twoFA->setType(UserTwoFactorAuthenticationStatus::DISABLED->value);
                $user->setTwoFactorAuthentication($twoFA);
                $this->entityManager->persist($twoFA);
            }
            $twoFA = $user->getTwoFactorAuthentication();
            $twoFA->setSecret($secret);
            $this->entityManager->persist($user);
            $this->entityManager->persist($twoFA);
            $this->entityManager->flush();
        } else {
            $this->addFlash('error', 'You must be logged in to access this page');
            return $this->redirectToRoute('app_landing');
        }

        $formattedSecret = implode(' ', str_split($secret, 10));

        $provisioningUri = $this->totpService->generateTOTP($secret);
        // Generate the qr code to activate 2fa through the app.
        $qrCode = new QrCode($provisioningUri);
        $writer = new PngWriter();
        $qrCodeResult = $writer->write($qrCode);
        $qrCodeImage = base64_encode($qrCodeResult->getString());

        return $this->render('site/enable2FAapp.html.twig', [
            'qrCodeImage' => $qrCodeImage,
            'provisioningUri' => $provisioningUri,
            'secret' => $formattedSecret,
            'data' => $data,
        ]);
    }

    #[Route(path: '/verify2FAapp', name: 'app_verify2FA_app')]
    public function verify2FA(Request $request): Response
    {
        $data = $this->getSettings->getSettings($this->userRepository, $this->settingRepository);
        $form = $this->createForm(TwoFAcode::class);
        $session = $request->getSession();
        if ($form->handleRequest($request)->isSubmitted() && $form->isValid()) {
            // Get the introduced code
            $code = $form->get('code')->getData();
            $user = $this->getUser();
            if ($user instanceof User) {
                // Get the secret code to communicate with app.
                $secret = $user->getTwoFactorAuthentication()->getSecret();
                // Check if the used code is one of the OTP codes
                if ($this->twoFAService->validateOTPCodes($user, $code)) {
                    $session->set('2fa_verified', true);
                    $eventMetaData = [
                        'platform' => PlatformMode::LIVE->value,
                        'uuid' => $user->getUuid(),
                        'ip' => $request->getClientIp(),
                    ];
                    $this->eventActions->saveEvent(
                        $user,
                        AnalyticalEventType::VERIFY_OTP_2FA->value,
                        new DateTime(),
                        $eventMetaData
                    );
                    return $this->redirectToRoute('app_landing');
                }
                // Check if the code used is the one generated in the application.
                if ($this->totpService->verifyTOTP($secret, $code)) {
                    $session->set('2fa_verified', true);
                    $eventMetaData = [
                        'platform' => PlatformMode::LIVE->value,
                        'uuid' => $user->getUuid(),
                        'ip' => $request->getClientIp(),
                    ];
                    $this->eventActions->saveEvent(
                        $user,
                        AnalyticalEventType::VERIFY_APP_2FA->value,
                        new DateTime(),
                        $eventMetaData
                    );
                    return $this->redirectToRoute('app_landing');
                }
                $this->addFlash('error', 'Invalid code');
            }
        }
        return $this->render('site/verify2FA.html.twig', [
            'data' => $data,
            'form' => $form,
        ]);
    }

    #[Route(path: '/verify2FA', name: 'app_verify2FA_local')]
    public function verify2FAlocal(Request $request): Response
    {
        $data = $this->getSettings->getSettings($this->userRepository, $this->settingRepository);
        $form = $this->createForm(TwoFAcode::class);
        /** @var User $user */
        $user = $this->getUser();
        $this->twoFAService->generate2FACode($user);
        $session = $request->getSession();
        if ($form->handleRequest($request)->isSubmitted() && $form->isValid()) {
            // Get the introduced code
            $formCode = $form->get('code')->getData();
            // Check if the used code is one of the OTP codes
            if ($this->twoFAService->validateOTPCodes($user, $formCode)) {
                $session->set('2fa_verified', true);
                $eventMetaData = [
                    'platform' => PlatformMode::LIVE->value,
                    'uuid' => $user->getUuid(),
                    'ip' => $request->getClientIp(),
                ];
                $this->eventActions->saveEvent(
                    $user,
                    AnalyticalEventType::VERIFY_OTP_2FA->value,
                    new DateTime(),
                    $eventMetaData
                );
                return $this->redirectToRoute('app_landing');
            }
            // Check if the code used is the one generated in the BD.
            if ($this->twoFAService->validate2FACode($user, $formCode)) {
                $session->set('2fa_verified', true);
                $eventMetaData = [
                    'platform' => PlatformMode::LIVE->value,
                    'uuid' => $user->getUuid(),
                    'ip' => $request->getClientIp(),
                ];
                $this->eventActions->saveEvent(
                    $user,
                    AnalyticalEventType::VERIFY_LOCAL_2FA->value,
                    new DateTime(),
                    $eventMetaData
                );
                return $this->redirectToRoute('app_landing');
            }
            $this->addFlash('error', 'Invalid code please try again or resend the code');
        }
        return $this->render('site/verify2FAlocal.html.twig', [
            'data' => $data,
            'form' => $form,
        ]);
    }

    #[Route(path: '/disable2FA', name: 'app_disable2FA')]
    public function disable2FA(Request $request): RedirectResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        if ($user && $user->getTwoFactorAuthentication()) {
            /** @var TwoFactorAuthentication $twoFA */
            $twoFA = $user->getTwoFactorAuthentication();
            // Mark 2fa as disabled.
            $twoFA->setType(UserTwoFactorAuthenticationStatus::DISABLED->value);
            $this->entityManager->persist($twoFA);
            $this->entityManager->persist($user);
            $this->entityManager->flush();
            $eventMetaData = [
                'platform' => PlatformMode::LIVE->value,
                'uuid' => $user->getUuid(),
                'ip' => $request->getClientIp(),
            ];
            $this->eventActions->saveEvent(
                $user,
                AnalyticalEventType::DISABLE_2FA->value,
                new DateTime(),
                $eventMetaData
            );
        } else {
            $this->addFlash('error', 'You must be logged in to access this page');
            return $this->redirectToRoute('app_landing');
        }
        return $this->redirectToRoute('app_landing');
    }

    #[Route(path: '/enable2FAapp/validate', name: 'app_enable2FA_app_confirm', methods: ['POST'])]
    public function enable2FAappValidate(Request $request): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        if ($user) {
            $twoFA = $user->getTwoFactorAuthentication();
            if ($twoFA instanceof TwoFactorAuthentication) {
                //Mark 2fa as Enable via app.
                $twoFA->setType(UserTwoFactorAuthenticationStatus::APP->value);
                $this->entityManager->persist($twoFA);
                $this->entityManager->persist($user);
                $this->entityManager->flush();
                $eventMetaData = [
                    'platform' => PlatformMode::LIVE->value,
                    'uuid' => $user->getUuid(),
                    'ip' => $request->getClientIp(),
                ];
                $this->eventActions->saveEvent(
                    $user,
                    AnalyticalEventType::ENABLE_APP_2FA->value,
                    new DateTime(),
                    $eventMetaData
                );
            }
        }
        return $this->redirectToRoute('app_otpCodes');
    }

    #[Route(path: '/enable2FA/codes', name: 'app_otpCodes')]
    public function twoFAcodes(Request $request): Response
    {
        $data = $this->getSettings->getSettings($this->userRepository, $this->settingRepository);
        $user = $this->getUser();
        if ($user instanceof User) {
            // Generate OTP codes
            $this->twoFAService->generateOTPcodes($user);
            $eventMetaData = [
                'platform' => PlatformMode::LIVE->value,
                'uuid' => $user->getUuid(),
                'ip' => $request->getClientIp(),
            ];
            $this->eventActions->saveEvent(
                $user,
                AnalyticalEventType::GENERATE_OTP_2FA->value,
                new DateTime(),
                $eventMetaData
            );
            return $this->render('site/otpCodes.html.twig', [
                'data' => $data,
                'codes' => $user->getTwoFactorAuthentication()->getOTPcodes()
            ]);
        }
        $this->addFlash('error', 'User not found');
        return $this->redirectToRoute('app_otpCodes');
    }
}
