<?php

namespace App\Service;

use Exception;
use GeoIp2\Database\Reader;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

class EEAUserDetector
{
    public function __construct(
        private readonly RequestStack $requestStack
    ) {
    }

    /**
     * Checks if the user is from the European Economic Area (EEA).
     *
     * This function retrieves the user's IP address and checks their geographical location
     * using the MaxMind GeoLite2 database. It returns true if the user is located within
     * a country in the EEA, and false otherwise.
     *
     */
    public function isEEAUser(): bool
    {
        $ip = $this->getUserIp();
        if (!$ip) {
            return false; // Fallback if no IP is found
        }

        // Get location data from the retrieved IP
        $locationData = $this->getLocationFromIp($ip);

        // Ensure we got valid location data
        if ($locationData === null) {
            return false;
        }

        // Check if the country is in the EU
        return $locationData['isInEU'];
    }

    /**
     * Retrieve the user's IP address using the Symfony Request object.
     */
    private function getUserIp(): ?string
    {
        $request = $this->requestStack->getCurrentRequest();
        if (!$request instanceof Request) {
            return null; // Fallback if no Request is available
        }

        // Use the Request object's getClientIp method
        return $request->getClientIp();
    }

    /**
     * Check the geographical information based on the provided IP address.
     *
     * This function uses the MaxMind GeoLite2 database to fetch location details, such as whether the IP address
     * belongs to a country in the European Union and the country's ISO Alpha-2 code.
     *
     */
    private function getLocationFromIp(string $ip): ?array
    {
        try {
            $databasePath = __DIR__ . '/../../docs/GeoLiteDB/GeoLite2-City.mmdb';

            $reader = new Reader($databasePath);
            $record = $reader->city($ip);

            return [
                'isInEU' => $record->country->isInEuropeanUnion, // Use MaxMind's EU flag directly
                'countryCode' => $record->country->isoCode,      // ISO Alpha-2 code for debug or audit purposes
            ];
        } catch (Exception) {
            return null;
        }
    }
}
