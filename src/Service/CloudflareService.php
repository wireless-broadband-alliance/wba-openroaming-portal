<?php

namespace App\Service;

use App\DTO\CloudflareDTO;
use Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\DecodingExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\RedirectionExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

readonly class CloudflareService
{

    public function __construct(
        private HttpClientInterface $httpClient
    ) {}

    /**
     * @throws TransportExceptionInterface
     * @throws ServerExceptionInterface
     * @throws RedirectionExceptionInterface
     * @throws DecodingExceptionInterface
     * @throws ClientExceptionInterface
     */
    public function validate(CloudflareDTO $dto): bool
    {
        if (!$dto->token || !$dto->host) {
            return false;
        }

        if (!$this->tokenHasAccessToHost($dto->token, $dto->host)) {
            return false;
        }

        return true;
    }

    /**
     * @throws TransportExceptionInterface
     * @throws ServerExceptionInterface
     * @throws RedirectionExceptionInterface
     * @throws DecodingExceptionInterface
     * @throws ClientExceptionInterface
     */
    private function tokenHasAccessToHost(string $token, string $host): bool
    {
        $domain = preg_replace('/^www\./', '', $host);

        $response = $this->httpClient->request(
            'GET',
            'https://api.cloudflare.com/client/v4/zones',
            [
                'headers' => [
                    'Authorization' => 'Bearer ' . $token,
                    'Content-Type' => 'application/json',
                ],
                'query' => [
                    'name' => $domain,
                ],
            ]
        );

        $data = $response->toArray(false);

        return isset($data['success']) && $data['success'] === true && count($data['result']) > 0;
    }
}