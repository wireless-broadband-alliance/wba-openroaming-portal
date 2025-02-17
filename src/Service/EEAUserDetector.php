<?php

namespace App\Service;

use App\Enum\GeoLocation\GeoLocationErrorCodes;
use App\Enum\GeoLocation\IsEEAHandler;
use Exception;
use GeoIp2\Database\Reader;
use MaxMind\Db\Reader\InvalidDatabaseException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

class EEAUserDetector
{
    public function __construct(
        private readonly RequestStack $requestStack,
    ) {
    }


    /**
     * Determines if the current user is located within the European Economic Area (EEA).
     *
     * This method retrieves the user's IP address and uses GeoLite2 location data
     * to determine the user's geographical region and whether it falls within the EEA.
     * It handles multiple error scenarios including missing IP, database errors, and
     * unhandled location states.
     *
     */
    public function isEEAUser(): int
    {
        $ip = $this->getUserIp();

        // Handle missing IP
        if (!$ip) {
            return IsEEAHandler::MISSING_IP->value;
        }

        // Get location data from the retrieved IP
        $locationData = $this->getLocationFromIp('93.184.216.34');

        // Handle errors returned from getLocationFromIp
        if ($locationData === GeoLocationErrorCodes::MISSING_FILE->value) {
            return IsEEAHandler::LOCATION_ERROR->value;
        }

        if ($locationData === GeoLocationErrorCodes::INVALID_DB->value) {
            return IsEEAHandler::LOCATION_ERROR->value;
        }

        if ($locationData === GeoLocationErrorCodes::GENERIC_ERROR->value) {
            return IsEEAHandler::LOCATION_ERROR->value;
        }

        // Handle success cases
        if ($locationData === GeoLocationErrorCodes::IN_EU->value) {
            return IsEEAHandler::IN_EEA->value;
        }

        if ($locationData === GeoLocationErrorCodes::NOT_IN_EU->value) {
            return IsEEAHandler::NOT_IN_EEA->value;
        }

        // Fallback in case an unhandled state occurs
        return IsEEAHandler::LOCATION_ERROR->value;
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
    private function getLocationFromIp(string $ip): string
    {
        try {
            $databasePath = __DIR__ . '/../../docs/GeoLiteDB/GeoLite2-City.mmdb';

            // Check if the database file exists
            if (!file_exists($databasePath)) {
                return GeoLocationErrorCodes::MISSING_FILE->value;
            }

            $reader = new Reader($databasePath);
            $record = $reader->city($ip);

            return $record->country->isInEuropeanUnion
                ? GeoLocationErrorCodes::IN_EU->value
                : GeoLocationErrorCodes::NOT_IN_EU->value;
        } catch (InvalidDatabaseException) {
            return GeoLocationErrorCodes::INVALID_DB->value;
        } catch (Exception) {
            return GeoLocationErrorCodes::GENERIC_ERROR->value;
        }
    }
}
