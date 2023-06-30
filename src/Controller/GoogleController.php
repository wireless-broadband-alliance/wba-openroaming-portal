<?php

namespace App\Controller;

use App\Entity\Setting;
use App\Entity\User;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use KnpU\OAuth2ClientBundle\Client\ClientRegistry;
use Psr\Cache\CacheItemPoolInterface;
use League\OAuth2\Client\Provider\Exception\IdentityProviderException;
use Psr\Cache\InvalidArgumentException;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;
use RuntimeException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Http\Event\InteractiveLoginEvent;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * @method get(string $string)
 * @property $requestStack
 */
class GoogleController extends AbstractController
{
    private ClientRegistry $clientRegistry;
    private EntityManagerInterface $entityManager;
    private CacheItemPoolInterface $cache;
    private UserPasswordHasherInterface $passwordEncoder;
    private TokenStorageInterface $tokenStorage;
    private RequestStack $requestStack;
    private EventDispatcherInterface $eventDispatcher;


    public function __construct(
        ClientRegistry              $clientRegistry,
        EntityManagerInterface      $entityManager,
        CacheItemPoolInterface      $cache,
        UserPasswordHasherInterface $passwordEncoder,
        TokenStorageInterface       $tokenStorage,
        RequestStack                $requestStack,
        EventDispatcherInterface $eventDispatcher

    )
    {
        $this->clientRegistry = $clientRegistry;
        $this->entityManager = $entityManager;
        $this->cache = $cache;
        $this->passwordEncoder = $passwordEncoder;
        $this->tokenStorage = $tokenStorage;
        $this->requestStack = $requestStack;
        $this->eventDispatcher = $eventDispatcher;
    }

    /**
     * @throws Exception
     * @throws InvalidArgumentException
     */
    #[Route('/connect/google', name: 'connect_google')]
    public function connectAction(Request $request): RedirectResponse
    {
        // Get the Google client from the client registry
        $client = $this->clientRegistry->getClient('google');

        // Get the OAuth2 provider from the client
        $oauth2Provider = $client->getOAuth2Provider();

        // Generate a random state parameter and store it in the session
        $state = bin2hex(random_bytes(16));
        $request->getSession()->set('_oauth2state', $state);

        // Generate a cache key based on the state parameter
        $cacheKey = 'google_state_' . $state;

        // Store the state parameter in the cache
        $cacheItem = $this->cache->getItem($cacheKey);
        $cacheItem->set($state);
        $this->cache->save($cacheItem);

        // Get the authorization URL for Google with the state parameter
        $redirectUrl = $oauth2Provider->getAuthorizationUrl(['state' => $state]);

        // Redirect the user to the Google authorization URL
        return $this->redirect($redirectUrl);
    }

    /**
     * @param Request $request
     * @return RedirectResponse
     * @throws ContainerExceptionInterface
     * @throws IdentityProviderException
     * @throws InvalidArgumentException
     * @throws NotFoundExceptionInterface
     * @throws Exception
     */
    /**
     * @param Request $request
     * @return RedirectResponse
     * @throws ContainerExceptionInterface
     * @throws IdentityProviderException
     * @throws InvalidArgumentException
     * @throws NotFoundExceptionInterface
     * @throws Exception
     */
    #[Route('/connect/google/check', name: 'connect_google_check')]
    public function connectCheckAction(Request $request): RedirectResponse
    {
        // Get the Google client from the client registry
        $client = $this->clientRegistry->getClient('google');

        // Get the state parameter from the request
        $state = $request->query->get('state');

        // Generate a cache key based on the state parameter
        $cacheKey = 'google_state_' . $state;

        // Retrieve the cached state from the cache
        $cachedState = $this->cache->getItem($cacheKey)->get();

        // Validate the state parameter against the cached state
        $this->validateState($state, $cachedState);

        // Get the authorization code from the request
        $code = $request->query->get('code');

        // Exchange the authorization code for an access token
        $accessToken = $client->getOAuth2Provider()->getAccessToken('authorization_code', [
            'code' => $code,
        ]);

        // Get the Google user ID from the access token
        $googleUserId = $accessToken->getToken();

        // Retrieve the resource owner (Google user) from the access token
        $resourceOwner = $client->fetchUserFromToken($accessToken);

        // Retrieve the email from the resource owner
        $email = $resourceOwner->getEmail();

        // Check if the email is valid and has the correct domain
        if (!$this->isValidEmail($email)) {
            $this->addFlash('error', 'Invalid email or domain');
            return $this->redirectToRoute('app_landing');
        }

        // Find or create the user based on the Google user ID and email
        $user = $this->findOrCreateUser($googleUserId, $email);

        // Remove the cached state
        $this->cache->deleteItem($cacheKey);

        // Authenticate the user
        $this->authenticateUser($user);

        // Redirect the user to the main index page
        return $this->redirectToRoute('app_landing');
    }

    private function isValidEmail(string $email): bool
    {
        $settingRepository = $this->entityManager->getRepository(Setting::class);
        $validDomainsSetting = $settingRepository->findOneBy(['name' => 'VALID_DOMAINS_GOOGLE_LOGIN']);

        if (!$validDomainsSetting) {
            throw new RuntimeException('Valid domains setting not found in the database.');
        }

        $validDomains = explode(',', $validDomainsSetting->getValue());

        // Trim whitespace from each valid domain
        $validDomains = array_map('trim', $validDomains);

        // Extract the domain from the email
        $domain = substr($email, strrpos($email, '@') + 1);

        // Check if the extracted domain is present in the array of valid domains
        return in_array($domain, $validDomains, true);
    }




    private function validateState(string $state, ?string $cachedState): void
    {
        // Check if the state parameter matches the cached state
        if ($state !== $cachedState) {
            throw new RuntimeException('Potato Invalid state, go check the $state and the $caches variables or go back to the landing page and try again');
        }
    }

    /**
     * @throws Exception
     */
    private function findOrCreateUser(string $googleUserId, string $email): User
    {
        // Find the user entity in the database based on the Google user ID
        $user = $this->entityManager->getRepository(User::class)->findOneBy(['googleId' => $googleUserId]);

        if (!$user) {
            // User doesn't exist based on Google ID, check if user exists based on email
            $user = $this->entityManager->getRepository(User::class)->findOneBy(['email' => $email]);
        }

        if (!$user) {
            // User doesn't exist, create a new user with a Google id
            $user = new User();
            $user->setGoogleId($googleUserId);
            $user->setIsVerified(true);
            $user->setEmail($email);
            $user->setUuid(str_replace('@', "-DEMO-" . uniqid("", true) . "-", $user->getEmail()));
            // Generate a random password
            $randomPassword = bin2hex(random_bytes(8));
            // Save the hashed password
            $hashedPassword = $this->passwordEncoder->hashPassword($user, $randomPassword);
            $user->setPassword($hashedPassword);

            // Generate a random password
            $randomPassword = bin2hex(random_bytes(8));

            // Save the hashed password
            $hashedPassword = $this->passwordEncoder->hashPassword($user, $randomPassword);
            $user->setPassword($hashedPassword);

            // Persist the user entity to the database
            $this->entityManager->persist($user);
        }

        // Update the user's last login time
        $user->setLastLogin(new DateTime());
        $this->entityManager->flush();

        return $user;
    }



    /**
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     */
    private function authenticateUser(User $user): void
    {
        $request = $this->requestStack->getCurrentRequest();

        try {
            // Get the firewall name from the token storage
            $tokenStorage = $this->tokenStorage;
            $token = $tokenStorage->getToken();
            $firewallName = $token ? $token->getFirewallName() : 'main';

            // Create a new UsernamePasswordToken for the user
            $token = new UsernamePasswordToken($user, $firewallName, $user->getRoles());

            // Authenticate the user by setting the token
            $this->tokenStorage->setToken($token);

            // Get the event dispatcher from the property
            $eventDispatcher = $this->eventDispatcher;

            // Dispatch the InteractiveLoginEvent
            $eventDispatcher->dispatch(new InteractiveLoginEvent($request, $token));

            // Update the user's last login time or perform any other post-authentication tasks
            $this->entityManager->flush();
        } catch (AuthenticationException $exception) {
            // Handle any authentication exceptions

            // Redirect the user to the landing page with the error message
            $errorMessage = 'Authentication failed: ' . $exception->getMessage();
            $this->addFlash('error', $errorMessage);
            $this->redirectToRoute('app_landing');
            return;
        }
    }
}
