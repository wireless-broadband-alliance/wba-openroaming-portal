<?php

namespace App\Controller;

use App\Entity\User;
use App\Entity\UserExternalAuth;
use App\Enum\AnalyticalEventType;
use App\Enum\FirewallType;
use App\Enum\PlatformMode;
use App\Enum\SettingName;
use App\Enum\UserProvider;
use App\Repository\SettingRepository;
use App\Repository\UserExternalAuthRepository;
use App\Repository\UserRepository;
use App\Service\EventActions;
use App\Service\GetSettings;
use App\Service\UserStatusChecker;
use DateTime;
use DateTimeInterface;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use KnpU\OAuth2ClientBundle\Client\ClientRegistry;
use League\OAuth2\Client\Provider\Exception\IdentityProviderException;
use Random\RandomException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Symfony\Component\Security\Http\Event\InteractiveLoginEvent;
use Symfony\Contracts\Translation\TranslatorInterface;

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
        private readonly UserStatusChecker $userStatusChecker,
        private readonly UserExternalAuthRepository $userExternalAuthRepository,
        private readonly TranslatorInterface $translator,
        private readonly CsrfTokenManagerInterface $csrfTokenManager,
        private readonly SettingRepository $settingRepository,
    ) {
    }

    /**
     * @throws Exception
     */
    #[Route('{type}/connect/microsoft', name: 'connect_microsoft', defaults: ['type' => FirewallType::LANDING->value])]
    public function connect(Request $request, string $type): RedirectResponse
    {
        // Call the getSettings method of GetSettings class to retrieve the data
        /** @var array<string, array{value: string, description: string}> $data */
        $data = $this->getSettings->getSettings();

        // Check if the user clicked on the 'sms' variable present only on the SMS authentication buttons
        if ($data[SettingName::PLATFORM_MODE->value]['value'] === PlatformMode::DEMO->value) {
            $this->addFlash(
                'error',
                $this->translator->trans(
                    'portalInDemoMode',
                    [],
                    'controllers'
                )
            );
            return $this->redirectToRoute('app_landing');
        }

        if ($data[SettingName::AUTH_METHOD_MICROSOFT_LOGIN_ENABLED->value]['value'] === "false") {
            $this->addFlash(
                'error',
                $this->translator->trans(
                    'authenticationMethodNotEnabled',
                    [],
                    'controllers'
                )
            );
            return $this->redirectToRoute('app_landing');
        }

        $previousLoggedID = $request->get('previousLoggedID');

        // Retrieve the "microsoft" client
        if ($type === FirewallType::DASHBOARD->value) {
            $client = $this->clientRegistry->getClient('microsoft_dashboard');
        } else {
            $client = $this->clientRegistry->getClient('microsoft_landing');
        }

        $callbackRoute = match ($type) {
            'dashboard' => 'dashboard_connect_microsoft_check',
            default => 'connect_microsoft_check',
        };

        // Define the minimal required scopes
        $state = [
            'previousLoggedID' => $previousLoggedID,
        ];

        // Get the authorization URL with scopes
        return $client->redirect(
            ['openid', 'profile', 'email', 'offline_access', 'User.Read'],
            ['state' => json_encode($state, JSON_THROW_ON_ERROR)]
        );
    }

    /**
     * @throws Exception
     * @throws GuzzleException
     */
    #[Route('/connect/microsoft/check', name: 'connect_microsoft_check', methods: ['GET'])]
    #[Route('/dashboard/connect/microsoft/check', name: 'dashboard_connect_microsoft_check', methods: ['GET'])]
    public function connectCheck(Request $request): RedirectResponse
    {
        // Retrieve the "microsoft" client
        $routeName = $request->attributes->get('_route');

        $client = match ($routeName) {
            'dashboard_connect_microsoft_check' => $this->clientRegistry->getClient('microsoft_dashboard'),
            default => $this->clientRegistry->getClient('microsoft_landing'),
        };

        $code = $request->query->get('code');
        if ($code === null) {
            $this->addFlash(
                'error',
                $this->translator->trans(
                    'authenticationProcessCancelled',
                    [],
                    'controllers'
                )
            );
            return $this->redirectToRoute('app_landing');
        }

        // Retrieve the `state` parameter and decode it
        $state = $request->query->get('state');
        $stateParams = $state !== null ? json_decode(
            $state,
            true,
            512,
            JSON_THROW_ON_ERROR
        ) : [];
        $previousLoggedID = $stateParams['previousLoggedID'] ?? null;

        // Exchange the authorization code for an access token
        $accessToken = $client->getOAuth2Provider()->getAccessToken('authorization_code', [
            'code' => $code,
        ]);
        $httpClient = new Client();
        $response = $httpClient->get(
            'https://graph.microsoft.com/v1.0/me',
            [
                'headers' => [
                    'Authorization' => 'Bearer ' . $accessToken->getToken(),
                ],
            ]
        );
        $data = json_decode($response->getBody()->getContents(), true, 512, JSON_THROW_ON_ERROR);
        $microsoftUserId = $data['id'] ?? null;
        $email = $data['mail'] ?? $data['userPrincipalName'] ?? null;
        $firstname = $data['givenName'] ?? null;
        $lastname = $data['surname'] ?? null;

        // Check if the email is valid
        if (!$this->userStatusChecker->isValidEmail($email, UserProvider::MICROSOFT_ACCOUNT->value)) {
            $this->addFlash(
                'error',
                $this->translator->trans(
                    'emailDomainNotAllowed',
                    [],
                    'controllers'
                )
            );
            return $this->redirectToRoute('app_landing');
        }

        // Find or create the user based on the Microsoft user ID and email
        $user = $this->findOrCreateMicrosoftUser($microsoftUserId, $email, $firstname, $lastname);

        // If the user is null, redirect to the landing page
        if (!$user instanceof User) {
            return $this->redirectToRoute('app_landing');
        }

        // Check if the user is banned
        if ($user->getBannedAt() instanceof DateTimeInterface) {
            $this->addFlash(
                'error',
                $this->translator->trans(
                    'accountBanned',
                    [],
                    'controllers'
                )
            );
            return $this->redirectToRoute('app_landing');
        }

        // Check if the previousLoggedID exist to trigger the user Account deletion
        $csrfToken = $this->csrfTokenManager->getToken('user_deletion_check_token')->getValue();
        if ($previousLoggedID) {
            return $this->redirectToRoute('app_user_account_deletion_external_check', [
                'previousLoggedID' => $previousLoggedID,
                'currentLoggedUserID' => $user->getId(),
                '_csrf_token' => $csrfToken
            ]);
        }

        // Authenticate the user
        $this->authenticateUserMicrosoft($user);


        if ($routeName === 'dashboard_connect_microsoft_check') {
            return $this->redirectToRoute('admin_page');
        }
        return $this->redirectToRoute('app_landing');
    }

    /**
     * @throws RandomException
     */
    private function findOrCreateMicrosoftUser(
        string $microsoftUserId,
        string $email,
        ?string $firstname,
        ?string $lastname
    ): ?User {
        // Check if a user with the given email exists
        $userMicrosoft = $this->userRepository->findOneBy(['uuid' => $email]);

        if ($userMicrosoft !== null) {
            $existingUserAuth = $this->userExternalAuthRepository->findOneBy([
                'user' => $userMicrosoft
            ]);

            if (
                $existingUserAuth !== null &&
                $existingUserAuth->getProvider() === UserProvider::MICROSOFT_ACCOUNT->value
            ) {
                return $userMicrosoft;
            }

            $this->addFlash(
                'error',
                $this->translator->trans(
                    'emailIsAlreadyInUse',
                    [],
                    'controllers'
                )
            );

            return null;
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

            // Defines the Event to the table
            $eventMetadata = [
                'platform' => $this->settingRepository->findOneBy(
                    ['name' => SettingName::PLATFORM_MODE->value]
                )->getValue(),
                'ip' => $_SERVER['REMOTE_ADDR'],
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown',
                'uuid' => $user->getUuid(),
            ];
            $this->eventActions->saveEvent(
                $user,
                AnalyticalEventType::MICROSOFT_LOGIN_REQUEST->value,
                new DateTime(),
                $eventMetadata
            );

            // Save the changes
            $this->entityManager->flush();
        } catch (AuthenticationException $exception) {
            // Handle authentication failure
            $errorMessage = $this->translator->trans('authenticationFailed', [], 'controllers')
                . $exception->getMessage();
            $this->addFlash('error', $errorMessage);
            $this->redirectToRoute('app_landing');
            return;
        }
    }

    /**
     * @throws IdentityProviderException
     * @throws Exception|GuzzleException
     */
    public function fetchUserFromMicrosoft(string $code): ?User
    {
        $client = $this->clientRegistry->getClient('microsoft');

        // Exchange the authorization code for an access token
        $accessToken = $client->getOAuth2Provider()->getAccessToken('authorization_code', [
            'code' => $code,
        ]);

        // Fetch user info from Microsoft
        /** @phpstan-ignore-next-line */
        $resourceOwner = $client->fetchUserFromToken($accessToken);
        /** @phpstan-ignore-next-line */
        $data = $resourceOwner->toArray();
        $microsoftUserId = $resourceOwner->getId();

        // Map the relevant details from the returned $data array
        $email = $data['emails']['preferred'] ?? $data['emails']['account'] ?? null;
        $firstname = $data['first_name'] ?? null;
        $lastname = $data['last_name'] ?? null;

        return $this->findOrCreateMicrosoftUser($microsoftUserId, $email, $firstname, $lastname);
    }
}
