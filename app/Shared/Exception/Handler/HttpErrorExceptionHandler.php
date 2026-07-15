<?php

declare(strict_types=1);

namespace App\Shared\Exception\Handler;

use App\Shared\Domain\Exception\HttpErrorInterface;
use App\Shared\Domain\Exception\RetryAfterAwareInterface;
use App\Shared\Exception\ErrorEnvelope;
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

        $body = json_encode(
            ErrorEnvelope::from($throwable),
            JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE
        );

        $response = $response
            ->withStatus($throwable->httpStatus())
            ->withHeader('Content-Type', 'application/json; charset=utf-8')
            ->withBody(new SwooleStream($body));

        if ($throwable instanceof RetryAfterAwareInterface) {
            $response = $response->withHeader('Retry-After', (string) $throwable->retryAfterSeconds());
        }

        return $response;
    }

    public function isValid(Throwable $throwable): bool
    {
        return $throwable instanceof HttpErrorInterface;
    }
}
