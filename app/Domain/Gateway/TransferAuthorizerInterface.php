<?php

declare(strict_types=1);

namespace App\Domain\Gateway;

use App\Domain\Exception\AuthorizerUnavailableException;

interface TransferAuthorizerInterface
{
    /**
     * Consults the external authorizer. A denial is a business answer (false);
     * not getting an answer at all is an infrastructure failure.
     *
     * @throws AuthorizerUnavailableException when the authorizer cannot be reached
     */
    public function isAuthorized(): bool;
}
