<?php

namespace CRUDJT;

class Validation
{
    private const U64_MAX = 18446744073709551615;

    public const MAX_HASH_SIZE = 256;

    public const ERROR_ALREADY_STARTED = 0;
    public const ERROR_NOT_STARTED = 1;
    public const ERROR_ENCRYPTED_KEY_NOT_SET = 2;

    private const ERROR_MESSAGES = [
        self::ERROR_ALREADY_STARTED => 'CRUDJT already started',
        self::ERROR_NOT_STARTED => 'CRUDJT has not started',
        self::ERROR_ENCRYPTED_KEY_NOT_SET => 'Encrypted key is blank',
    ];

    public static function errorMessage(int $code): string
    {
        return self::ERROR_MESSAGES[$code] ?? "Unknown error ({$code})";
    }

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

    public static function validateHashBytesize(int $hashBytesize): void
    {
        if ($hashBytesize > self::MAX_HASH_SIZE) {
            throw new \InvalidArgumentException("Hash can not be bigger than " . self::MAX_HASH_SIZE . " bytesize");
        }
    }

    public static function validateEncryptedKey(string $key): bool
    {
        $decoded = base64_decode($key, true); // strict decoding

        if ($decoded === false) {
            throw new \InvalidArgumentException("'encrypted_key' must be a valid Base64 string");
        }

        $length = strlen($decoded);
        if (!in_array($length, [32, 48, 64], true)) {
            throw new \InvalidArgumentException("'encrypted_key' must be exactly 32, 48, or 64 bytes. Got {$length} bytes");
        }

        return true;
    }
}
