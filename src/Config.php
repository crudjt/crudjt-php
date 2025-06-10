<?php

namespace CRUD_JT;

use FFI;

final class Config
{

    private static ?FFI $ffi = null;
    private static $settings = [];


    private static function init(): void
    {
        if (self::$ffi === null) {
            self::$ffi = FFI::cdef("
                void __encrypted_key(const char*);
                void __store_jt_path(const char*);
            ", self::resolveLibraryPath()); // <--- заміни на свій шлях до dylib
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
        self::$settings['encrypted_key'] = $value;
        return new self();
    }

    public static function store_jt_path(string $value): self
    {
        self::$settings['store_jt_path'] = $value;
        return new self();
    }

    public static function start(): void
    {
        self::ensureInit();

        if (isset(self::$settings['store_jt_path'])) {
            self::$ffi->__store_jt_path(self::$settings['store_jt_path']);
        }

        self::$ffi->__encrypted_key(self::$settings['encrypted_key']);
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
