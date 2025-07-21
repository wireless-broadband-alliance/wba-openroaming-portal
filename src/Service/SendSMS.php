<?php

namespace App\Service;

use App\Entity\Event;
use App\Entity\User;
use App\Enum\AnalyticalEventType;
use App\Enum\PlatformMode;
use App\Repository\EventRepository;
use App\Repository\SettingRepository;
use App\Repository\UserRepository;
use DateInterval;
use DateTime;
use DateTimeInterface;
use Doctrine\ORM\NonUniqueResultException;
use Exception;
use RuntimeException;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\RedirectionExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;

class SendSMS
{
    /**
     * SendSMS constructor.
     */
    public function __construct(
        private readonly UserRepository $userRepository,
        private readonly ParameterBagInterface $parameterBag,
        private readonly EventRepository $eventRepository,
        private readonly EventActions $eventActions,
        private readonly SettingRepository $settingRepository,
    ) {
    }

    public function sendSmsNoValidation(User $user, string $message): bool
    {
        $recipient = "+" .
            $user->getPhoneNumber()->getCountryCode() .
            $user->getPhoneNumber()->getNationalNumber();

        $apiUrl = $this->parameterBag->get('app.budget_api_url');

        // Fetch SMS credentials from the database
        $username = $this->settingRepository->findOneBy(['name' => 'SMS_USERNAME'])->getValue();
        $userId = $this->settingRepository->findOneBy(['name' => 'SMS_USER_ID'])->getValue();
        $handle = $this->settingRepository->findOneBy(['name' => 'SMS_HANDLE'])->getValue();
        $from = $this->settingRepository->findOneBy(['name' => 'SMS_FROM'])->getValue();

        // Check if the user can regenerate the SMS code
        $client = HttpClient::create();

        // Adjust the API endpoint and parameters based on the Budget SMS documentation
        $apiUrl .= "?username=$username&userid=$userId&handle=$handle&to=$recipient&from=$from&msg=$message";
        $response = $client->request('GET', $apiUrl);

        // Handle the API response as needed
        $response->getStatusCode();
        $response->getContent();

        return true;
    }
}
