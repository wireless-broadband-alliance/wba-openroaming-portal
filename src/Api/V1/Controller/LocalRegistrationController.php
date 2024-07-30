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

class LocalRegistrationController extends AbstractController
{
    private UserRepository $userRepository;
    private EntityManagerInterface $entityManager;
    private UserPasswordHasherInterface $passwordHasher;
    private EventActions $eventActions;

    public function __construct(
        UserRepository $userRepository,
        EntityManagerInterface $entityManager,
        UserPasswordHasherInterface $passwordHasher,
        EventActions $eventActions,
    ) {
        $this->userRepository = $userRepository;
        $this->entityManager = $entityManager;
        $this->passwordHasher = $passwordHasher;
        $this->eventActions = $eventActions;
    }

    /**
     * @throws Exception
     */
    #[Route('/api/v1/auth/local/register/', name: 'api_auth_local_register', methods: ['POST'])]
    public function localRegister(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true, 512, JSON_THROW_ON_ERROR);

        if (!isset($data['uuid'], $data['password'], $data['email'])) {
            return new JsonResponse(['error' => 'Invalid data'], 422);
        }

        if ($data['uuid'] !== $data['email']) {
            return new JsonResponse([
                'error' => 'Invalid data. 
            Make sure to type both with the same content!'
            ], 422);
        }

        if ($this->userRepository->findOneBy(['email' => $data['uuid']])) {
            return new JsonResponse(['error' => 'This User already exists'], 403);
        }

        $user = new User();
        $user->setUuid($data['uuid']);
        $user->setEmail($data['email']);
        $user->setPassword($this->passwordHasher->hashPassword($user, $data['password']));
        $user->setIsVerified($data['isVerified'] ?? false);
        $user->setFirstName($data['first_name'] ?? null);
        $user->setLastName($data['last_name'] ?? null);
        $user->setCreatedAt(new DateTime($data['createdAt']));

        $userExternalAuth = new UserExternalAuth();
        $userExternalAuth->setUser($user);
        $userExternalAuth->setProvider(UserProvider::PORTAL_ACCOUNT);
        $userExternalAuth->setProviderId(UserProvider::EMAIL);


        $this->entityManager->persist($user);
        $this->entityManager->persist($userExternalAuth);
        $this->entityManager->flush();

        // Defines the Event to the table
        $eventMetaData = [
            'uuid' => $user->getUuid(),
            'Provider' => UserProvider::PORTAL_ACCOUNT,
            'registrationType' => UserProvider::EMAIL,
        ];
        $this->eventActions->saveEvent(
            $user,
            AnalyticalEventType::USER_CREATION,
            new DateTime(),
            $eventMetaData
        );

        return new JsonResponse(['message' => 'Local User Account Registered Successfully'], 200);
    }

    /**
     * @throws Exception
     */
    #[Route('/api/v1/auth/sms/register/', name: 'api_auth_sms_register', methods: ['POST'])]
    public function smsRegister(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true, 512, JSON_THROW_ON_ERROR);

        if (!isset($data['uuid'], $data['password'], $data['phoneNumber'])) {
            return new JsonResponse(['error' => 'Invalid data. Make sure to set all the inputs!'], 422);
        }

        if ($data['uuid'] !== $data['phoneNumber']) {
            return new JsonResponse([
                'error' => 'Invalid data. 
            Make sure to type both with the same content!'
            ], 422);
        }

        if ($this->userRepository->findOneBy(['phoneNumber' => $data['uuid']])) {
            return new JsonResponse(['error' => 'This User already exists'], 403);
        }

        $user = new User();
        $user->setUuid($data['uuid']);
        $user->setPhoneNumber($data['phoneNumber']);
        $user->setPassword($this->passwordHasher->hashPassword($user, $data['password']));
        $user->setIsVerified($data['isVerified'] ?? false);
        $user->setFirstName($data['first_name'] ?? null);
        $user->setLastName($data['last_name'] ?? null);
        $user->setCreatedAt(new DateTime($data['createdAt']));

        $userExternalAuth = new UserExternalAuth();
        $userExternalAuth->setUser($user);
        $userExternalAuth->setProvider(UserProvider::PORTAL_ACCOUNT);
        $userExternalAuth->setProviderId(UserProvider::PHONE_NUMBER);


        $this->entityManager->persist($user);
        $this->entityManager->persist($userExternalAuth);
        $this->entityManager->flush();

        // Defines the Event to the table
        $eventMetaData = [
            'uuid' => $user->getUuid(),
            'Provider' => UserProvider::PORTAL_ACCOUNT,
            'registrationType' => UserProvider::PHONE_NUMBER,
        ];
        $this->eventActions->saveEvent(
            $user,
            AnalyticalEventType::USER_CREATION,
            new DateTime(),
            $eventMetaData
        );

        return new JsonResponse(['message' => 'SMS User Account Registered Successfully'], 200);
    }
}
