<?php

namespace App\Service;

use Prometheus\Storage\Adapter;
use Prometheus\Storage\InMemory;

/**
 * Factory service for creating Prometheus storage adapter.
 */
class PrometheusStorageService
{
    /**
     * Constructor.
     */
    public function __construct()
    {
        // No parameters needed
    }
    
    /**
     * Get the in-memory storage adapter.
     */
    public function getAdapter(): Adapter
    {
        return new InMemory();
    }
} 