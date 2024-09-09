<?php

namespace App\Api\V1\Controller;

use App\Api\V1\BaseResponse;
use App\Entity\User;
use App\Entity\UserExternalAuth;
use App\Service\CaptchaValidator;
use Exception;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\DecodingExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\RedirectionExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;

class GetCurrentUserController extends AbstractController
{
    private TokenStorageInterface $tokenStorage;
    private CaptchaValidator $captchaValidator;

    public function __construct(TokenStorageInterface $tokenStorage, CaptchaValidator $captchaValidator)
    {
        $this->tokenStorage = $tokenStorage;
        $this->captchaValidator = $captchaValidator;
    }

    /**
     * @throws TransportExceptionInterface
     * @throws ServerExceptionInterface
     * @throws RedirectionExceptionInterface
     * @throws DecodingExceptionInterface
     * @throws ClientExceptionInterface
     * @throws Exception
     */
    public function __invoke(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true, 512, JSON_THROW_ON_ERROR);
        if (!isset($data['cf-turnstile-response'])) {
            throw new BadRequestHttpException(
                'CAPTCHA token is missing!'
            );
        }

        if (!$this->captchaValidator->validate($data['cf-turnstile-response'], $request->getClientIp())) {
            throw new BadRequestHttpException(
                'CAPTCHA validation failed!'
            );
        }

        $token = $this->tokenStorage->getToken();

        // Check if the token is present and is of the correct type
        if ($token instanceof TokenInterface && $token->getUser() instanceof User) {
            /** @var User $currentUser */
            $currentUser = $token->getUser();

            // Get user external auth details
            $userExternalAuths = $currentUser->getUserExternalAuths()->map(
                function (UserExternalAuth $userExternalAuth) {
                    return [
                        'provider' => $userExternalAuth->getProvider(),
                        'provider_id' => $userExternalAuth->getProviderId(),
                    ];
                }
            )->toArray();

            // Construct the response content with user details
            $content = [
                'attributes' => [
                    'uuid' => $currentUser->getUuid(),
                    'email' => $currentUser->getEmail(),
                    'roles' => $currentUser->getRoles(),
                    'isVerified' => $currentUser->isVerified(),
                    'phone_number' => $currentUser->getPhoneNumber(),
                    'first_name' => $currentUser->getFirstName(),
                    'last_name' => $currentUser->getLastName(),
                    'user_radius_profiles' => $currentUser->getUserRadiusProfiles(),
                    'user_external_auths' => $userExternalAuths,
                    'verification_code' => $currentUser->getVerificationCode(),
                    'created_at' => $currentUser->getCreatedAt(),
                    'banned_at' => $currentUser->getBannedAt(),
                    'deleted_at' => $currentUser->getDeletedAt(),
                    'forgot_password_request' => $currentUser->isForgotPasswordRequest(),
                ]
            ];

            return (new BaseResponse(Response::HTTP_OK, $content))->toResponse();
        }

        // Handle the case where the user is not authenticated
        return new JsonResponse([
            'error' => 'Unauthorized',
        ], Response::HTTP_UNAUTHORIZED);
    }
}
