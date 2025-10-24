<?php

namespace App\Utils;

use Memcached;
use Psr\Cache\InvalidArgumentException;
use Symfony\Component\Cache\Adapter\MemcachedAdapter;
use Symfony\Component\Cache\CacheItem;
use Symfony\Component\Cache\Exception\CacheException;

class CacheUtils
{
    /**
     * @var MemcachedAdapter
     */
    private MemcachedAdapter $cache;

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

    /**
     * @param string $key
     * @return mixed|null
     * @throws InvalidArgumentException
     */
    public function read(string $key): mixed
    {
        $item = $this->getCacheItem($key);
        return $item->isHit() ? $item->get() : null;
    }

    /**
     * @throws InvalidArgumentException
     */
    private function getCacheItem(string $key): CacheItem
    {
        return $this->cache->getItem($key);
    }

    /**
     * @param string $key
     * @param mixed $value
     * @param int $ttl
     * @return bool True if the item was successfully saved
     * @throws InvalidArgumentException
     */
    public function write(string $key, mixed $value, int $ttl = 0): bool
    {
        $item = $this->getCacheItem($key);
        $item->set($value);
        if ($ttl > 0) {
            $item->expiresAfter($ttl);
        }
        return $this->cache->save($item);
    }
}
