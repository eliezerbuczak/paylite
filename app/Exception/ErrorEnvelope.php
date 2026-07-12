<?php

declare(strict_types=1);

namespace App\Exception;

use App\Domain\Exception\HttpErrorInterface;

/**
 * Single source of the standard error envelope shape.
 */
final class ErrorEnvelope
{
    /**
     * @return array{error: array{code: string, message: string}}
     */
    public static function from(HttpErrorInterface $error): array
    {
        return [
            'error' => [
                'code' => $error->errorCode(),
                'message' => $error->getMessage(),
            ],
        ];
    }
}
