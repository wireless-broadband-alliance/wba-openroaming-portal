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
        private HttpClientInterface $httpClient,
    ) {
    }

    /**
     * @throws TransportExceptionInterface
     */
    public function validate(CloudflareDTO $dto): bool
    {
        if (!$dto->token || !$dto->host) {
            return false;
        }

        $zoneId = $this->discoverZoneId($dto->token, $dto->host);

        if ($zoneId === null) {
            return false;
        }

        return $this->tokenCanEditDns($dto->token, $zoneId);
    }

    /**
     * Walk up the hostname until Cloudflare finds the zone.
     *
     * marcelina.tetrapi.org
     * → tetrapi.org
     * → org (stop)
     */
    private function discoverZoneId(string $token, string $host): ?string
    {
        $host = strtolower(trim($host));
        $host = preg_replace('/^\*\./', '', $host);

        $labels = explode('.', $host);

        while (count($labels) >= 2) {
            $candidate = implode('.', $labels);

            $zoneId = $this->queryZone($token, $candidate);

            if ($zoneId !== null) {
                return $zoneId;
            }

            array_shift($labels); // go one level up
        }

        return null;
    }

    /**
     * @throws TransportExceptionInterface
     * @throws ServerExceptionInterface
     * @throws RedirectionExceptionInterface
     * @throws DecodingExceptionInterface
     * @throws ClientExceptionInterface
     */
    private function queryZone(string $token, string $candidate): ?string
    {
        $response = $this->httpClient->request(
            'GET',
            'https://api.cloudflare.com/client/v4/zones',
            [
                'headers' => [
                    'Authorization' => 'Bearer ' . $token,
                ],
                'query' => [
                    'name' => $candidate,
                    'status' => 'active',
                    'per_page' => 1,
                ],
            ]
        );

        $data = $response->toArray(false);

        if (!($data['success'] ?? false)) {
            return null;
        }

        if (empty($data['result'])) {
            return null;
        }

        return $data['result'][0]['id'] ?? null;
    }

    /**
     * @throws TransportExceptionInterface
     */
    private function tokenCanEditDns(string $token, string $zoneId): bool
    {
        $response = $this->httpClient->request(
            'GET',
            "https://api.cloudflare.com/client/v4/zones/$zoneId/dns_records?per_page=1",
            [
                'headers' => [
                    'Authorization' => 'Bearer ' . $token,
                ],
            ]
        );

        return $response->getStatusCode() === 200;
    }
}
