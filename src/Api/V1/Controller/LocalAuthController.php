<?php

namespace App\Api\V1\Controller;

use App\Entity\User;
use App\Entity\UserExternalAuth;
use App\Enum\AnalyticalEventType;
use App\Enum\UserProvider;
use App\Repository\UserRepository;
use App\Service\EventActions;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Annotation\Route;

class LocalAuthController extends AbstractController
{

    /**
     * @throws Exception
     */
    #[Route('/api/v1/auth/local', name: 'api_auth_local', methods: ['POST'])]
    public function authLocal(Request $request): JsonResponse
    {
        return $this->json('Eu sou um rabo');
    }

}