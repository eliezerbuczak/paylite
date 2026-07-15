<?php

declare(strict_types=1);

namespace App\Controller;

use App\Domain\Entity\Deposit;
use App\Domain\ValueObject\Money;
use App\DTO\DepositMoneyInput;
use App\Service\DepositMoneyService;
use App\Shared\Exception\MalformedRequestException;
use Hyperf\HttpServer\Contract\RequestInterface;
use Hyperf\HttpServer\Contract\ResponseInterface;
use Psr\Http\Message\ResponseInterface as PsrResponse;

final class WalletController
{
    public function __construct(
        private readonly DepositMoneyService $depositMoney,
        private readonly ResponseInterface $response,
    ) {
    }

    public function deposit(RequestInterface $request, int $userId): PsrResponse
    {
        $deposit = $this->depositMoney->execute(new DepositMoneyInput(
            userId: $userId,
            amount: Money::fromDecimal($this->requireNumeric($request, 'value')),
        ));

        return $this->response
            ->json($this->present($deposit))
            ->withStatus(201)
            ->withAddedHeader('Location', "/wallets/{$userId}/deposits/{$deposit->id}");
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
    private function present(Deposit $deposit): array
    {
        return [
            'id' => $deposit->id,
            'value' => $deposit->amount->toDecimal(),
            'created_at' => $deposit->createdAt->format(DATE_ATOM),
        ];
    }
}
