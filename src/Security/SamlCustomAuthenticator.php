<?php

namespace App\Security;

use App\Repository\SamlProviderRepository;
use App\Service\SamlProviderResolverService;
use Exception;
use OneLogin\Saml2\Error;
use OneLogin\Saml2\ValidationError;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Http\Authenticator\AbstractAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;
use Symfony\Component\Security\Http\Util\TargetPathTrait;

class SamlCustomAuthenticator extends AbstractAuthenticator
{
    use TargetPathTrait;

    public function __construct(
        private readonly SamlProviderResolverService $samlService,
        private readonly CustomSamlUserFactory $userFactory,
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly SamlProviderResolverService $samlProviderResolverService,
        private readonly SamlProviderRepository $samlProviderRepository,
    ) {
    }

    public function supports(Request $request): bool
    {
        // Ensure the route is correct and the request method is POST with a valid SAMLResponse
        return $request->request->has('SAMLResponse');
    }

    /**
     * @throws ValidationError
     * @throws Error
     */
    public function authenticate(Request $request): SelfValidatingPassport
    {
        $samlResponse = $request->request->get('SAMLResponse');
        if (!$samlResponse) {
            throw new AuthenticationException('Missing SAMLResponse in the request.');
        }

        $samlResponseData = $this->samlService->decodeSamlResponse($samlResponse);
        $idpEntityId = $samlResponseData['idp_entity_id'];
        $idpCertificate = $samlResponseData['certificate'];
        // Fetch the SamlProvider using the repository
        $fetchedSamlProvider = $this->samlProviderRepository->findOneBy([
            'idpEntityId' => $idpEntityId,
            'idpX509Cert' => $idpCertificate,
        ]);
        // Handle the case where no SamlProvider is found
        if (!$fetchedSamlProvider) {
            throw new AuthenticationException(
                sprintf(
                    'No matching SAML Provider found for IDP Entity ID "%s" and Certificate.',
                    $idpEntityId
                )
            );
        }

        // Cast the value to an integer
        $auth = $this->samlService->authSamlProviderById($fetchedSamlProvider->getId());
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
            throw new AuthenticationException('Failed to process user from SAML response: ' . $e->getMessage(), 0, $e);
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
    ): RedirectResponse {
        if ($targetPath = $this->getTargetPath($request->getSession(), $firewallName)) {
            return new RedirectResponse($targetPath);
        }

        return new RedirectResponse($this->urlGenerator->generate('app_landing'));
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): ?RedirectResponse
    {
        // Redirect to a custom error page or login page
        $errorUrl = $this->urlGenerator->generate('app_landing', [
            'error' => $exception->getMessage(),
        ]);

        return new RedirectResponse($errorUrl);
    }
}
