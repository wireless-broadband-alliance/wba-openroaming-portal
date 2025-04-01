<?php

namespace App\Controller;

use App\Entity\Setting;
use App\Entity\User;
use App\Entity\UserExternalAuth;
use App\Enum\AnalyticalEventType;
use App\Enum\FirewallType;
use App\Enum\PlatformMode;
use App\Enum\UserProvider;
use App\Repository\SettingRepository;
use App\Repository\UserRepository;
use App\Service\EventActions;
use App\Service\GetSettings;
use App\Service\UserStatusChecker;
use DateTime;
use DateTimeInterface;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use KnpU\OAuth2ClientBundle\Client\ClientRegistry;
use League\OAuth2\Client\Provider\Exception\IdentityProviderException;
use RuntimeException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Http\Event\InteractiveLoginEvent;

class MicrosoftController extends AbstractController
{
    public function __construct(
        private readonly ClientRegistry $clientRegistry,
        private readonly EntityManagerInterface $entityManager,
        private readonly UserPasswordHasherInterface $passwordEncoder,
        private readonly TokenStorageInterface $tokenStorage,
        private readonly RequestStack $requestStack,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly EventActions $eventActions,
        private readonly GetSettings $getSettings,
        private readonly UserRepository $userRepository,
        private readonly SettingRepository $settingRepository,
        private readonly UserStatusChecker $userStatusChecker,
    ) {
    }

    #[Route('/connect/microsoft', name: 'connect_microsoft')]
    public function connect(): RedirectResponse
    {
        // Call the getSettings method of GetSettings class to retrieve the data
        $data = $this->getSettings->getSettings($this->userRepository, $this->settingRepository);

        // Check if the user clicked on the 'sms' variable present only on the SMS authentication buttons
        if ($data['PLATFORM_MODE']['value'] === true) {
            $this->addFlash(
                'error',
                'The portal is in Demo mode - it is not possible to use this verification method.'
            );
            return $this->redirectToRoute('app_landing');
        }

        // Retrieve the "microsoft" client
        $client = $this->clientRegistry->getClient('microsoft');

        // Get the authorization URL
        $redirectUrl = $client->getOAuth2Provider()->getAuthorizationUrl();

        // Redirect the user to the authorization URL
        return $this->redirect($redirectUrl);
    }

    /**
     * @throws IdentityProviderException
     * @throws Exception
     */
    #[Route('/connect/microsoft/check', name: 'connect_microsoft_check', methods: ['GET'])]
    public function connectCheck(Request $request): RedirectResponse
    {
        // Retrieve the "microsoft" client
        $client = $this->clientRegistry->getClient('microsoft');

        $code = $request->query->get('code');
        if ($code === null) {
            $this->addFlash('error', 'Authentication process cancelled.');
            return $this->redirectToRoute('app_landing');
        }

        // Exchange the authorization code for an access token
        $accessToken = $client->getOAuth2Provider()->getAccessToken('authorization_code', [
            'code' => $code,
        ]);

        // Retrieve the user ID and email from the resource owner
        $microsoftUserId = $accessToken->getToken();
        $resourceOwner = $client->fetchUserFromToken($accessToken);
        /** @phpstan-ignore-next-line */
        $data = $resourceOwner->toArray();

        // Map the relevant details from the returned $data array
        $email = $data['email'];
        $firstname = $data['given_name'] ?? null;
        $lastname = $data['family_name'] ?? null;

        // Check if the email is valid
        if (!$this->userStatusChecker->isValidEmail($email, UserProvider::MICROSOFT_ACCOUNT->value)) {
            $this->addFlash('error', 'Sorry! Your email domain is not allowed to use this platform');
            return $this->redirectToRoute('app_landing');
        }

        // Find or create the user based on the Google user ID and email
        $user = $this->findOrCreateMicrosoftUser($microsoftUserId, $email, $firstname, $lastname);

        // If the user is null, redirect to the landing page
        if (!$user instanceof \App\Entity\User) {
            return $this->redirectToRoute('app_landing');
        }

        // Check if the user is banned
        if ($user->getBannedAt() instanceof DateTimeInterface) {
            $this->addFlash('error', "Your account has been banned");
            return $this->redirectToRoute('app_landing');
        }

        // Authenticate the user
        $this->authenticateUserMicrosoft($user);

        // Redirect the user to the landing page
        return $this->redirectToRoute('app_landing');
    }

    private function findOrCreateMicrosoftUser(
        string $microsoftUserId,
        string $email,
        ?string $firstname,
        ?string $lastname
    ): ?User {
        // Check if a user with the given Microsoft user ID exists in UserExternalAuth
        $userExternalAuth = $this->entityManager->getRepository(UserExternalAuth::class)->findOneBy([
            'provider' => UserProvider::MICROSOFT_ACCOUNT->value,
            'provider_id' => $microsoftUserId
        ]);

        if ($userExternalAuth !== null) {
            // If a user with the given Microsoft user ID exists, return the associated user
            return $userExternalAuth->getUser();
        }

        // Check if a user with the given email exists
        $userWithEmail = $this->entityManager->getRepository(User::class)->findOneBy(['uuid' => $email]);

        if ($userWithEmail !== null) {
            $existingUserAuth = $this->entityManager->getRepository(UserExternalAuth::class)->findOneBy([
                'user' => $userWithEmail
            ]);

            if ($existingUserAuth === null) {
                $this->addFlash(
                    'error',
                    "Email already in use. Please use the original provider from this account!"
                );
                return null;
            }

            // If a user with the given email exists, and they don't have an external auth entry, return the user
            return $userWithEmail;
        }

        // If no user exists, create a new user and a corresponding UserExternalAuth entry
        $user = new User();
        $user->setIsVerified(true)
            ->setEmail($email)
            ->setFirstName($firstname)
            ->setLastName($lastname)
            ->setCreatedAt(new DateTime())
            ->setUuid($email);

        $userAuth = new UserExternalAuth();
        $userAuth->setUser($user)
            ->setProvider(UserProvider::MICROSOFT_ACCOUNT->value)
            ->setProviderId($microsoftUserId);

        $randomPassword = bin2hex(random_bytes(8));
        $hashedPassword = $this->passwordEncoder->hashPassword($user, $randomPassword);
        $user->setPassword($hashedPassword);

        $this->entityManager->persist($user);
        $this->entityManager->persist($userAuth);
        $this->entityManager->flush();

        $event_metadata = [
            'ip' => $_SERVER['REMOTE_ADDR'],
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown',
            'platform' => PlatformMode::LIVE->value,
            'uuid' => $user->getUuid(),
            'registrationType' => UserProvider::MICROSOFT_ACCOUNT->value,
        ];

        $this->eventActions->saveEvent(
            $user,
            AnalyticalEventType::USER_CREATION->value,
            new DateTime(),
            $event_metadata
        );
        $this->eventActions->saveEvent(
            $user,
            AnalyticalEventType::USER_VERIFICATION->value,
            new DateTime(),
            []
        );

        return $user;
    }

    public function authenticateUserMicrosoft(User $user): void
    {
        // Get the current request from the request stack
        $request = $this->requestStack->getCurrentRequest();

        try {
            // Get the current token and firewall name
            $tokenStorage = $this->tokenStorage;
            $token = $tokenStorage->getToken();
            /** @phpstan-ignore-next-line */
            $firewallName = $token instanceof TokenInterface ? $token->getFirewallName() : FirewallType::LANDING->value;

            // Create a new token with the authenticated user
            $token = new UsernamePasswordToken($user, $firewallName, $user->getRoles());

            // Set the new token in the token storage
            $this->tokenStorage->setToken($token);

            // Dispatch an interactive login event
            $eventDispatcher = $this->eventDispatcher;
            $eventDispatcher->dispatch(new InteractiveLoginEvent($request, $token));

            // Save the changes
            $this->entityManager->flush();
        } catch (AuthenticationException $exception) {
            // Handle authentication failure
            $errorMessage = 'Authentication failed: ' . $exception->getMessage();
            $this->addFlash('error', $errorMessage);
            $this->redirectToRoute('app_landing');
            return;
        }
    }

    /**
     * @throws IdentityProviderException
     * @throws Exception
     */
    public function fetchUserFromMicrosoft(string $code): ?User
    {
        $client = $this->clientRegistry->getClient('microsoft');

        // Exchange the authorization code for an access token
        $accessToken = $client->getOAuth2Provider()->getAccessToken('authorization_code', [
            'code' => $code,
        ]);

        // Fetch user info from Microsoft
        $resourceOwner = $client->fetchUserFromToken($accessToken);
        /** @phpstan-ignore-next-line */
        $data = $resourceOwner->toArray();
        $microsoftUserId = $resourceOwner->getId();

        // Map the relevant details from the returned $data array
        $email = $data['email'];
        $firstname = $data['given_name'] ?? null;
        $lastname = $data['family_name'] ?? null;

        return $this->findOrCreateMicrosoftUser($microsoftUserId, $email, $firstname, $lastname);
    }
}
