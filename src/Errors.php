<?php
namespace CRUDJT;

require_once __DIR__ . '/Errors/InternalError.php';
require_once __DIR__ . '/Errors/InvalidState.php';

use CRUDJT\Errors\InternalError;
use CRUDJT\Errors\InvalidState;

final class Errors
{
    private const MAP = [
        'XX000' => InternalError::class,
        '55JT01' => InvalidState::class
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
