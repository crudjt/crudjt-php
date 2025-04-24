<?php

class Cache
{
    private const CACHE_CAPACITY = 40_000;

    private array $cache = [];
    private $wFunc;

    public function __construct(callable $wFunc)
    {
        $this->wFunc = $wFunc;
    }

    public function get($value)
    {
        if (!isset($this->cache[$value])) {
            return null;
        }

        $cachedValue = $this->cache[$value];
        $this->cache[$value] = $cachedValue; // touch for LRU

        $output = [];

        if (isset($cachedValue['metadata']['ttl'])) {
            $ttl = ceil($cachedValue['metadata']['ttl'] - time());
            if ($ttl <= 0) {
                unset($this->cache[$value]);
                return null;
            }

            $output['metadata'] = [
                'ttl' => $ttl,
            ];
        }

        if (isset($cachedValue['metadata']['silence_read'])) {
            $cachedValue['metadata']['silence_read'] -= 1;
            $silence_read = $cachedValue['metadata']['silence_read'];

            $output['metadata'] = $output['metadata'] ?? [];
            $output['metadata']['silence_read'] = $silence_read;

            $this->cache[$value] = $cachedValue;

            if ($silence_read <= 0) {
                unset($this->cache[$value]);
            }

            call_user_func($this->wFunc, $value);
        }

        $output['data'] = $cachedValue['data'];

        return $output;
    }

    public function insert($key, $value, int $ttl, int $silence_read)
    {
        $hash = ['data' => $value];

        if ($ttl > 0) {
            $hash['metadata'] = [
                'ttl' => time() + $ttl,
            ];
        }

        if ($silence_read > 0) {
            $hash['metadata'] = $hash['metadata'] ?? [];
            $hash['metadata']['silence_read'] = $silence_read;
        }

        $this->set($key, $hash);
    }

    public function forceInsert($key, $hash)
    {
        $this->set($key, $hash);
    }

    public function delete($value)
    {
        unset($this->cache[$value]);
    }

    private function set($key, $value)
    {
        if (count($this->cache) >= self::CACHE_CAPACITY) {
            array_shift($this->cache); // remove oldest (approximate LRU)
        }

        $this->cache[$key] = $value;
    }
}
