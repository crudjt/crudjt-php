<?php

class Validation
{
    private const U64_MAX = 18446744073709551615;

    public static function validateInsertion(array $hash, ?int $ttl, ?int $silence_read): void
    {
        if (!is_array($hash)) {
            throw new InvalidArgumentException("Must be an array");
        }

        if ($ttl !== -1 && ($ttl < 1 || $ttl > self::U64_MAX)) {
            throw new InvalidArgumentException("ttl should be greater than 0 and less than 2^64");
        }

        if ($silence_read !== -1 && ($silence_read < 1 || $silence_read > self::U64_MAX)) {
            throw new InvalidArgumentException("silence_read should be greater than 0 and less than 2^64");
        }
    }

    public static function validateToken($token): void
    {
        if (!is_string($token)) {
            throw new InvalidArgumentException("token must be a string");
        }

        if (strlen($token) < 1) {
            throw new InvalidArgumentException("token can't be blank");
        }
    }
}
