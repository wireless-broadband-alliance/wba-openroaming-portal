<?php

namespace App\Security;

use App\Entity\User;
use App\Enum\AnalyticalEventType;
use App\Enum\FirewallType;
use App\Enum\OperationMode;
use App\Enum\SessionStatus;
use App\Enum\SettingName;
use App\Enum\UserProvider;
use App\Enum\UserTwoFactorAuthenticationStatus;
use App\Repository\SettingRepository;
use App\Repository\UserRepository;
use App\Service\TwoFAService;
use libphonenumber\NumberParseException;
use libphonenumber\PhoneNumberFormat;
use libphonenumber\PhoneNumberUtil;
use PixelOpen\CloudflareTurnstileBundle\Http\CloudflareTurnstileHttpClient;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\Session;
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
use Symfony\Contracts\Translation\TranslatorInterface;

class LandingAuthenticator extends AbstractLoginFormAuthenticator
{
    use TargetPathTrait;

    public function __construct(
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly SettingRepository $settingRepository,
        private readonly CloudflareTurnstileHttpClient $turnstileHttpClient,
        private readonly UserRepository $userRepository,
        private readonly TwoFAService $twoFAService,
        private readonly TranslatorInterface $translator,
    ) {
    }

    /**
     * @throws \JsonException
     */
    public function authenticate(Request $request): Passport
    {
        /** @var array<string, mixed> $formData */
        $formData = $request->request->all();

        /** @var array<string, mixed> $loginData */
        $loginData = (array) ($formData['login'] ?? []);

        $loginMethod = (string) ($loginData['loginMethod'] ?? UserProvider::EMAIL->value);
        $password = (string) ($loginData['password'] ?? '');

        $request->getSession()->set('last_login_method', $loginMethod);
        $request->getSession()->remove(SessionStatus::AUTHENTICATED_VIA_UUID_ONLY->value);
        if ($loginMethod === UserProvider::EMAIL->value) {
            $identifier = $formData['login']['email'] ?? null;

            $userLoader = fn(string $id) => $this->userRepository->findOneBy([
                'email' => $id,
                'deletedAt' => null,
                'isDisabled' => false,
            ]);
        } elseif ($loginMethod === UserProvider::PHONE_NUMBER->value) {
            $phoneUtil = PhoneNumberUtil::getInstance();
            $phoneData = $formData['login']['phoneNumber'] ?? [];
            if (!empty($phoneData['country']) && !empty($phoneData['number'])) {
                try {
                    $phoneNumberObj = $phoneUtil->parse($phoneData['number'], $phoneData['country']);
                    $identifier = $phoneUtil->format($phoneNumberObj, PhoneNumberFormat::E164);
                } catch (NumberParseException) {
                    throw new CustomUserMessageAuthenticationException('Invalid phone number.');
                }
            } else {
                $identifier = null;
            }

            $userLoader = function (string $id) use ($phoneUtil) {
                try {
                    $phoneNumberObj = $phoneUtil->parse($id);
                } catch (NumberParseException) {
                    return null;
                }
                return $this->userRepository->findOneBy([
                    'phoneNumber' => $phoneNumberObj,
                    'deletedAt' => null,
                    'isDisabled' => false,
                ]);
            };
        } else {
            throw new CustomUserMessageAuthenticationException('Invalid login method.');
        }

        if (empty($identifier)) {
            throw new CustomUserMessageAuthenticationException('Missing user identifier.');
        }

        // Turnstile CAPTCHA check
        $turnstileResponse = $request->request->get('cf-turnstile-response');
        $turnstileResponse = is_string($turnstileResponse) ? $turnstileResponse : '';
        $turnstileSetting = $this->settingRepository->findOneBy(['name' => SettingName::TURNSTILE_CHECKER->value]);
        $isTurnstileEnabled = $turnstileSetting && $turnstileSetting->getValue() === OperationMode::ON->value;
        if (
            $isTurnstileEnabled &&
            ($turnstileResponse === '' ||
                $turnstileResponse === '0' || !$this->turnstileHttpClient->verifyResponse(
                    $turnstileResponse
                ))
        ) {
            throw new CustomUserMessageAuthenticationException(
                $this->translator->trans('invalidCAPTCHAValidation', [], 'Security')
            );
        }

        $request->getSession()->set(SecurityRequestAttributes::LAST_USERNAME, $identifier);

        $csrfToken = $request->request->get('_csrf_token');
        $csrfToken = is_string($csrfToken) ? $csrfToken : null;

        $badges = [new CsrfTokenBadge('authenticate', $csrfToken)];
        $cookie = $request->cookies->get('cookie_preferences');
        if ($cookie) {
            $preferences = json_decode($cookie, true, 512, JSON_THROW_ON_ERROR);
            if (!empty($preferences['rememberMe'])) {
                $rememberMeBadge = new RememberMeBadge();
                $rememberMeBadge->enable();
                $badges[] = $rememberMeBadge;
            }
        }

        return new Passport(
            new UserBadge($identifier, $userLoader),
            new PasswordCredentials($password),
            $badges
        );
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?Response
    {
        $user = $token->getUser();

        if (!$user instanceof User) {
            if ($targetPath = $this->getTargetPath($request->getSession(), $firewallName)) {
                return new RedirectResponse($targetPath);
            }

            return new RedirectResponse($this->urlGenerator->generate('app_landing'));
        }

        $session = $request->getSession();
        $context = FirewallType::LANDING->value;

        // Check for TOTP 2FA
        if ($user->getTwoFAtype() === UserTwoFactorAuthenticationStatus::TOTP->value) {
            $session->remove('2fa_verified_' . $context);

            return new RedirectResponse($this->urlGenerator->generate('app_verify2FA_TOTP', [
                'context' => $context,
            ]));
        }

        // Check for SMS or EMAIL 2FA
        if (
            $user->getTwoFAtype() === UserTwoFactorAuthenticationStatus::SMS->value ||
            $user->getTwoFAtype() === UserTwoFactorAuthenticationStatus::EMAIL->value
        ) {
            $session->remove('2fa_verified_' . $context);

            if ($this->twoFAService->canValidationCode($user, AnalyticalEventType::LOGIN_TRADITIONAL_REQUEST->value)) {
                $this->twoFAService->generate2FACode(
                    $user,
                    $request->getClientIp(),
                    $request->headers->get('User-Agent'),
                    AnalyticalEventType::LOGIN_TRADITIONAL_REQUEST->value
                );
            }

            return new RedirectResponse($this->urlGenerator->generate('app_verify2FA_portal', [
                'context' => $context,
            ]));
        }

        // Handle unverified users requiring email confirmation code
        if (!$user->isVerified()) {
            $userVerificationSetting = $this->settingRepository->findOneBy(
                ['name' => SettingName::USER_VERIFICATION->value]
            );
            if ($userVerificationSetting?->getValue() === OperationMode::ON->value) {
                $loginModeSetting = $this->settingRepository->findOneBy(
                    ['name' => SettingName::LOGIN_WITH_UUID_ONLY->value]
                );
                $value = $loginModeSetting?->getValue();

                $eventType = match ($value) {
                    'true' => AnalyticalEventType::LOGIN_WITH_UUID_ONLY_CODE,
                    default => AnalyticalEventType::LOGIN_TRADITIONAL_REQUEST,
                };

                if ($this->twoFAService->canValidationCode($user, $eventType->value)) {
                    $this->twoFAService->generate2FACode(
                        $user,
                        $request->getClientIp(),
                        $request->headers->get('User-Agent'),
                        $eventType->value
                    );

                    if ($session instanceof Session) {
                        $session->getFlashBag()->add(
                            'success',
                            $this->translator->trans(
                                'verificationCodeSent',
                                [],
                                'controllers'
                            )
                        );
                    }

                    return new RedirectResponse($this->urlGenerator->generate('app_login_confirmation'));
                }

                $intervalMinutes = $this->twoFAService->timeLeftToResendCode($user, $eventType->value);

                throw new CustomUserMessageAuthenticationException(
                    $this->translator->trans(
                        'codeAlreadySent',
                        ['%minutes%' => $intervalMinutes],
                        'controllers'
                    )
                );
            }
        }

        // 4. Default landing redirect for verified users without 2FA
        if ($targetPath = $this->getTargetPath($session, $firewallName)) {
            return new RedirectResponse($targetPath);
        }

        return new RedirectResponse($this->urlGenerator->generate('app_landing'));
    }

    protected function getLoginUrl(Request $request): string
    {
        return $this->urlGenerator->generate('app_login');
    }
}
