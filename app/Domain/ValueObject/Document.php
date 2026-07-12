<?php

declare(strict_types=1);

namespace App\Domain\ValueObject;

use App\Domain\Exception\InvalidDocumentException;

/**
 * CPF (11 digits) or CNPJ (14 digits), stored as digits only,
 * validated by check digits.
 */
final readonly class Document
{
    private const CPF_LENGTH = 11;

    private const CNPJ_LENGTH = 14;

    private function __construct(public string $value)
    {
    }

    public static function fromString(string $raw): self
    {
        $digits = preg_replace('/\D/', '', $raw) ?? '';

        $isValid = match (strlen($digits)) {
            self::CPF_LENGTH => self::isValidCpf($digits),
            self::CNPJ_LENGTH => self::isValidCnpj($digits),
            default => false,
        };

        if (!$isValid) {
            throw new InvalidDocumentException();
        }

        return new self($digits);
    }

    private static function isValidCpf(string $digits): bool
    {
        if (self::hasRepeatedDigits($digits)) {
            return false;
        }

        foreach ([9, 10] as $position) {
            $sum = 0;
            for ($i = 0; $i < $position; ++$i) {
                $sum += (int) $digits[$i] * (($position + 1) - $i);
            }

            if ((int) $digits[$position] !== (10 * $sum) % 11 % 10) {
                return false;
            }
        }

        return true;
    }

    private static function isValidCnpj(string $digits): bool
    {
        if (self::hasRepeatedDigits($digits)) {
            return false;
        }

        $weights = [
            12 => [5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2],
            13 => [6, 5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2],
        ];

        foreach ($weights as $position => $positionWeights) {
            $sum = 0;
            foreach ($positionWeights as $i => $weight) {
                $sum += (int) $digits[$i] * $weight;
            }

            $expected = $sum % 11 < 2 ? 0 : 11 - ($sum % 11);
            if ((int) $digits[$position] !== $expected) {
                return false;
            }
        }

        return true;
    }

    /**
     * Sequences like 111.111.111-11 satisfy the check digit arithmetic
     * but are not real documents.
     */
    private static function hasRepeatedDigits(string $digits): bool
    {
        return preg_match('/^(\d)\1+$/', $digits) === 1;
    }
}
