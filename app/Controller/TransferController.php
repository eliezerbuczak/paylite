<?php

declare(strict_types=1);

namespace App\Controller;

use App\Domain\Entity\Transfer;
use App\Domain\ValueObject\Money;
use App\DTO\TransferMoneyInput;
use App\Service\TransferMoneyService;
use App\Shared\Exception\MalformedRequestException;
use Hyperf\HttpServer\Contract\RequestInterface;
use Hyperf\HttpServer\Contract\ResponseInterface;
use Psr\Http\Message\ResponseInterface as PsrResponse;

final class TransferController
{
    public function __construct(
        private readonly TransferMoneyService $transferMoney,
        private readonly ResponseInterface $response,
    ) {
    }

    public function store(RequestInterface $request): PsrResponse
    {
        $transfer = $this->transferMoney->execute(new TransferMoneyInput(
            payerId: $this->requireInteger($request, 'payer'),
            payeeId: $this->requireInteger($request, 'payee'),
            amount: Money::fromDecimal($this->requireNumeric($request, 'value')),
        ));

        return $this->response
            ->json($this->present($transfer))
            ->withStatus(201)
            ->withAddedHeader('Location', "/transfers/{$transfer->id}");
    }

    private function requireInteger(RequestInterface $request, string $field): int
    {
        $value = $request->input($field);

        if (!is_int($value)) {
            throw MalformedRequestException::missingIntegerField($field);
        }

        return $value;
    }

    private function requireNumeric(RequestInterface $request, string $field): float
    {
        $value = $request->input($field);

        if (!is_int($value) && !is_float($value)) {
            throw MalformedRequestException::missingNumericField($field);
        }

        return (float) $value;
    }

    /**
     * @return array<string, float|int|string>
     */
    private function present(Transfer $transfer): array
    {
        return [
            'id' => $transfer->id,
            'value' => $transfer->amount->toDecimal(),
            'payer' => $transfer->payerId,
            'payee' => $transfer->payeeId,
            'created_at' => $transfer->createdAt->format(DATE_ATOM),
        ];
    }
}
