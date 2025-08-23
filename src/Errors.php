<?php
namespace CRUD_JT;

require_once __DIR__ . '/Errors/InternalError.php';
require_once __DIR__ . '/Errors/DonateException.php';

use CRUD_JT\Errors\InternalError;
use CRUD_JT\Errors\DonateException;

final class Errors
{
    private const MAP = [
        'XX000' => InternalError::class,
        'DE000' => DonateException::class
    ];

    public static function raise(string $code, string $message): void
    {
        if (!isset(self::MAP[$code])) {
            throw new \Exception("Unknown error code: $code. Message: $message");
        }

        $errorClass = self::MAP[$code];
        throw new $errorClass($message);
    }
}
