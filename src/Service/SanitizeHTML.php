<?php

namespace App\Service;

use HTMLPurifier_Config;

class SanitizeHTML
{
    public function sanitizeHtml(string $html): string
    {
        $config = HTMLPurifier_Config::createDefault();
        $config->set('Cache.SerializerPath', sys_get_temp_dir());
        return (new \HTMLPurifier($config))->purify($html);
    }
}