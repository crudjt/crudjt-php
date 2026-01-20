<?php

namespace CRUD_JT;

use FFI;
use CRUD_JT\Errors;

require_once 'cache.php';
require_once 'validation.php';
require_once __DIR__ . '/Errors.php';

use Token\TokenServiceClient;
use Grpc\ChannelCredentials;

use Token\CreateTokenRequest;
use Token\ReadTokenRequest;
use Token\UpdateTokenRequest;
use Token\DeleteTokenRequest;


final class CRUD_JT
{
    private static ?FFI $ffi = null;
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
            ", self::resolveLibraryPath());
        }

        self::$cache = new Cache(fn($token) => self::$ffi->__read($token));
        self::$validation = new Validation();
    }

    private static function ensureInit(): void
    {
        if (self::$ffi === null) {
            self::init();
        }
    }

    public static function original_create(array $hash, int $ttl = -1, int $silence_read = -1): string
    {
        self::ensureInit();

        if (!Config::wasStarted()) {
            throw new \Exception(
                Validation::errorMessage(Validation::ERROR_NOT_STARTED)
            );
        }

        self::$validation->validateInsertion($hash, $ttl, $silence_read);

        $packed = msgpack_pack($hash);
        $len = strlen($packed);

        $buffer = FFI::new("char[" . $len . "]");
        FFI::memcpy($buffer, $packed, $len);
        self::$validation->validateHashBytesize($len);

        $result = self::$ffi->__create($buffer, $len, $ttl, $silence_read);
        if (!$result) {
            throw new InternalError("Something went wrong. Ups");
        }

        self::$cache->insert($result, $hash, $ttl, $silence_read);

        return $result;
    }

    public static function create(array $hash, int $ttl = -1, int $silence_read = -1): string
    {
        self::ensureInit();

        if (Config::master()) {
          return self::original_create($hash, $ttl, $silence_read);
        } else {
          $request = new CreateTokenRequest();
          $request->setPackedData(msgpack_pack($hash));
          $request->setTtl($ttl);
          $request->setSilenceRead($silence_read);

          list($response, $status) = Config::stub()->CreateToken($request)->wait();

          if ($status->code !== \Grpc\STATUS_OK) {
              die($status->details);
          }

          return $response->getToken();
        }
    }

    public static function original_read(string $value): ?array
    {
        if (!Config::wasStarted()) {
            throw new \Exception(
                Validation::errorMessage(Validation::ERROR_NOT_STARTED)
            );
        }

        self::ensureInit();

        self::$validation->validateToken($value);

        $output = self::$cache->get($value);
        if ($output !== null) {
            return $output;
        }

        $str = self::$ffi->__read($value);
        $result = json_decode($str, true);

        if (!isset($result['ok']) || !$result['ok']) {
            Errors::raise($result['code'], $result['error_message'] ?? "Unknown error");
        }

        if (empty($result['data'])) {
            return null;
        }

        $data = json_decode($result['data'], true);
        self::$cache->forceInsert($value, $data);

        return $data;
    }

    public static function read(string $value): ?array
    {
        self::ensureInit();

        if (Config::master()) {
          return self::original_read($value);
        } else {
          $request = new ReadTokenRequest();
          $request->setToken($value);

          list($response, $status) = Config::stub()->ReadToken($request)->wait();

          if ($status->code !== \Grpc\STATUS_OK) {
              die($status->details);
          }

          return msgpack_unpack($response->getPackedData());
        }
    }

    public static function original_update(string $value, array $hash, int $ttl = -1, int $silence_read = -1): bool
    {
        if (!Config::wasStarted()) {
            throw new \Exception(
                Validation::errorMessage(Validation::ERROR_NOT_STARTED)
            );
        }

        self::ensureInit();

        self::$validation->validateInsertion($hash, $ttl, $silence_read);

        $packed = msgpack_pack($hash);
        $len = strlen($packed);

        $buffer = FFI::new("char[" . strlen($packed) . "]");
        FFI::memcpy($buffer, $packed, strlen($packed));
        self::$validation->validateHashBytesize($len);

        $was_updated = self::$ffi->__update($value, $buffer, $len, $ttl, $silence_read);
        if ($was_updated) {
          self::$cache->insert($value, $hash, $ttl, $silence_read);
        }

        return $was_updated;
    }

    public static function update(string $value, array $hash, int $ttl = -1, int $silence_read = -1): bool
    {
      self::ensureInit();

      if (Config::master()) {
        return self::original_update($value, $hash, $ttl, $silence_read);
      } else {
        $request = new UpdateTokenRequest();
        $request->setToken($value);
        $request->setPackedData(msgpack_pack($hash));
        $request->setTtl($ttl);
        $request->setSilenceRead($silence_read);

        list($response, $status) = Config::stub()->UpdateToken($request)->wait();

        if ($status->code !== \Grpc\STATUS_OK) {
            die($status->details);
        }

        return $response->getResult();
      }
    }

    public static function original_delete(string $value): bool
    {
        if (!Config::wasStarted()) {
            throw new \Exception(
                Validation::errorMessage(Validation::ERROR_NOT_STARTED)
            );
        }

        self::ensureInit();

        self::$validation->validateToken($value);

        self::$cache->delete($value);

        return self::$ffi->__delete($value);
    }

    public static function delete(string $value): bool
    {
      self::ensureInit();

      if (Config::master()) {
        return self::original_delete($value);
      } else {
        $request = new DeleteTokenRequest();
        $request->setToken($value);

        list($response, $status) = Config::stub()->DeleteToken($request)->wait();

        if ($status->code !== \Grpc\STATUS_OK) {
            die($status->details);
        }

        return $response->getResult();
      }
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
