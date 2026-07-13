<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Domain\Exception\HttpErrorInterface;
use App\Exception\ErrorEnvelope;
use App\Exception\IdempotentRequestInFlightException;
use Hyperf\HttpServer\Contract\ResponseInterface as HttpResponse;
use Hyperf\Redis\Redis;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Throwable;

/**
 * Deduplicates retries carrying an Idempotency-Key header (client-generated
 * UUID). Requests without the header are processed normally, with no dedup.
 */
final class IdempotencyMiddleware implements MiddlewareInterface
{
    private const HEADER = 'Idempotency-Key';

    private const TTL_SECONDS = 86400;

    private const STATE_PROCESSING = 'processing';

    public function __construct(
        private readonly Redis $redis,
        private readonly HttpResponse $response,
    ) {
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $key = $request->getHeaderLine(self::HEADER);
        if ($key === '') {
            return $handler->handle($request);
        }

        $redisKey = "idempotency:{$key}";

        if (!$this->claim($redisKey)) {
            return $this->replay($redisKey)
                ->withHeader(self::HEADER, $key)
                ->withHeader('Idempotent-Replayed', 'true');
        }

        try {
            $response = $handler->handle($request);
        } catch (Throwable $error) {
            $this->recordFailure($redisKey, $error);

            throw $error;
        }

        $body = json_decode((string) $response->getBody(), true);
        $this->remember($redisKey, $response->getStatusCode(), is_array($body) ? $body : []);

        return $response->withHeader(self::HEADER, $key);
    }

    /**
     * Business failures are final: record them so a retry replays the same
     * answer. Infrastructure failures release the key so the client can
     * retry for real.
     */
    private function recordFailure(string $redisKey, Throwable $error): void
    {
        if ($error instanceof HttpErrorInterface) {
            $this->remember($redisKey, $error->httpStatus(), ErrorEnvelope::from($error));

            return;
        }

        $this->redis->del($redisKey);
    }

    private function claim(string $redisKey): bool
    {
        $processing = json_encode(['state' => self::STATE_PROCESSING], JSON_THROW_ON_ERROR);

        return (bool) $this->redis->set($redisKey, $processing, ['nx', 'ex' => self::TTL_SECONDS]);
    }

    /**
     * @param array<string, mixed> $body
     */
    private function remember(string $redisKey, int $status, array $body): void
    {
        $stored = json_encode([
            'state' => 'done',
            'status' => $status,
            'body' => $body,
        ], JSON_THROW_ON_ERROR);

        $this->redis->set($redisKey, $stored, ['ex' => self::TTL_SECONDS]);
    }

    private function replay(string $redisKey): ResponseInterface
    {
        $stored = $this->redis->get($redisKey);
        $data = is_string($stored) ? json_decode($stored, true) : null;

        if (!is_array($data) || ($data['state'] ?? null) === self::STATE_PROCESSING) {
            throw new IdempotentRequestInFlightException();
        }

        $body = is_array($data['body'] ?? null) ? $data['body'] : [];

        return $this->response
            ->json($body)
            ->withStatus((int) ($data['status'] ?? 200));
    }
}
