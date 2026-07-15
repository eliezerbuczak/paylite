<?php

declare(strict_types=1);

namespace App\Gateway;

use App\Domain\Exception\AuthorizerUnavailableException;
use App\Domain\Gateway\TransferAuthorizerInterface;
use App\Shared\Exception\ServiceUnavailableException;
use App\Shared\Infrastructure\Resilience\CircuitBreaker;

/**
 * Resilience decorator over the authorizer port: when the breaker is open
 * the request is rejected in microseconds instead of paying the timeout.
 * A denial is a business answer and never counts as a failure.
 */
final readonly class CircuitBreakerAuthorizer implements TransferAuthorizerInterface
{
    public function __construct(
        private TransferAuthorizerInterface $inner,
        private CircuitBreaker $breaker,
    ) {
    }

    /**
     * @throws AuthorizerUnavailableException propagated from the inner adapter, after counting the failure
     * @throws ServiceUnavailableException when the breaker is open and the call is rejected upfront
     */
    public function isAuthorized(): bool
    {
        if ($this->breaker->isOpen()) {
            throw new ServiceUnavailableException($this->breaker->retryAfterSeconds());
        }

        try {
            $authorized = $this->inner->isAuthorized();
        } catch (AuthorizerUnavailableException $error) {
            $this->breaker->recordFailure();

            throw $error;
        }

        $this->breaker->recordSuccess();

        return $authorized;
    }
}
