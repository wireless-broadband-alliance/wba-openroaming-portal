<?php

namespace App\Service;

use DateTime;

class EscapeSpreadSheet
{
    /**
     * Escape a value to prevent spreadsheet injection for the export routes (EXPORT USERS || FREERADIUS)
     * @param mixed $value
     * @return string
     */
    public function escapeSpreadsheetValue(mixed $value): string
    {
        if ($value instanceof DateTime) {
            return $value->format('Y-m-d H:i:s');
        }

        $escapedValue = (string)$value;

        // Remove specific characters
        $charactersToRemove = ['=', '(', ')'];
        return str_replace($charactersToRemove, '', $escapedValue);
    }
}
