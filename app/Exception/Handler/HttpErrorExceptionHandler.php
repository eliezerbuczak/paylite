<?php

declare(strict_types=1);

namespace App\Exception\Handler;

use App\Domain\Exception\HttpErrorInterface;
use Hyperf\ExceptionHandler\ExceptionHandler;
use Hyperf\HttpMessage\Stream\SwooleStream;
use Psr\Http\Message\ResponseInterface;
use Throwable;

final class HttpErrorExceptionHandler extends ExceptionHandler
{
    public function handle(Throwable $throwable, ResponseInterface $response): ResponseInterface
    {
        if (!$throwable instanceof HttpErrorInterface) {
            return $response;
        }

        $this->stopPropagation();

        $body = json_encode([
            'error' => [
                'code' => $throwable->errorCode(),
                'message' => $throwable->getMessage(),
            ],
        ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);

        return $response
            ->withStatus($throwable->httpStatus())
            ->withHeader('Content-Type', 'application/json; charset=utf-8')
            ->withBody(new SwooleStream($body));
    }

    public function isValid(Throwable $throwable): bool
    {
        return $throwable instanceof HttpErrorInterface;
    }
}
