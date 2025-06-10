<?php

namespace CRUD_JT;

use FFI;

// require_once __DIR__ . '/vendor/autoload.php';
require_once 'cache.php';
require_once 'validation.php';

final class CRUD_JT
{
    // use Validation;

    private static ?FFI $ffi = null;
    // private static ?Packer $packer = null;
    private static ?Cache $cache = null;
    private static ?Validation $validation = null;

    private static function init(): void
    {
        if (self::$ffi === null) {
            self::$ffi = FFI::cdef("
                const char* __create(const char* buffer, size_t size, int ttl, int silence_read);
                const char* __read(const char* value);
                bool __update(const char* value, const char* buffer, size_t size, int ttl, int silence_read);
                bool __delete(const char* value);
            ", self::resolveLibraryPath()); // <--- заміни на свій шлях до dylib
        }

        // if (self::$packer === null) {
        //     self::$packer = new Packer();
        // }

        // $ffi = self::$ffi; // <-- локальна змінна
        // self::$cache = new Cache(fn($value) => $ffi->__read($value));
        self::$cache = new Cache(fn($token) => self::$ffi->__read($token));
        self::$validation = new Validation();
    }

    private static function ensureInit(): void
    {
        if (self::$ffi === null) {
            self::init();
        }
    }

    // public static function encrypted_key(string $token): void
    // {
    //     self::ensureInit();
    //     self::$ffi->encrypted_key($token);
    // }

    public static function create(array $hash, int $ttl = -1, int $silence_read = -1): string
    {
        self::ensureInit();

        self::$validation->validateInsertion($hash, $ttl, $silence_read);

        $packed = msgpack_pack($hash);
        $len = strlen($packed);

        $buffer = FFI::new("char[" . $len . "]");
        FFI::memcpy($buffer, $packed, $len);

        $result = self::$ffi->__create($buffer, $len, $ttl, $silence_read);

        self::$cache->insert($result, $hash, $ttl, $silence_read);

        return $result;
    }

    public static function read(string $value): ?array
    {
        self::ensureInit();

        self::$validation->validateToken($value);

        $output = self::$cache->get($value);
        if ($output !== null) {
            return $output;
        }

        $result = self::$ffi->__read($value);
        if ($result === null || $result === '') {
            return null;
        }

        $parsed_result = json_decode($result, true);
        self::$cache->forceInsert($value, $parsed_result);

        return $parsed_result;
    }

    public static function update(string $value, array $hash, int $ttl = -1, int $silence_read = -1): bool
    {
        self::ensureInit();

        self::$validation->validateInsertion($hash, $ttl, $silence_read);

        $packed = msgpack_pack($hash);

        $buffer = FFI::new("char[" . strlen($packed) . "]");
        FFI::memcpy($buffer, $packed, strlen($packed));

        $was_updated = self::$ffi->__update($value, $buffer, strlen($packed), $ttl, $silence_read);
        if ($was_updated) {
          self::$cache->insert($value, $hash, $ttl, $silence_read);
        }

        return $was_updated;
    }

    public static function delete(string $value): bool
    {
        self::ensureInit();

        self::$validation->validateToken($value);

        self::$cache->delete($value);

        return self::$ffi->__delete($value);
    }

    private static function resolveLibraryPath(): string
    {
        $os = PHP_OS_FAMILY;
        $arch = php_uname('m');

        $osMap = [
            'Windows' => 'windows',
            'Darwin'  => 'macos',
            'Linux'   => 'linux',
        ];

        $archMap = [
            'x86_64' => 'x86_64',
            'aarch64' => 'arm64',
            'arm64' => 'arm64',
            'AMD64' => 'x86_64'
        ];

        if (!isset($osMap[$os])) {
            throw new \Exception("Unsupported OS: $os");
        }
        if (!isset($archMap[$arch])) {
            throw new \Exception("Unsupported architecture: $arch");
        }

        $osName = $osMap[$os];
        $archName = $archMap[$arch];

        if ($osName === 'windows') {
            $ext = 'dll';
        } elseif ($osName === 'linux') {
            $ext = 'so';
        } elseif ($osName === 'macos') {
            $ext = 'dylib';
        } else {
            throw new \Exception("Unknown OS extension for $osName");
        }

        $path = __DIR__ . "/native/{$osName}/store_jt_{$archName}.{$ext}";

        if (!file_exists($path)) {
            throw new \Exception("FFI library not found at: $path");
        }

        return $path;
    }

}
