<?php

namespace App\Security;

use App\Service\SamlActiveProviderService;
use Exception;
use OneLogin\Saml2\Error;
use OneLogin\Saml2\ValidationError;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Http\Authenticator\AbstractAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;

class SamlCustomAuthenticator extends AbstractAuthenticator
{
    public function __construct(
        private readonly SamlActiveProviderService $samlService,
        private readonly CustomSamlUserFactory $userFactory
    ) {
    }

    public function supports(Request $request): bool
    {
        // This authenticator only supports requests to '/saml/acs'
        return $request->getPathInfo() === '/saml/acs';
    }

    /**
     * @throws ValidationError
     * @throws Error
     */
    public function authenticate(Request $request): SelfValidatingPassport
    {
        // Get the SAML Response from the request
        $samlResponse = $request->request->get('SAMLResponse');

        if (!$samlResponse) {
            throw new AuthenticationException('Missing SAMLResponse in the request.');
        }

        $auth = $this->samlService->getActiveSamlProvider();
        $auth->processResponse();
        if ($auth->getErrors()) {
            throw new AuthenticationException(implode(', ', $auth->getErrors()));
        }

        // Retrieve NameID and attributes from response
        $nameId = $auth->getNameId();
        if (!$nameId) {
            throw new AuthenticationException('Missing NameID in SAML response.');
        }
        $attributes = $auth->getAttributes();

        try {
            // Factory will create or retrieve the user based on SAML attributes
            $user = $this->userFactory->createUser($nameId, $attributes);
        } catch (Exception $e) {
            throw new AuthenticationException('Failed to process user from SAML response: '.$e->getMessage(), 0, $e);
        }

        // Return a SelfValidatingPassport including the user
        return new SelfValidatingPassport(
            new UserBadge($nameId, function () use ($user) {
                return $user; // Returning the user created or found by the factory
            })
        );
    }

    public function onAuthenticationSuccess(
        Request $request,
        TokenInterface $token,
        string $firewallName
    ): ?JsonResponse {
        return new JsonResponse(['message' => 'Authentication successful!']);
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): ?JsonResponse
    {
        return new JsonResponse(['error' => $exception->getMessage()], 401);
    }
}
