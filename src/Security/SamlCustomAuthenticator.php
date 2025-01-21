<?php

namespace App\Security;

use App\Repository\UserExternalAuthRepository;
use App\Service\SamlActiveProviderService;
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
    private SamlActiveProviderService $samlService;
    private UserExternalAuthRepository $userAuthRepo;

    public function __construct(SamlActiveProviderService $samlService, UserExternalAuthRepository $userAuthRepo)
    {
        $this->samlService = $samlService;
        $this->userAuthRepo = $userAuthRepo;
    }

    public function supports(Request $request): ?bool
    {
        return $request->getPathInfo() === '/saml/acs'; // SAML assertion endpoint
    }

    /**
     * @throws ValidationError
     * @throws Error
     */
    public function authenticate(Request $request): SelfValidatingPassport
    {
        $auth = $this->samlService->getActiveSamlProvider();
        $auth->processResponse();

        if ($auth->getErrors()) {
            throw new AuthenticationException(implode(', ', $auth->getErrors()));
        }

        $attributes = $auth->getAttributes();
        $nameId = $auth->getNameId();

        // Find UserExternalAuth
        $externalAuth = $this->userAuthRepo->findOneBy(['provider_id' => $nameId]);

        if (!$externalAuth || !$externalAuth->getUser()) {
            throw new AuthenticationException('User not found for the SAML response.');
        }

        return new SelfValidatingPassport(
            new UserBadge($nameId, function () use ($externalAuth) {
                return $externalAuth->getUser();
            })
        );
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?JsonResponse
    {
        return new JsonResponse(['message' => 'Successfully authenticated.']);
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): ?JsonResponse
    {
        return new JsonResponse(['error' => $exception->getMessage()], 401);
    }
}
