<?php

namespace App\Controller;

use App\Entity\User;
use App\Enum\PlatformMode;
use App\Enum\twoFAType;
use App\Form\LoginFormType;
use App\Form\TwoFAcode;
use App\Form\TwoFactorPhoneNumber;
use App\Repository\SettingRepository;
use App\Repository\UserRepository;
use App\Service\GetSettings;
use App\Service\RegistrationEmailGenerator;
use App\Service\TOTPService;
use App\Service\VerificationCodeEmailGenerator;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\NonUniqueResultException;
use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\QrCode;
use Endroid\QrCode\Writer\PngWriter;
use LogicException;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\IsGranted;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;

class SecurityController extends AbstractController
{
    private UserRepository $userRepository;
    private SettingRepository $settingRepository;
    private GetSettings $getSettings;
    private TOTPService $totpService;
    private EntityManagerInterface $entityManager;

    /**
     * SiteController constructor.
     *
     * @param UserRepository $userRepository The repository for accessing user data.
     * @param SettingRepository $settingRepository The setting repository is used to create the getSettings function.
     * @param GetSettings $getSettings The instance of GetSettings class.
     */
    public function __construct(
        UserRepository $userRepository,
        SettingRepository $settingRepository,
        GetSettings $getSettings,
        TOTPService $totpService,
        EntityManagerInterface $entityManager,
        private readonly VerificationCodeEmailGenerator $verificationCodeGenerator,
    ) {
        $this->userRepository = $userRepository;
        $this->settingRepository = $settingRepository;
        $this->getSettings = $getSettings;
        $this->totpService = $totpService;
        $this->entityManager = $entityManager;
    }

    /**
     * @throws NonUniqueResultException
     */
    #[Route('/login/{type}', name: 'app_login')]
    public function login(Request $request, AuthenticationUtils $authenticationUtils, $type): Response
    {
        // Call the getSettings method of GetSettings class to retrieve the data
        $data = $this->getSettings->getSettings($this->userRepository, $this->settingRepository);

        $user_sigin = new User();
        $form = $this->createForm(LoginFormType::class, $user_sigin);
        $form->handleRequest($request);

        // Check if the user is already logged in and redirect them accordingly
        if ($this->getUser() instanceof \Symfony\Component\Security\Core\User\UserInterface) {
            if ($type === 'admin') {
                if ($this->isGranted('ROLE_ADMIN')) {
                    $session = $request->getSession();
                    $session->set('session_admin', true);
                    return $this->redirectToRoute('admin_page');
                }
                $this->addFlash('error', 'Wrong credentials');
                return $this->redirectToRoute('saml_logout');
            }
            $platformMode = $data['PLATFORM_MODE']['value'];
            if ($platformMode === PlatformMode::DEMO) {
                return $this->redirectToRoute('saml_logout');
            }
            $twoFAplatformStatus = $this->settingRepository->findOneBy(['name' => 'TWO_FACTOR_AUTH_STATUS']);
            if ($twoFAplatformStatus) {
                if ($twoFAplatformStatus->getValue() === twoFAType::NOT_ENFORCED) {
                    if ($this->getUser()->getTwoFA()) {
                        if ($this->getUser()->getTwoFAcode()) {
                            return $this->redirectToRoute('app_verify2FA_app');
                        }
                        return $this->redirectToRoute('app_verify2FA_local');
                    }
                    return $this->redirectToRoute('app_landing');
                }
                if ($twoFAplatformStatus->getValue() === twoFAType::ENFORCED_FOR_LOCAL) {
                    if ($this->getUser()->getTwoFA()) {
                        if ($this->getUser()->getTwoFAcode()) {
                            return $this->redirectToRoute('app_verify2FA_app');
                        }
                        return $this->redirectToRoute('app_verify2FA_local');
                    }
                    return $this->redirectToRoute('app_enable2FA');
                }
                if ($twoFAplatformStatus->getValue() === twoFAType::ENFORCED_FOR_ALL) {
                    if ($this->getUser()->getTwoFA()) {
                        if ($this->getUser()->getTwoFAcode()) {
                            return $this->redirectToRoute('app_verify2FA_app');
                        }
                        return $this->redirectToRoute('app_verify2FA_local');
                    }
                    return $this->redirectToRoute('app_enable2FA');
                }
                return $this->redirectToRoute('app_landing');
            }
            return $this->redirectToRoute('app_landing');
        }

        // Get the login error if there is one
        $error = $authenticationUtils->getLastAuthenticationError();

        // Last username entered by the user (this will be empty if the user clicked the verification link)
        $lastUsername = $authenticationUtils->getLastUsername();

        // Check if there's a UUID parameter in the URL
        $uuid = $request->query->get('uuid');
        if ($uuid) {
            // Try to find the user by UUID excluding admins
            $user = $this->userRepository->findOneByUUIDExcludingAdmin($uuid);
            if ($user instanceof User) {
                // If the user is found, set their email as the last username to pre-fill the email field
                $lastUsername = $user->getUuid();
            }
        }

        // Show an error message if the login attempt fails
        if ($error instanceof AuthenticationException) {
            $this->addFlash('error', $error->getMessage());
        }

        if ($type === "admin") {
            return $this->render('admin/login_admin_landing.html.twig', [
                'last_username' => $lastUsername,
                'error' => $error,
                'data' => $data,
                'form' => $form,
            ]);
        }

        return $this->render('site/login_landing.html.twig', [
            'last_username' => $lastUsername,
            'error' => $error,
            'data' => $data,
            'form' => $form,
        ]);
    }

    #[Route(path: '/logout', name: 'app_logout')]
    public function logout(): void
    {
        throw new LogicException(
            'This method can be blank - it will be intercepted by the logout key on your firewall.'
        );
    }

    #[Route(path: '/enable2FA', name: 'app_enable2FA')]
    public function enable2FA(Request $request): Response
    {
        $data = $this->getSettings->getSettings($this->userRepository, $this->settingRepository);
        $form = $this->createForm(TwoFactorPhoneNumber::class, $this->getUser());
        if ($this->getUser()->getPhoneNumber()) {
            $user = $this->getUser();
            if ($user) {
                $user->setTwoFA(true);
                $this->entityManager->persist($user);
                $this->entityManager->flush();
            } else {
                $this->addFlash('error', 'User not found');
            }
            return $this->redirectToRoute('app_landing');
        }
        if ($form->handleRequest($request)->isSubmitted() && $form->isValid()) {
            $phoneNumber = $form->get('phoneNumber')->getData();
            $user = $this->getUser();
            if ($user) {
                $user->setTwoFA(true);
                $user->setPhoneNumber($phoneNumber);
                $this->entityManager->persist($user);
                $this->entityManager->flush();
            } else {
                $this->addFlash('error', 'User not found');
            }
            return $this->redirectToRoute('app_landing');
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
        if ($user) {
            $user->setTwoFAcode($secret);
            $user->setTwoFA(true);
            $this->entityManager->persist($user);
            $this->entityManager->flush();
        } else {
            $this->addFlash('error', 'User not found');
        }

        $formattedSecret = implode(' ', str_split($secret, 10));

        $provisioningUri = $this->totpService->generateTOTP($secret);

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
        if ($form->handleRequest($request)->isSubmitted() && $form->isValid()) {
            $code = $form->get('code')->getData();
            $user = $this->getUser();
            $secret = $user->getTwoFAcode();
            if ($this->totpService->verifyTOTP($secret, $code)) {
                return $this->redirectToRoute('app_landing');
            }
            $this->addFlash('error', 'Invalid code');
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
        $this->verificationCodeGenerator->generate2FACode($this->getUser());
        if ($form->handleRequest($request)->isSubmitted() && $form->isValid()) {
            ///!!!!! send message !!!!!!
            $formCode = $form->get('code')->getData();
            if ($this->verificationCodeGenerator->validateCode($this->getUser(), $formCode)) {
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
    public function disable2FA(): RedirectResponse
    {
        $user = $this->getUser();
        if ($user) {
            $user->setTwoFA(false);
            $user->setTwoFAcode(null);
            $this->entityManager->persist($user);
            $this->entityManager->flush();
        } else {
            $this->addFlash('error', 'User not found');
        }
        return $this->redirectToRoute('app_landing');
    }
}
