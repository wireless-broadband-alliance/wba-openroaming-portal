<?php

namespace App\Controller;

use App\Entity\User;
use App\Enum\PlatformMode;
use App\Enum\twoFAType;
use App\Form\LoginFormType;
use App\Form\TwoFAcode;
use App\Repository\SettingRepository;
use App\Repository\UserRepository;
use App\Service\GetSettings;
use App\Service\TOTPService;
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
        EntityManagerInterface $entityManager
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
                    if ($this->getUser()->getTwoFAcode()) {
                        return $this->redirectToRoute('app_verify2FA');
                    }
                    return $this->redirectToRoute('app_landing');
                }

                return $this->redirectToRoute('app_verify2FA');
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
    public function enable2FA(): RedirectResponse
    {
        $user = $this->getUser();
        $secret = $this->totpService->generateSecret();
        if ($user) {
            $user->setTwoFAcode($secret);
            $this->entityManager->persist($user);
            $this->entityManager->flush();
        } else {
            $this->addFlash('error', 'User not found');
        }

        $provisioningUri = $this->totpService->generateTOTP($secret);

        return $this->redirectToRoute('app_generateQRCode');
    }

    #[Route(path: '/generateQRCode', name: 'app_generateQRCode')]
    public function generateQRCode(): Response
    {
        $secret = $this->getUser()->getTwoFAcode();
        $provisioningUri = $this->totpService->generateTOTP($secret);

        $qrCode = new QrCode($provisioningUri);
        $writer = new PngWriter();
        $qrCodeImage = $writer->write($qrCode);

        return new Response(
            $qrCodeImage->getString(),
            Response::HTTP_OK,
            ['Content-Type' => $qrCodeImage->getMimeType()]
        );
    }

    #[Route(path: '/verify2FA', name: 'app_verify2FA')]
    public function verify2FA(Request $request): Response
    {
        $data = $this->getSettings->getSettings($this->userRepository, $this->settingRepository);
        $form = $this->createForm(TwoFAcode::class);
        if ($form->handleRequest($request)->isSubmitted() && $form->isValid()) {
            $data = json_decode($request->getContent(), true);
            $user = $this->getUser();
            $secret = $user->getTwoFAcode();
            $code = $data['code'];
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
/*
    public function login(Request $request, TOTPService $totpService): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        // 1. Verificar credenciais (usuário e senha)
        $user = $this->getDoctrine()
            ->getRepository(User::class)
            ->findOneBy(['email' => $data['email']]);

        if (!$user || !password_verify($data['password'], $user->getPassword())) {
            return $this->json(['error' => 'Invalid credentials'], 401);
        }

        // 2. Verificar o código TOTP
        $code = $data['totp_code'] ?? null;
        if (!$user->getTotpSecret() || !$totpService->verifyTOTP($user->getTotpSecret(), $code)) {
            return $this->json(['error' => 'Invalid 2FA code'], 401);
        }

        // 3. Retornar um token JWT ou continuar a sessão
        return $this->json(['message' => 'Login successful']);
    }
*/
}
