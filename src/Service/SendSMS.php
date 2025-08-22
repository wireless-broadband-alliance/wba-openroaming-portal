<?php

namespace App\Service;

use App\Entity\User;
use App\Enum\SMSResponse;
use App\Repository\SettingRepository;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpClient\HttpClient;

class SendSMS
{
    /**
     * SendSMS constructor.
     */
    public function __construct(
        private readonly SettingRepository $settingRepository,
        private readonly ParameterBagInterface $parameterBag,
    ) {
    }

    public function sendSmsNoValidation(User $user, string $message): string
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

        if ($this->verifyMessageLength($message)) {
            return SMSResponse::SMS_INVALID_MESSAGE_LENGTH->value;
        }

        // Adjust the API endpoint and parameters based on the Budget SMS documentation
        $apiUrl .= "?username=$username&userid=$userId&handle=$handle&to=$recipient&from=$from&msg=$message";
        $response = $client->request('GET', $apiUrl);

        // Handle the API response as needed
        $response->getStatusCode();
        $response->getContent();

        return SMSResponse::SMS_SUCCESS->value;
    }

    public function verifyMessageLength(string $message): bool
    {
        return strlen($message) <= 612;
    }

}
