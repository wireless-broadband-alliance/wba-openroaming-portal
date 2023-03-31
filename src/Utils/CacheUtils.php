<?php

namespace App\Utils;

use Memcached;
use Symfony\Component\Cache\Adapter\MemcachedAdapter;
use Symfony\Component\Cache\CacheItem;
use Symfony\Component\Cache\Exception\CacheException;

class CacheUtils
{
    private $cache;


    /**
     * @throws CacheException
     */
    public function __construct()
    {
        // Create a new Memcached client
        $client = new Memcached();
        $client->addServer('memcached', 11211);

        // Create a new cache pool using the Memcached client
        $this->cache = new MemcachedAdapter($client);
    }

    public function read(string $key)
    {
        $item = $this->getCacheItem($key);
        return $item->isHit() ? $item->get() : null;
    }

    private function getCacheItem(string $key): CacheItem
    {
        return $this->cache->getItem($key);
    }

    public function write(string $key, $value, int $ttl = 0)
    {
        $item = $this->getCacheItem($key);
        $item->set($value);
        if ($ttl > 0) {
            $item->expiresAfter($ttl);
        }
        $this->cache->save($item);
    }
}
