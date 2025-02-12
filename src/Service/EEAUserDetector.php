<?php

namespace App\Service;

class EEAUserDetector
{
    private array $eeaTimezones = [
        // Timezones for EEA countries (approximation)
        'UTC',   // Ireland, Portugal
        'CET',   // Central European countries
        'CEST',  // Central European countries (summer)
        'EET',   // Eastern European countries
        'EEST',  // Eastern European countries (summer)
    ];

    /**
     * Check if the user is from the EEA based on the server's timezone.
     */
    public function isEEAUser(): bool
    {
        $timezone = date_default_timezone_get();
        return in_array($timezone, $this->eeaTimezones, true);
    }
}
