<?php

namespace App\Api\V1\Controller;

use App\Api\V1\BaseResponse;
use App\Entity\User;
use App\Repository\UserRepository;
use App\Service\CaptchaValidator;
use App\Service\UserStatusChecker;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Exception\JsonException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\RedirectionExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface;

class TwoFAController extends AbstractController
{

    public function __construct(
        private readonly CaptchaValidator $captchaValidator,
        private readonly UserRepository $userRepository,
        private readonly UserPasswordHasherInterface $passwordHarsher,
        private readonly UserStatusChecker $userStatusChecker,
    ) {
    }

    /**
     * @throws RedirectionExceptionInterface
     * @throws ClientExceptionInterface
     * @throws ServerExceptionInterface
     * @throws \JsonException
     */
    #[Route('/api/v1/twoFA/request', name: 'api_twoFA_request', methods: ['POST'])]
    public function twoFARequest(Request $request): JsonResponse
    {
        try {
            $data = json_decode($request->getContent(), true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return new BaseResponse(400, null, 'Invalid JSON format')->toResponse(); # Bad Request Response
        }

        if (!isset($data['turnstile_token'])) {
            return new BaseResponse(400, null, 'CAPTCHA validation failed')->toResponse(); # Bad Request Response
        }

        if (!$this->captchaValidator->validate($data['turnstile_token'], $request->getClientIp())) {
            return new BaseResponse(400, null, 'CAPTCHA validation failed')->toResponse(); # Bad Request Response
        }

        $errors = [];
        // Check for missing fields and add them to the array errors
        if (empty($data['uuid'])) {
            $errors[] = 'uuid';
        }
        if (empty($data['password'])) {
            $errors[] = 'password';
        }
        if ($errors !== []) {
            return new BaseResponse(
                400,
                ['missing_fields' => $errors],
                'Invalid data: Missing required fields.'
            )->toResponse();
        }

        // Check if user exists are valid
        $user = $this->userRepository->findOneBy(['uuid' => $data['uuid']]);

        if (!$user instanceof User) {
            return new BaseResponse(400, null, 'Invalid credentials')->toResponse();
            // Bad Request Response
        }

        if (!$this->passwordHarsher->isPasswordValid($user, $data['password'])) {
            return new BaseResponse(401, null, 'Invalid credentials')->toResponse(); # Unauthorized Request Response
        }

        $statusCheckerResponse = $this->userStatusChecker->checkUserStatus($user);
        if ($statusCheckerResponse instanceof BaseResponse) {
            return $statusCheckerResponse->toResponse();
        }
    }
}
