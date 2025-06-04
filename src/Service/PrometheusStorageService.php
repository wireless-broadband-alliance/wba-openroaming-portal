<?php

namespace App\Service;

use Prometheus\Storage\Adapter;
use Prometheus\Storage\InMemory;

/**
 * Factory service for creating Prometheus storage adapter.
 */
class PrometheusStorageService
{
    public function getAdapter(): Adapter
    {
        return new InMemory();
    }
}
