<?php

namespace App\Security;

use App\Entity\User;
use App\Enum\AnalyticalEventType;
use App\Enum\FirewallType;
use App\Enum\OperationMode;
use App\Enum\UserProvider;
use App\Enum\UserTwoFactorAuthenticationStatus;
use App\Repository\SettingRepository;
use App\Repository\UserRepository;
use App\Service\TwoFAService;
use DateTimeInterface;
use libphonenumber\NumberParseException;
use libphonenumber\PhoneNumberFormat;
use libphonenumber\PhoneNumberUtil;
use PixelOpen\CloudflareTurnstileBundle\Http\CloudflareTurnstileHttpClient;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;
use Symfony\Component\Security\Http\Authenticator\AbstractLoginFormAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\CsrfTokenBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\RememberMeBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Credentials\CustomCredentials;
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
        private readonly FormFactoryInterface $formFactory,
        private readonly SettingRepository $settingRepository,
        private readonly CloudflareTurnstileHttpClient $turnstileHttpClient,
        private readonly UserRepository $userRepository,
        private readonly TwoFAService $twoFAService,
        private readonly TranslatorInterface $translator,
        private readonly RequestStack $requestStack,
    ) {
    }

    /**
     * @throws \JsonException
     */
    public function authenticate(Request $request): Passport
    {
        $formData = $request->request->all();
        $loginMethod = $formData['login']['loginMethod'] ?? UserProvider::EMAIL->value;
        $password = $formData['login']['password'];

        if ($loginMethod === UserProvider::EMAIL->value) {
            $identifier = $formData['login']['email'];

            $userLoader = fn(string $id) => $this->userRepository->findOneBy([
                'email' => $id,
                'deletedAt' => null,
            ]);
        } elseif ($loginMethod === UserProvider::PHONE_NUMBER->value) {
            $phoneUtil = PhoneNumberUtil::getInstance();
            $phoneData = $formData['login']['phoneNumber'] ?? [];
            if (!empty($phoneData['country']) && !empty($phoneData['number'])) {
                try {
                    $phoneNumberObj = $phoneUtil->parse($phoneData['number'], $phoneData['country']);
                    // Use E164 format string as identifier for UserBadge
                    $identifier = $phoneUtil->format($phoneNumberObj, PhoneNumberFormat::E164);
                } catch (NumberParseException) {
                    throw new CustomUserMessageAuthenticationException('Invalid phone number.');
                }
            } else {
                $identifier = null;
            }

            // The callback will convert the string back to PhoneNumber object for repository
            $userLoader = function (string $id) use ($phoneUtil) {
                try {
                    $phoneNumberObj = $phoneUtil->parse($id); // parse E164 string back to object
                } catch (NumberParseException) {
                    return null;
                }
                return $this->userRepository->findOneBy([
                    'phoneNumber' => $phoneNumberObj,
                    'deletedAt' => null,
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
        $turnstileSetting = $this->settingRepository->findOneBy(['name' => 'TURNSTILE_CHECKER']);
        $isTurnstileEnabled = $turnstileSetting && $turnstileSetting->getValue() === OperationMode::ON->value;
        if (
            $isTurnstileEnabled && (empty($turnstileResponse) || !$this->turnstileHttpClient->verifyResponse(
                $turnstileResponse
            ))
        ) {
            throw new CustomUserMessageAuthenticationException(
                $this->translator->trans('invalidCAPTCHAValidation', [], 'Security')
            );
        }

        $request->getSession()->set(SecurityRequestAttributes::LAST_USERNAME, $identifier);

        // Remember-me badge
        $badges = [new CsrfTokenBadge('authenticate', $request->request->get('_csrf_token'))];
        $cookie = $request->cookies->get('cookie_preferences');
        if ($cookie) {
            $preferences = json_decode($cookie, true, 512, JSON_THROW_ON_ERROR);
            if (!empty($preferences['rememberMe'])) {
                $rememberMeBadge = new RememberMeBadge();
                $rememberMeBadge->enable();
                $badges[] = $rememberMeBadge;
            }
        }

        // Standard login with password
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
            // Default redirection for non-admin or anonymous users
            if ($targetPath = $this->getTargetPath($request->getSession(), $firewallName)) {
                return new RedirectResponse($targetPath);
            }

            return new RedirectResponse($this->urlGenerator->generate('app_landing'));
        }

        if ($user->isVerified()) {
            return new RedirectResponse($this->urlGenerator->generate('app_landing'));
        }

        $loginModeSetting = $this->settingRepository->findOneBy(['name' => 'LOGIN_WITH_UUID_ONLY']);
        $mode = OperationMode::from($loginModeSetting?->getValue() ?? OperationMode::OFF->value);

        $eventType = match ($mode) {
            OperationMode::ON => AnalyticalEventType::LOGIN_WITH_UUID_ONLY_CODE,
            OperationMode::OFF => AnalyticalEventType::LOGIN_TRADITIONAL_REQUEST,
        };

        if (
            $this->settingRepository->findOneBy(['name' => 'USER_VERIFICATION'])->getValue() ===
            OperationMode::ON->value
        ) {
            if ($this->twoFAService->canValidationCode($user, $eventType->value)) {
                $this->twoFAService->generate2FACode(
                    $user,
                    $request->getClientIp(),
                    $request->headers->get('User-Agent'),
                    $eventType->value
                );

                $session = $this->requestStack->getSession();
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

        return new RedirectResponse($this->urlGenerator->generate('app_landing'));
    }


    protected function getLoginUrl(Request $request): string
    {
        return $this->urlGenerator->generate('app_login');
    }
}
