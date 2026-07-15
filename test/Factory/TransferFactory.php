<?php

declare(strict_types=1);

namespace HyperfTest\Factory;

use App\Transfer\Infrastructure\Model\Transfer;

final class TransferFactory
{
    /**
     * @param array<string, mixed> $overrides
     */
    public static function create(array $overrides = []): Transfer
    {
        $transfer = new Transfer();
        $transfer->fill(array_merge([
            'payer_id' => UserFactory::common()->id,
            'payee_id' => UserFactory::merchant()->id,
            'amount_cents' => 1000,
        ], $overrides));
        $transfer->save();

        return $transfer;
    }
}
