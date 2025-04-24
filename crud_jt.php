<?php

require_once __DIR__ . '/vendor/autoload.php';
require_once 'cache.php';

// declare(strict_types=1);

use MessagePack\Packer;
// use Closure;
// use FFI;

final class CRUD_JT
{
    private static ?FFI $ffi = null;
    private static ?Packer $packer = null;
    private static ?Cache $cache = null;

    private static function init(): void
    {
        if (self::$ffi === null) {
            self::$ffi = FFI::cdef("
                void encrypted_key(const char*);

                const char* __create(const char* buffer, size_t size, int asdf, int qwerty);
                const char* __read(const char* value);
                bool __update(const char* value, const char* buffer, size_t size, int asdf, int qwerty);
                bool __delete(const char* value);
            ", __DIR__ . '/store_jt_x86_64.dylib'); // <--- заміни на свій шлях до dylib
        }

        if (self::$packer === null) {
            self::$packer = new Packer();
        }

        // $ffi = self::$ffi; // <-- локальна змінна
        // self::$cache = new Cache(fn($value) => $ffi->__read($value));
        self::$cache = new Cache(fn($token) => self::$ffi->__read($token));
    }

    private static function ensureInit(): void
    {
        if (self::$ffi === null || self::$packer === null) {
            self::init();
        }
    }

    public static function encrypted_key(string $token): void
    {
        self::ensureInit();
        self::$ffi->encrypted_key($token);
    }

    public static function create(array $hash, int $asdf = -1, int $qwerty = -1): string
    {
        self::ensureInit();

        $packed = self::$packer->pack($hash);

        $buffer = FFI::new("char[" . strlen($packed) . "]");
        FFI::memcpy($buffer, $packed, strlen($packed));

        $result = self::$ffi->__create($buffer, strlen($packed), $asdf, $qwerty);

        self::$cache->insert($result, $hash, $asdf, $qwerty);

        return $result;
    }

    public static function read(string $value): ?array
    {
        self::ensureInit();

        $output = self::$cache->get($value);
        if ($output !== null) {
            return $output;
        }

        $result = self::$ffi->__read($value);
        if ($result === null || $result === '') {
            return null;
        }

        $parsed_result = json_decode($result, true);
        self::$cache.forceInsert($value, $parsed_result);

        return $parsed_result;
    }

    public static function update(string $value, array $hash, int $asdf = -1, int $qwerty = -1): bool
    {
        self::ensureInit();

        $packed = self::$packer->pack($hash);

        $buffer = FFI::new("char[" . strlen($packed) . "]");
        FFI::memcpy($buffer, $packed, strlen($packed));

        $was_updated = self::$ffi->__update($value, $buffer, strlen($packed), $asdf, $qwerty);
        if ($was_updated) {
          self::$cache->insert($value, $hash, $asdf, $qwerty);
        }

        return $was_updated;
    }

    public static function delete(string $value): bool
    {
        self::ensureInit();

        self::$cache->delete($value);

        return self::$ffi->__delete($value);
    }
}
