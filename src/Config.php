<?php

namespace CRUD_JT;

use FFI;

require_once 'validation.php';
require_once __DIR__ . '/Errors.php';

final class Config
{
    private static ?FFI $ffi = null;
    private static array $settings = [];
    private static bool $wasStarted = false;

    private static function init(): void
    {
        if (self::$ffi === null) {
            self::$ffi = FFI::cdef("
                const char* start_store_jt(const char* encrypted_key, const char* store_jt_path);
            ", self::resolveLibraryPath());
        }
    }

    private static function ensureInit(): void
    {
        if (self::$ffi === null) {
            self::init();
        }
    }

    public static function encrypted_key(string $value): self
    {
        Validation::validateEncryptedKey($value);
        self::$settings['encrypted_key'] = $value;
        return new self();
    }

    public static function store_jtPath(string $value): self
    {
        self::$settings['store_jt_path'] = $value;
        return new self();
    }

    public static function wasStarted(): bool
    {
        return self::$wasStarted;
    }

    public static function start(): void
    {
        if (!isset(self::$settings['encrypted_key'])) {
            Errors::raise(
                Validation::ERROR_ENCRYPTED_KEY_NOT_SET,
                Validation::errorMessage(Validation::ERROR_ENCRYPTED_KEY_NOT_SET)
            );
        }

        if (self::$wasStarted) {
            Errors::raise(
                Validation::ERROR_ALREADY_STARTED,
                Validation::errorMessage(Validation::ERROR_ALREADY_STARTED)
            );
        }

        self::ensureInit();

        $encrypted_key = self::$settings['encrypted_key'];
        $store_jtPath = self::$settings['store_jt_path'] ?? null;

        $cString = self::$ffi->start_store_jt($encrypted_key, $store_jtPath);
        $result = json_decode($cString, true);

        if (!is_array($result)) {
            throw new \RuntimeException("Invalid response from start_store_jt: $json");
        }

        if (!($result['ok'] ?? false)) {
            $code = $result['code'];
            $message = $result['error_message'];

            Errors::raise($code, $message);
        }

        self::$wasStarted = true;
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
