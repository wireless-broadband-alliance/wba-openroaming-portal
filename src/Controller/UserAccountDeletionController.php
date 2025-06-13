<?php

namespace App\Controller;

use App\Entity\User;
use App\Enum\AnalyticalEventType;
use App\Enum\FirewallType;
use App\Enum\OperationMode;
use App\Enum\UserProvider;
use App\Form\AutoDeleteCodeType;
use App\Form\AutoDeletePasswordType;
use App\Repository\SettingRepository;
use App\Repository\UserExternalAuthRepository;
use App\Repository\UserRepository;
use App\Service\GetSettings;
use App\Service\TwoFAService;
use App\Service\UserDeletionService;
use DateTime;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

class UserAccountDeletionController extends AbstractController
{
    public function __construct(
        private readonly UserExternalAuthRepository $userExternalAuthRepository,
        private readonly GetSettings $getSettings,
        private readonly UserDeletionService $userDeletionService,
        private readonly UserRepository $userRepository,
        private readonly TwoFAService $twoFAService,
        private readonly SettingRepository $settingRepository,
    ) {
    }

    /**
     * @throws \JsonException
     */
    #[Route('/landing/userAccount/deletion/local', name: 'app_user_account_deletion_local')]
    #[IsGranted('ROLE_USER')]
    public function autoDeleteUserLocalRequest(Request $request): Response
    {
        $data = $this->getSettings->getSettings($this->userRepository, $this->settingRepository);

        /** @var User $currentUser */
        $currentUser = $this->getUser();

        // Check if the local account has a phoneNumber of an email
        if ($currentUser->getPhoneNumber() === null && empty($currentUser->getEmail())) {
            $this->redirectToRoute('app_landing');
        }

        $userExternalAuths = $this->userExternalAuthRepository->findBy(['user' => $currentUser->getId()]);
        if ($currentUser->getUserExternalAuths()[0]->getProvider() !== UserProvider::PORTAL_ACCOUNT->value) {
            $this->addFlash(
                'error',
                'You can not access this page without the valid requirements!'
            );

            return $this->redirectToRoute('app_landing');
        }
        if (
            $data['LOGIN_WITH_UUID_ONLY']['value'] === OperationMode::ON->value &&
            $currentUser->getUserExternalAuths()[0]->getProvider() === UserProvider::PORTAL_ACCOUNT->value
        ) {
            if (
                $this->twoFAService->canValidationCode($currentUser, AnalyticalEventType::USER_AUTO_DELETE_CODE->value)
            ) {
                $this->twoFAService->generate2FACode(
                    $currentUser,
                    $request->getClientIp(),
                    $request->headers->get('User-Agent'),
                    AnalyticalEventType::USER_AUTO_DELETE_CODE->value
                );
                $this->addFlash(
                    'success',
                    'A confirmation code was sent to your email.'
                );
            } else {
                $interval_minutes = $this->twoFAService->timeLeftToResendCode(
                    $currentUser,
                    AnalyticalEventType::USER_AUTO_DELETE_CODE->value
                );

                $this->addFlash(
                    'error',
                    "Your code has already been sent to you previously. 
                    Wait {$interval_minutes} minute(s) to request a code again."
                );
            }

            return $this->redirectToRoute('app_user_account_deletion_local_code');
        }
        $form = $this->createForm(AutoDeletePasswordType::class);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $currentPasswordDB = $currentUser->getPassword();
            if ($currentUser->getUserExternalAuths()[0]->getProvider() === UserProvider::PORTAL_ACCOUNT->value) {
                $typedPassword = $form->get('password')->getData();

                // Compare the typed password with the hashed password from the database
                if (password_verify((string)$typedPassword, $currentPasswordDB)) {
                    $this->userDeletionService->deleteUser($currentUser, $userExternalAuths, $request, $currentUser);

                    return $this->redirectToRoute('app_landing');
                }
                $this->addFlash(
                    'error',
                    'Current password Invalid. Please try again.'
                );
            }
        }

        if ($form->isSubmitted() && !$form->isValid()) {
            foreach ($form->getErrors(true) as $error) {
                $this->addFlash('error', $error->getMessage());
            }
        }

        return $this->render('site/actions/auto_delete_account.html.twig', [
            'form' => $form->createView(),
            'data' => $data,
            'user' => $currentUser,
            'context' => FirewallType::LANDING->value,
        ]);
    }

    #[Route('/landing/userAccount/deletion/local/code', name: 'app_user_account_deletion_local_code')]
    #[IsGranted('ROLE_USER')]
    public function userAccountDeletionLocalConfirm(Request $request): Response
    {
        $data = $this->getSettings->getSettings($this->userRepository, $this->settingRepository);

        /** @var User $currentUser */
        $currentUser = $this->getUser();

        // Check if the local account has a phoneNumber of an email
        if ($currentUser->getPhoneNumber() === null && empty($currentUser->getEmail())) {
            $this->redirectToRoute('app_landing');
        }

        $userExternalAuths = $this->userExternalAuthRepository->findBy(['user' => $currentUser->getId()]);
        if ($currentUser->getUserExternalAuths()[0]->getProvider() !== UserProvider::PORTAL_ACCOUNT->value) {
            $this->addFlash(
                'error',
                'You can not access this page without the valid requirements!'
            );

            return $this->redirectToRoute('app_landing');
        }

        $form = $this->createForm(AutoDeleteCodeType::class);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            if ($currentUser->getUserExternalAuths()[0]->getProvider() === UserProvider::PORTAL_ACCOUNT->value) {
                $typedCode = $form->get('code')->getData();

                // Compare the typed code with the 2fa code from the database
                if ($this->twoFAService->validate2FACode($currentUser, $typedCode)) {
                    $this->userDeletionService->deleteUser($currentUser, $userExternalAuths, $request, $currentUser);

                    return $this->redirectToRoute('app_landing');
                }
                $this->addFlash(
                    'error',
                    'Code Invalid. Please try again.'
                );
            }
        }

        if ($form->isSubmitted() && !$form->isValid()) {
            foreach ($form->getErrors(true) as $error) {
                $this->addFlash('error', $error->getMessage());
            }
        }

        return $this->render('site/actions/auto_delete_account.html.twig', [
            'form' => $form->createView(),
            'data' => $data,
            'user' => $currentUser,
            'context' => FirewallType::LANDING->value,
        ]);
    }

    /**
     * @throws \DateMalformedStringException
     */
    #[Route('/landing/userAccount/deletion/external', name: 'app_user_account_deletion_external')]
    #[IsGranted('ROLE_USER')]
    public function autoDeleteUserExternal(): RedirectResponse
    {
        /** @var User $currentUser */
        $currentUser = $this->getUser();
        if (!$currentUser) {
            // Redirect if the current user cannot be retrieved
            return $this->redirectToRoute('app_landing');
        }

        // Check if the account has either a phone number or email
        if ($currentUser->getPhoneNumber() === null && empty($currentUser->getEmail())) {
            return $this->redirectToRoute('app_landing');
        }

        $userExternalAuths = $currentUser->getUserExternalAuths();
        // Block if the provider is `PORTAL_ACCOUNT`
        if ($userExternalAuths[0]->getProvider() === UserProvider::PORTAL_ACCOUNT->value) {
            $this->addFlash(
                'error',
                'You can not access this page without the valid requirements!'
            );

            return $this->redirectToRoute('app_landing');
        }

        // Redirect based on external provider
        // GOOGLE ACCOUNT
        if ($userExternalAuths[0]->getProvider() === UserProvider::GOOGLE_ACCOUNT->value) {
            $previousLoggedID = $currentUser->getId();

            return $this->redirectToRoute('connect_google', ['previousLoggedID' => $previousLoggedID]);
        }

        // MICROSOFT ACCOUNT
        if ($userExternalAuths[0]->getProvider() === UserProvider::MICROSOFT_ACCOUNT->value) {
            $previousLoggedID = $currentUser->getId();

            return $this->redirectToRoute('connect_microsoft', ['previousLoggedID' => $previousLoggedID]);
        }

        // SAML ACCOUNT
        if ($userExternalAuths[0]->getProvider() === UserProvider::SAML->value) {
            $previousLoggedID = $currentUser->getId();

            $cookie = new Cookie(
                'previousLoggedID',
                $previousLoggedID,
                new DateTime()->modify('+1 minutes'),
                '/',
                null,
                false,
                true,
                false,
                Cookie::SAMESITE_LAX
            );
            $response = $this->redirectToRoute('saml_login');
            $response->headers->setCookie($cookie);

            return $response;
        }

        // Default case redirects to the landing page
        return $this->redirectToRoute('app_landing');
    }

    /**
     * @throws \JsonException
     */
    #[Route(
        '/landing/userAccount/deletion/external/check/{previousLoggedID}/{currentLoggedUserID}',
        name: 'app_user_account_deletion_external_check',
    )]
    #[IsGranted('ROLE_USER')]
    public function autoDeleteUserExternalCheck(
        ?int $previousLoggedID,
        int $currentLoggedUserID,
        Request $request
    ): RedirectResponse {
        $csrfToken = $request->query->get('_csrf_token');

        if (!$this->isCsrfTokenValid('user_deletion_check_token', $csrfToken)) {
            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }

        if ($previousLoggedID !== $currentLoggedUserID) {
            $this->addFlash(
                'error',
                'You did not select the correct account for deletion. 
                Your currently authenticated account is not the same as the selected one.'
            );

            return $this->redirectToRoute('app_landing');
        }

        /** @var User $user */
        $user = $this->userRepository->findOneBy(['id' => $currentLoggedUserID]);
        if ($user) {
            $userExternalAuths = $this->userExternalAuthRepository->findBy(['user' => $user]);
            $this->userDeletionService->deleteUser($user, $userExternalAuths, $request, $user);
        }

        return $this->redirectToRoute('app_landing');
    }
}
