<?php

namespace App\Security;

use App\Entity\User;
use App\Enum\AnalyticalEventType;
use App\Enum\OperationMode;
use App\Repository\SettingRepository;
use App\Repository\UserRepository;
use App\Service\TwoFAService;
use DateTimeInterface;
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
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\RememberMeBadge;
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
        private readonly TwoFAService $twoFAService,
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
                'deletedAt' => null,
            ]);
            if (!$user) {
                // Validate if the user account exists
                throw new CustomUserMessageAuthenticationException('Invalid Credentials.');
            }
            if ($user->isDisabled() === true) {
                // Validate if the user account is disabled
                throw new CustomUserMessageAuthenticationException('This account is currently disabled.');
            }
            if ($user->getBannedAt() instanceof DateTimeInterface) {
                // Validate if the user account exists
                throw new CustomUserMessageAuthenticationException('This account is currently banned.');
            }
        }

        // Check if Turnstile validation is enabled in the database
        $turnstileSetting = $this->settingRepository->findOneBy(['name' => 'TURNSTILE_CHECKER']);
        $isTurnstileEnabled = $turnstileSetting && $turnstileSetting->getValue() === OperationMode::ON->value;

        // Validate the Turnstile CAPTCHA
        if (
            $isTurnstileEnabled &&
            (empty($turnstileResponse) || !$this->turnstileHttpClient->verifyResponse($turnstileResponse))
        ) {
            throw new CustomUserMessageAuthenticationException('Invalid CAPTCHA validation.');
        }

        // Add LAST_USERNAME to the session (optional)
        $request->getSession()->set(SecurityRequestAttributes::LAST_USERNAME, $uuid);

        // Preferences Checker
        $rememberMe = false;
        $cookie = $request->cookies->get('cookie_preferences');
        if ($cookie) {
            $preferences = json_decode($cookie, true, 512, JSON_THROW_ON_ERROR);
            $rememberMe = isset($preferences['rememberMe']) && $preferences['rememberMe'] === true;
        }

        // Prepare badges
        $badges = [
            new CsrfTokenBadge('authenticate', $request->request->get('_csrf_token')),
        ];

        if ($rememberMe) {
            $rememberMeBadge = new RememberMeBadge();
            $rememberMeBadge->enable();
            $badges[] = $rememberMeBadge;
        }

        // Standard login with password
        return new Passport(
            new UserBadge($uuid), // Identifier for fetching the user
            new PasswordCredentials($password), // Check password
            [
                new CsrfTokenBadge(
                    'authenticate',
                    $request->request->get('_csrf_token')
                ), // CSRF protection
            ]
        );
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?Response
    {
        $user = $token->getUser();

        if (!$user instanceof User) {
            // Default redirection for non-admin or anonymous users
            if ($targetPath = $this->getTargetPath($request->getSession(), $firewallName)) {
                return new RedirectResponse($targetPath);
            }

            return new RedirectResponse($this->urlGenerator->generate('app_landing'));
        }

        if ($user->isVerified()) {
            return new RedirectResponse($this->urlGenerator->generate('app_landing'));
        }

        if ($this->twoFAService->canValidationCode($user, AnalyticalEventType::LOGIN_TRADITIONAL_REQUEST->value)) {
            $this->twoFAService->generate2FACode(
                $user,
                $request->getClientIp(),
                $request->headers->get('User-Agent'),
                AnalyticalEventType::LOGIN_TRADITIONAL_REQUEST->value
            );

            return new RedirectResponse($this->urlGenerator->generate('app_email_code'));
        }

        $intervalMinutes = $this->twoFAService->timeLeftToResendCode(
            $user,
            AnalyticalEventType::LOGIN_TRADITIONAL_REQUEST->value
        );

        throw new CustomUserMessageAuthenticationException(
            "Your code has already been sent to you previously. 
            Wait {$intervalMinutes} minute(s) to request a code again."
        );
    }

    protected function getLoginUrl(Request $request): string
    {
        return $this->urlGenerator->generate('app_login');
    }
}
