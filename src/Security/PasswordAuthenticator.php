<?php

namespace App\Security;

use App\Entity\User;
use App\Form\LoginFormType;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;
use Symfony\Component\Security\Http\Authenticator\AbstractLoginFormAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\CsrfTokenBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Credentials\PasswordCredentials;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\SecurityRequestAttributes;
use Symfony\Component\Security\Http\Util\TargetPathTrait;

class PasswordAuthenticator extends AbstractLoginFormAuthenticator
{
    use TargetPathTrait;

    public const LOGIN_ROUTE = 'app_login';

    public function __construct(
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly FormFactoryInterface $formFactory
    ) {
    }

    public function authenticate(Request $request): Passport
    {
        $userSignin = new User();
        $form = $this->formFactory->create(LoginFormType::class, $userSignin);
        $form->handleRequest($request);

        if (!$form->isSubmitted() || !$form->isValid()) {
            throw new CustomUserMessageAuthenticationException('Invalid login data.');
        }

        $uuid = $request->request->get('uuid', '');

        $request->getSession()->set(SecurityRequestAttributes::LAST_USERNAME, $uuid);

        return new Passport(
            new UserBadge($uuid),
            new PasswordCredentials($request->request->get('password', '')),
            [
                new CsrfTokenBadge('authenticate', $request->request->get('_csrf_token')),
            ]
        );
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?Response
    {
        // Check if there is a referer URL in the request headers
        $refererUrl = $request->headers->get('referer');

        // If there is a referer URL, redirect the user back to that URL
        if ($refererUrl) {
            return new RedirectResponse($refererUrl);
        }

        // If there is no referer URL, redirect the user to the home page
        return new RedirectResponse($this->urlGenerator->generate('app_landing'));
    }


    protected function getLoginUrl(Request $request): string
    {
        $type = $request->get('type', 'user');

        return $this->urlGenerator->generate(self::LOGIN_ROUTE, ['type' => $type]);
    }
}
