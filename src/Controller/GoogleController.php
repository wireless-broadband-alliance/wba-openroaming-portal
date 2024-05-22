<?php

namespace App\Controller;

use App\Entity\Event;
use App\Entity\Setting;
use App\Entity\User;
use App\Enum\AnalyticalEventType;
use App\Enum\PlatformMode;
use DateTime;
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
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Http\Event\InteractiveLoginEvent;

/**
 *
 */
class GoogleController extends AbstractController
{
    private ClientRegistry $clientRegistry;
    private EntityManagerInterface $entityManager;
    private UserPasswordHasherInterface $passwordEncoder;
    private TokenStorageInterface $tokenStorage;
    private RequestStack $requestStack;
    private EventDispatcherInterface $eventDispatcher;

    /**
     * @param ClientRegistry $clientRegistry
     * @param EntityManagerInterface $entityManager
     * @param UserPasswordHasherInterface $passwordEncoder
     * @param TokenStorageInterface $tokenStorage
     * @param RequestStack $requestStack
     * @param EventDispatcherInterface $eventDispatcher
     */
    public function __construct(
        ClientRegistry              $clientRegistry,
        EntityManagerInterface      $entityManager,
        UserPasswordHasherInterface $passwordEncoder,
        TokenStorageInterface       $tokenStorage,
        RequestStack                $requestStack,
        EventDispatcherInterface    $eventDispatcher,
    ) {
        $this->clientRegistry = $clientRegistry;
        $this->entityManager = $entityManager;
        $this->passwordEncoder = $passwordEncoder;
        $this->tokenStorage = $tokenStorage;
        $this->requestStack = $requestStack;
        $this->eventDispatcher = $eventDispatcher;
    }

    /**
     * @return RedirectResponse
     */
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
    #[Route('/connect/google/check', name: 'connect_google_check', methods: ['GET'])]
    public function connectCheckAction(Request $request): RedirectResponse
    {
        // Retrieve the "google" client
        $client = $this->clientRegistry->getClient('google');

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
        $googleUserId = $accessToken->getToken();
        $resourceOwner = $client->fetchUserFromToken($accessToken);
        /** @phpstan-ignore-next-line */
        $email = $resourceOwner->getEmail();
        /** @phpstan-ignore-next-line */
        $firstname = $resourceOwner->getFirstname();
        /** @phpstan-ignore-next-line */
        $lastname = $resourceOwner->getLastname();

        // Check if the email is valid
        if (!$this->isValidEmail($email)) {
            $this->addFlash('error', 'Sorry! Your email domain is not allowed to use this platform');
            return $this->redirectToRoute('app_landing');
        }

        // Find or create the user based on the Google user ID and email
        $user = $this->findOrCreateGoogleUser($googleUserId, $email, $firstname, $lastname);

        // If the user is null, redirect to the landing page
        if ($user === null) {
            return $this->redirectToRoute('app_landing');
        }

        // Check if the user is banned
        if ($user->getBannedAt()) {
            $this->addFlash('error', "Your account has been banned");
            return $this->redirectToRoute('app_landing');
        }

        // Authenticate the user
        $this->authenticateUser($user);

        // Redirect the user to the landing page
        return $this->redirectToRoute('app_landing');
    }


    /**
     * @param string $email
     * @return bool
     */
    private function isValidEmail(string $email): bool
    {
        // Retrieve the valid domains setting from the database
        $settingRepository = $this->entityManager->getRepository(Setting::class);
        $validDomainsSetting = $settingRepository->findOneBy(['name' => 'VALID_DOMAINS_GOOGLE_LOGIN']);

        // Throw an exception if the setting is not found
        if (!$validDomainsSetting) {
            throw new RuntimeException('VALID_DOMAINS_GOOGLE_LOGIN not found in the database.');
        }

        // If the valid domains setting is empty, allow all domains
        $validDomains = $validDomainsSetting->getValue();
        if (empty($validDomains)) {
            return true;
        }

        // Split the valid domains into an array and trim whitespace
        $validDomains = explode(',', $validDomains);
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
    private function findOrCreateGoogleUser(string $googleUserId, string $email, ?string $firstname, ?string $lastname): ?User
    {
        // Check if a user with the given Google user ID exists
        $existingUser = $this->entityManager->getRepository(User::class)->findOneBy(['googleId' => $googleUserId]);
        if ($existingUser) {
            // If a user with the given Google user ID exists, return the user
            return $existingUser;
        }

        // Check if a user with the given email exists
        $userWithEmail = $this->entityManager->getRepository(User::class)->findOneBy(['uuid' => $email]);

        if ($userWithEmail) {

            if ($userWithEmail->getGoogleId() === null) {
                $this->addFlash('error', "Email already in use. Please use the original provider from this account!");
                return null;
            }

            // Return the correct user to authenticate
            return $userWithEmail;
        }

        // If no user exists, create a new user with a new set of Events
        $user = new User();
        $user->setGoogleId($googleUserId)
            ->setIsVerified(true)
            ->setEmail($email)
            ->setFirstName($firstname)
            ->setLastName($lastname)
            ->setCreatedAt(new DateTime())
            ->setUuid($email);

        $event_create = new Event();
        $event_create->setUser($user);
        $event_create->setEventName(AnalyticalEventType::USER_CREATION);
        $event_create->setEventMetadata([
            'platform' => PlatformMode::Live,
        ]);
        $event_create->setEventDatetime(new DateTime());

        $event_verify = new Event();
        $event_verify->setUser($user);
        $event_verify->setEventName(AnalyticalEventType::USER_VERIFICATION);
        $event_verify->setEventDatetime(new DateTime());

        $randomPassword = bin2hex(random_bytes(8));
        $hashedPassword = $this->passwordEncoder->hashPassword($user, $randomPassword);
        $user->setPassword($hashedPassword);

        $this->entityManager->persist($event_create);
        $this->entityManager->persist($event_verify);
        $this->entityManager->persist($user);
        $this->entityManager->flush();


        return $user;
    }


    /**
     * @param User $user
     * @return void
     */
    private function authenticateUser(User $user): void
    {
        // Get the current request from the request stack
        $request = $this->requestStack->getCurrentRequest();

        try {
            // Get the current token and firewall name
            $tokenStorage = $this->tokenStorage;
            $token = $tokenStorage->getToken();
            /** @phpstan-ignore-next-line */
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
