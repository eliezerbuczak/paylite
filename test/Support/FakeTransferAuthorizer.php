<?php

declare(strict_types=1);

namespace HyperfTest\Support;

use App\Transfer\Domain\Exception\AuthorizerUnavailableException;
use App\Transfer\Domain\Gateway\TransferAuthorizerInterface;
use Closure;

/**
 * Reproduces the three possible outcomes of the external authorizer.
 * Feature tests bind it in the container so they never hit the real service.
 */
final class FakeTransferAuthorizer implements TransferAuthorizerInterface
{
    /**
     * @param Closure(): bool $behavior
     */
    private function __construct(private readonly Closure $behavior)
    {
    }

    public static function authorizing(): self
    {
        return new self(static fn (): bool => true);
    }

    public static function denying(): self
    {
        return new self(static fn (): bool => false);
    }

    public static function unavailable(): self
    {
        return new self(static fn (): bool => throw new AuthorizerUnavailableException());
    }

    public function isAuthorized(): bool
    {
        return ($this->behavior)();
    }
}
