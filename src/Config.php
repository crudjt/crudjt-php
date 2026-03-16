<?php
// This binding was generated automatically to ensure consistency across languages
// Generated using ChatGPT (GPT-5) from the canonical Ruby SDK
// API is stable and production-ready

namespace CRUDJT;

use FFI;

require_once 'validation.php';
require_once __DIR__ . '/Errors.php';

use Token\TokenServiceClient;
use Grpc\ChannelCredentials;

use Token\CreateTokenRequest;

final class Config
{
    private static ?FFI $ffi = null;
    private static bool $wasStarted = false;


    private static array $settings = [
        'grpc_host' => '127.0.0.1',
        'grpc_port' => 50051,
        'master' => false,
    ];

    private static ?TokenServiceClient $stub = null;

    public static function stub(): TokenServiceClient
    {
        if (!self::$stub) {
            throw new RuntimeException('gRPC client not initialized');
        }

        return self::$stub;
    }

    private static function init(): void
    {
        if (self::$ffi === null) {
            self::$ffi = FFI::cdef("
                const char* start_store_jt(const char* secret_key, const char* store_jt_path);
            ", self::resolveLibraryPath());
        }
    }

    private static function ensureInit(): void
    {
        if (self::$ffi === null) {
            self::init();
        }
    }

    public static function secret_key(string $value): self
    {
        Validation::validateSecretKey($value);
        self::$settings['secret_key'] = $value;
        return new self();
    }

    public static function wasStarted(): bool
    {
        return self::$wasStarted;
    }

    public static function master(): bool
    {
        return self::$settings['master'];
    }

    public static function startMaster(array $options = []): void
    {
        if (!isset($options['secret_key'])) {
            Errors::raise(
                Validation::ERROR_SECRET_KEY_NOT_SET,
                Validation::errorMessage(Validation::ERROR_SECRET_KEY_NOT_SET)
            );
        }

        if (self::$wasStarted) {
            Errors::raise(
                Validation::ERROR_ALREADY_STARTED,
                Validation::errorMessage(Validation::ERROR_ALREADY_STARTED)
            );
        }

        self::ensureInit();

        self::$settings['store_jt_path'] = $options['store_jt_path'] ?? null;

        $cString = self::$ffi->start_store_jt($options['secret_key'], self::$settings['store_jt_path']);
        $result = json_decode($cString, true);

        if (!is_array($result)) {
            throw new \RuntimeException("Invalid response from start_store_jt: $json");
        }

        if (!($result['ok'] ?? false)) {
            $code = $result['code'];
            $message = $result['error_message'];

            Errors::raise($code, $message);
        }

        self::$settings['master'] = true;
        self::$wasStarted = true;
    }

    public static function connectToMaster(array $options = []): void
    {
        if (self::$wasStarted) {
            Errors::raise(
                Validation::ERROR_ALREADY_STARTED,
                Validation::errorMessage(Validation::ERROR_ALREADY_STARTED)
            );
        }

        self::$settings['grpc_host'] = $options['grpc_host']
            ?? self::$settings['grpc_host'];

        self::$settings['grpc_port'] = $options['grpc_port']
            ?? self::$settings['grpc_port'];

        $address = sprintf(
            '%s:%d',
            self::$settings['grpc_host'],
            self::$settings['grpc_port']
        );

        self::$stub = new TokenServiceClient(
            $address,
            [
                'credentials' => ChannelCredentials::createInsecure(),
            ]
        );

        self::$settings['master'] = false;
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
