<?php

declare(strict_types=1);

namespace App\Shared\Exception\Handler;

use Hyperf\Contract\StdoutLoggerInterface;
use Hyperf\ExceptionHandler\ExceptionHandler;
use Hyperf\HttpMessage\Stream\SwooleStream;
use Psr\Http\Message\ResponseInterface;
use Throwable;

class AppExceptionHandler extends ExceptionHandler
{
    public function __construct(protected StdoutLoggerInterface $logger)
    {
    }

    public function handle(Throwable $throwable, ResponseInterface $response): ResponseInterface
    {
        $this->logger->error(sprintf('%s[%s] in %s', $throwable->getMessage(), $throwable->getLine(), $throwable->getFile()));
        $this->logger->error($throwable->getTraceAsString());

        $body = json_encode([
            'error' => [
                'code' => 'INTERNAL_ERROR',
                'message' => 'An unexpected error occurred.',
            ],
        ], JSON_THROW_ON_ERROR);

        return $response
            ->withStatus(500)
            ->withHeader('Content-Type', 'application/json; charset=utf-8')
            ->withBody(new SwooleStream($body));
    }

    /**
     * @SuppressWarnings("PHPMD.UnusedFormalParameter") required by ExceptionHandler
     */
    public function isValid(Throwable $throwable): bool
    {
        return true;
    }
}
