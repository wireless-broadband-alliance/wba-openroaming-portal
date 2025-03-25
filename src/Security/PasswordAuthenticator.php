<?php

namespace App\Security;

use App\Entity\User;
use App\Enum\TwoFAType;
use App\Enum\UserTwoFactorAuthenticationStatus;
use App\Form\LoginFormType;
use App\Repository\SettingRepository;
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

    public function __construct(
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly FormFactoryInterface $formFactory,
        private readonly SettingRepository $settingRepository,
    ) {
    }

    public function authenticate(Request $request): Passport
    {
        $userSigning = new User();
        $form = $this->formFactory->create(LoginFormType::class, $userSigning);
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
        $user = $token->getUser();
        $path = $request->getPathInfo();

        // Check if the request it's from the admin login route
        if ($path === '/dashboard/login') {
            $request->getSession()->set('session_admin', true);
        } else {
            $request->getSession()->set('session_admin', false);
        }

        // Check if the user is already logged in and redirect them accordingly
        if ($user instanceof User) {
            $twoFAPlatformStatus = $this->settingRepository->findOneBy([
                'name' => 'TWO_FACTOR_AUTH_STATUS'
            ])->getValue();

            $verification = $user->isVerified();
            // Check if the user is verified
            if (!$verification) {
                return new RedirectResponse($this->urlGenerator->generate('app_email_code'));
            }

            return $this->handleTwoFactorRedirection(
                $user,
                $twoFAPlatformStatus
            );
        }

        // Handle other users
        if ($targetPath = $this->getTargetPath($request->getSession(), $firewallName)) {
            return new RedirectResponse($targetPath);
        }

        // Default redirection for non-admin users
        return new RedirectResponse($this->urlGenerator->generate('app_landing'));
    }

    protected function getLoginUrl(Request $request): string
    {
        // Determine if the request is for the admin login
        if (str_starts_with($request->getPathInfo(), '/dashboard/admin')) {
            return $this->urlGenerator->generate('app_dashboard_login');
        }

        return $this->urlGenerator->generate('app_login');
    }

    protected function handleTwoFactorRedirection(
        User $user,
        string $twoFAPlatformStatus,
    ): Response {
        // Handle NOT_ENFORCED TwoFA status
        if ($twoFAPlatformStatus === TwoFAType::NOT_ENFORCED->value) {
            return $this->redirectBasedOnTwoFAType($user);
        }

        // Handle ENFORCED_FOR_LOCAL or ENFORCED_FOR_ALL statuses
        if (
            $twoFAPlatformStatus === TwoFAType::ENFORCED_FOR_LOCAL->value ||
            $twoFAPlatformStatus === TwoFAType::ENFORCED_FOR_ALL->value
        ) {
            if (
                $user->getTwoFAType() === UserTwoFactorAuthenticationStatus::DISABLED->value
            ) {
                return new RedirectResponse($this->urlGenerator->generate('app_configure2FA'));
            }

            return $this->redirectBasedOnTwoFAType($user);
        }

        // Fallback default redirection
        return new RedirectResponse($this->urlGenerator->generate('app_landing'));
    }

    protected function redirectBasedOnTwoFAType(User $user): Response
    {
        // Check if the user's 2FA type is SMS or EMAIL and redirect accordingly
        if (
            $user->getTwoFAType() === UserTwoFactorAuthenticationStatus::SMS->value ||
            $user->getTwoFAType() === UserTwoFactorAuthenticationStatus::EMAIL->value
        ) {
            return new RedirectResponse($this->urlGenerator->generate('app_2FA_generate_code'));
        }

        // Check if the user's 2FA type is TOTP and redirect accordingly
        if ($user->getTwoFAType() === UserTwoFactorAuthenticationStatus::TOTP->value) {
            return new RedirectResponse($this->urlGenerator->generate('app_verify2FA_TOTP'));
        }

        // Redirect to app_landing as a fallback
        return new RedirectResponse($this->urlGenerator->generate('app_landing'));
    }
}
