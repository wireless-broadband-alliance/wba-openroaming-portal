<?php

namespace App\Security;

use App\Entity\User;
use App\Enum\FirewallType;
use App\Enum\OperationMode;
use App\Enum\TwoFAType;
use App\Enum\UserTwoFactorAuthenticationStatus;
use App\Repository\SettingRepository;
use App\Repository\UserRepository;
use PixelOpen\CloudflareTurnstileBundle\Http\CloudflareTurnstileHttpClient;
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

class LandingAuthenticator extends AbstractLoginFormAuthenticator
{
    use TargetPathTrait;

    public function __construct(
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly FormFactoryInterface $formFactory,
        private readonly SettingRepository $settingRepository,
        private readonly CloudflareTurnstileHttpClient $turnstileHttpClient,
        private readonly UserRepository $userRepository,
    ) {
    }

    public function authenticate(Request $request): Passport
    {
        // Retrieve the data from the login form
        $uuid = $request->request->get('uuid');
        $password = $request->request->get('password');
        $turnstileResponse = $request->request->get('cf-turnstile-response'); // Captcha

        if ($uuid) {
            $user = $this->userRepository->findOneBy([
                'uuid' => $uuid,
                'bannedAt' => null,
                'deletedAt' => null,
                'isDisabled' => false,
            ]);
            if (!$user) {
                // Validate if the user account exists
                throw new CustomUserMessageAuthenticationException('Invalid Credentials.');
            }
            if ($user->isDisabled() === true) {
                // Validate if the user account is disabled
                throw new CustomUserMessageAuthenticationException('This account is currently disabled.');
            }
            if ($user->getBannedAt() !== null) {
                // Validate if the user account is banned
                throw new CustomUserMessageAuthenticationException('This account is currently banned.');
            }
        }

        // Check if Turnstile validation is enabled in the database
        $turnstileSetting = $this->settingRepository->findOneBy(['name' => 'TURNSTILE_CHECKER']);
        $isTurnstileEnabled = $turnstileSetting && $turnstileSetting->getValue() === OperationMode::ON->value;

        if ($isTurnstileEnabled) {
            // Validate the Turnstile CAPTCHA
            if (empty($turnstileResponse) || !$this->turnstileHttpClient->verifyResponse($turnstileResponse)) {
                throw new CustomUserMessageAuthenticationException('Invalid CAPTCHA validation.');
            }
        }

        // Add LAST_USERNAME to the session (optional)
        $request->getSession()->set(SecurityRequestAttributes::LAST_USERNAME, $uuid);

        // Create a Passport with user, credentials, and CSRF token
        return new Passport(
            new UserBadge($uuid), // Identifier for fetching the user
            new PasswordCredentials($password), // Check password
            [
                new CsrfTokenBadge('authenticate', $request->request->get('_csrf_token')), // CSRF protection
            ]
        );
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?Response
    {
        $user = $token->getUser();

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
                return new RedirectResponse($this->urlGenerator->generate('app_configure2FA', [
                    'context' => FirewallType::LANDING->value,
                ]));
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
            return new RedirectResponse($this->urlGenerator->generate('app_2FA_generate_code', [
                'context' => FirewallType::LANDING->value,
            ]));
        }

        // Check if the user's 2FA type is TOTP and redirect accordingly
        if ($user->getTwoFAType() === UserTwoFactorAuthenticationStatus::TOTP->value) {
            return new RedirectResponse($this->urlGenerator->generate('app_verify2FA_TOTP', [
                'context' => FirewallType::LANDING->value,
            ]));
        }

        // Redirect to app_landing as a fallback
        return new RedirectResponse($this->urlGenerator->generate('app_landing'));
    }
}
