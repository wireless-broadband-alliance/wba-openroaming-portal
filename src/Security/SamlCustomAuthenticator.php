<?php

namespace App\Security;

use App\Service\SamlActiveProviderService;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Http\Authenticator\AbstractAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;

class SamlCustomAuthenticator extends AbstractAuthenticator
{
    private LoggerInterface $logger;
    private SamlActiveProviderService $samlActiveProviderService;
    private RouterInterface $router;

    public function __construct(
        LoggerInterface $logger,
        SamlActiveProviderService $samlActiveProviderService,
        RouterInterface $router
    ) {
        $this->logger = $logger;
        $this->samlActiveProviderService = $samlActiveProviderService;
        $this->router = $router;
    }

    public function supports(Request $request): ?bool
    {
        // Check if the request contains a SAML response
        return $request->isMethod('POST') && $request->get('SAMLResponse') !== null;
    }

    public function authenticate(Request $request): SelfValidatingPassport
    {
        // Extract SAML response
        $samlResponse = $request->get('SAMLResponse');

        if (!$samlResponse) {
            throw new AuthenticationException('SAMLResponse not found in the request.');
        }

        // Find the current active provider
        $activeProvider = $this->samlActiveProviderService->getActiveSamlProvider();

        if (!$activeProvider) {
            throw new AuthenticationException('No active SAML provider found.');
        }

        // Perform SAML validation and extract user information
        // (This is where you'd integrate your SAML library to validate the response)
        $userEmail = $this->validateSamlAndGetEmail($samlResponse, $activeProvider);

        if (!$userEmail) {
            throw new AuthenticationException('Invalid SAML response');
        }

        // Create a user passport to proceed with the Symfony security process
        return new SelfValidatingPassport(
            new UserBadge($userEmail, function (string $userIdentifier) use ($userProvider) {
                // Load user from your system's database or create a new one dynamically
                return $userProvider->loadUserByIdentifier($userIdentifier);
            })
        );
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?Response
    {
        $this->logger->info("Authentication success for SAML user");

        return new RedirectResponse($this->router->generate('app_landing'));
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): ?Response
    {
        $this->logger->error("Authentication failure: " . $exception->getMessage());

        // Optional: Redirect or return error response
        return new Response('Authentication Failed: ' . $exception->getMessage(), Response::HTTP_UNAUTHORIZED);
    }

    private function validateSamlAndGetEmail(string $samlResponse, $activeProvider): ?string
    {
        // Implement your SAML response validation logic
        // Use a library like OneLogin SAML to decode and validate the SAMLResponse
        // Example: parse SAML data and retrieve user email with active provider details
        $email = 'user@example.com'; // Replace with parsed email from SAML response based on $activeProvider
        return $email;
    }
}
