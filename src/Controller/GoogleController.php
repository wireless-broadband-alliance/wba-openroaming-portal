<?php

namespace App\Controller;

use App\Entity\Setting;
use App\Entity\User;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use KnpU\OAuth2ClientBundle\Client\ClientRegistry;
use League\OAuth2\Client\Provider\Exception\IdentityProviderException;
use RuntimeException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Http\Event\InteractiveLoginEvent;

class GoogleController extends AbstractController
{
    private ClientRegistry $clientRegistry;
    private EntityManagerInterface $entityManager;
    private UserPasswordHasherInterface $passwordEncoder;
    private TokenStorageInterface $tokenStorage;
    private RequestStack $requestStack;
    private EventDispatcherInterface $eventDispatcher;

    public function __construct(
        ClientRegistry $clientRegistry,
        EntityManagerInterface $entityManager,
        UserPasswordHasherInterface $passwordEncoder,
        TokenStorageInterface $tokenStorage,
        RequestStack $requestStack,
        EventDispatcherInterface $eventDispatcher
    ) {
        $this->clientRegistry = $clientRegistry;
        $this->entityManager = $entityManager;
        $this->passwordEncoder = $passwordEncoder;
        $this->tokenStorage = $tokenStorage;
        $this->requestStack = $requestStack;
        $this->eventDispatcher = $eventDispatcher;
    }

    #[Route('/connect/google', name: 'connect_google')]
    public function connectAction(): RedirectResponse
    {
        // Retrieve the "google" client
        $client = $this->clientRegistry->getClient('google');

        // Get the authorization URL
        $redirectUrl = $client->getOAuth2Provider()->getAuthorizationUrl();

        // Redirect the user to the authorization URL
        return $this->redirect($redirectUrl);
    }


    /**
     * @throws IdentityProviderException
     * @throws Exception
     */
    #[Route('/connect/google/check', name: 'connect_google_check')]
    public function connectCheckAction(Request $request): RedirectResponse
    {
        // Retrieve the "google" client
        $client = $this->clientRegistry->getClient('google');

        // Get the authorization code from the query parameters
        $code = $request->query->get('code');

        // Exchange the authorization code for an access token
        $accessToken = $client->getOAuth2Provider()->getAccessToken('authorization_code', [
            'code' => $code,
        ]);

        // Retrieve the user ID and email from the resource owner
        $googleUserId = $accessToken->getToken();
        $resourceOwner = $client->fetchUserFromToken($accessToken);
        $email = $resourceOwner->getEmail();

        // Check if the email is valid
        if (!$this->isValidEmail($email)) {
            $this->addFlash('error', 'Sorry, you cannot login because you don\'t have a valid domain.');
            return $this->redirectToRoute('app_landing');
        }

        // Find or create the user based on the Google user ID and email
        $user = $this->findOrCreateUser($googleUserId, $email);

        // Authenticate the user
        $this->authenticateUser($user);

        // Redirect the user to the landing page
        return $this->redirectToRoute('app_landing');
    }

    private function isValidEmail(string $email): bool
    {
        // Retrieve the valid domains setting from the database
        $settingRepository = $this->entityManager->getRepository(Setting::class);
        $validDomainsSetting = $settingRepository->findOneBy(['name' => 'VALID_DOMAINS_GOOGLE_LOGIN']);

        // Throw an exception if the setting is not found
        if (!$validDomainsSetting) {
            throw new RuntimeException('VALID_DOMAINS_GOOGLE_LOGIN not found in the database.');
        }

        // Split the valid domains into an array and trim whitespace
        $validDomains = explode(',', $validDomainsSetting->getValue());
        $validDomains = array_map('trim', $validDomains);

        // Extract the domain from the email
        $emailParts = explode('@', $email);
        $domain = end($emailParts);

        // Check if the domain is in the list of valid domains
        return in_array($domain, $validDomains, true);
    }

    /**
     * @throws Exception
     */
    private function findOrCreateUser(string $googleUserId, string $email): User
    {
        // Check if a user with the given Google user ID exists
        $user = $this->entityManager->getRepository(User::class)->findOneBy(['googleId' => $googleUserId]);

        // If a user doesn't exist, check if a user with the given email exists
        if (!$user) {
            $user = $this->entityManager->getRepository(User::class)->findOneBy(['email' => $email]);
        }

        // If a user still doesn't exist, create a new user
        if (!$user) {
            $user = new User();
            $user->setGoogleId($googleUserId);
            $user->setIsVerified(true);
            $user->setEmail($email);
            $user->setUuid(str_replace('@', "-GOOGLE-" . uniqid("", true) . "-", $user->getEmail()));// I'm kinda confused here, idk what should i insert here and the column its not null on the db

            $randomPassword = bin2hex(random_bytes(8));
            $hashedPassword = $this->passwordEncoder->hashPassword($user, $randomPassword);
            $user->setPassword($hashedPassword);

            $this->entityManager->persist($user);
        }

        // Update the last login time and save the changes
        $user->setLastLogin(new DateTime());
        $this->entityManager->flush();

        return $user;
    }

    private function authenticateUser(User $user): void
    {
        // Get the current request from the request stack
        $request = $this->requestStack->getCurrentRequest();

        try {
            // Get the current token and firewall name
            $tokenStorage = $this->tokenStorage;
            $token = $tokenStorage->getToken();
            $firewallName = $token ? $token->getFirewallName() : 'main';

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
}
