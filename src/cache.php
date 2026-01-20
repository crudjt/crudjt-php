<?php

namespace CRUDJT;

require_once 'lru_cache.php';

class Cache
{
    private const CACHE_CAPACITY = 40_000;

    private LRUCache $cache;
    private $wFunc;

    public function __construct(callable $wFunc)
    {
        $this->cache = new LRUCache(self::CACHE_CAPACITY);
        $this->wFunc = $wFunc;
    }

    public function get(string $token): ?array
    {
        $cachedtoken = $this->cache->get($token);

        if ($cachedtoken === null) {
            return null;
        }

        $output = [];

        if (isset($cachedtoken['metadata']['ttl'])) {
            $ttl = (int)$cachedtoken['metadata']['ttl'] - time();
            if ($ttl <= 0) {
                $this->cache->del($token);
                return null;
            }

            $output['metadata']['ttl'] = $ttl;
        }

        if (isset($cachedtoken['metadata']['silence_read'])) {
            $silence_read = --$cachedtoken['metadata']['silence_read'];
            $output['metadata']['silence_read'] = $silence_read;

            if ($silence_read <= 0) {
                $this->cache->del($token);
            } else {
                $cachedtoken['metadata']['silence_read'] = $silence_read;
                $this->cache->put($token, $cachedtoken);
            }

            ($this->wFunc)($token);
        }

        $output['data'] = $cachedtoken['data'] ?? null;
        return $output;
    }

    public function insert(string $key, $token, int $ttl, int $silence_read): void
    {
        $hash = ['data' => $token];

        if ($ttl > 0) {
            $hash['metadata']['ttl'] = time() + $ttl;
        }

        if ($silence_read > 0) {
            $hash['metadata']['silence_read'] = $silence_read;
        }

        $this->cache->put($key, $hash);
    }

    public function forceInsert(string $key, array $hash): void
    {
        $this->cache->put($key, $hash);
    }

    public function delete(string $key): void
    {
        $this->cache->del($key);
    }
}
