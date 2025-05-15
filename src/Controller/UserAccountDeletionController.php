<?php

namespace App\Controller;

use App\Entity\User;
use App\Enum\FirewallType;
use App\Enum\UserProvider;
use App\Form\AutoDeletePasswordType;
use App\Repository\UserExternalAuthRepository;
use App\Service\GetSettings;
use App\Service\UserDeletionService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Contracts\Translation\TranslatorInterface;

class UserAccountDeletionController extends AbstractController
{
    public function __construct(
        private readonly UserExternalAuthRepository $userExternalAuthRepository,
        private readonly GetSettings $getSettings,
        private readonly UserDeletionService $userDeletionService,
        private readonly TranslatorInterface $translator
    ) {
    }

    /**
     * @throws \JsonException
     */
    #[\Symfony\Component\Routing\Attribute\Route('/landing/userAccount/deletion/local', name: 'app_user_account_deletion_local')]
    #[IsGranted('ROLE_USER')]
    public function autoDeleteUserLocalRequest(Request $request): Response
    {
        $data = $this->getSettings->getSettings();

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
                $this->translator->trans(
                    'cannotAccessThisPageWithAInvalidProvider',
                    [],
                    'controllers'
                )
            );

            return $this->redirectToRoute('app_landing');
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
                    $this->translator->trans('invalidPassword', [], 'controllers')
                );
            }
        }

        if ($form->isSubmitted() && !$form->isValid()) {
            foreach ($form->getErrors(true) as $error) {
                $this->addFlash('error', $error->getMessage());
            }
        }

        return $this->render('landing/autoDeleteAccount/auto_delete_account.html.twig', [
            'form' => $form->createView(),
            'data' => $data,
            'user' => $currentUser,
            'context' => FirewallType::LANDING->value,
        ]);
    }

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
                $this->translator->trans('cannotAccessThisPageWithAInvalidProvider', [], 'controllers')
            );

            return $this->redirectToRoute('app_landing');
        }

        // Redirect based on external provider
        if ($userExternalAuths[0]->getProvider() === UserProvider::GOOGLE_ACCOUNT->value) {
            $previousLoggedID = $currentUser->getId();
            return $this->redirectToRoute('connect_google', ['previousLoggedID' => $previousLoggedID]);
        }

        if ($userExternalAuths[0]->getProvider() === UserProvider::MICROSOFT_ACCOUNT->value) {
            // TODO: Implement Microsoft account authentication
        }

        if ($userExternalAuths[0]->getProvider() === UserProvider::SAML->value) {
            // TODO: Implement SAML login simulation
        }

        // Default case redirects to the landing page
        return $this->redirectToRoute('app_landing');
    }

    #[Route('/landing/userAccount/deletion/external/check/{previousLoggedID}/{currentLoggedUserID}',
        name: 'app_user_account_deletion_external_check')
    ]
    #[IsGranted('ROLE_USER')]
    public function autoDeleteUserExternalCheck(?int $previousLoggedID, int $currentLoggedUserID): RedirectResponse
    {
        if ($previousLoggedID !== $currentLoggedUserID) {
            $this->addFlash(
                'error',
                $this->translator->trans('invalidAccountSelectForUserDeletion', [], 'controllers')
            );

            return $this->redirectToRoute('app_landing');
        }

        return $this->redirectToRoute('app_landing');
    }
}
