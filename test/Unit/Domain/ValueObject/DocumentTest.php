<?php

declare(strict_types=1);

namespace HyperfTest\Unit\Domain\ValueObject;

use App\Domain\Exception\InvalidDocumentException;
use App\Domain\ValueObject\Document;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(Document::class)]
class DocumentTest extends TestCase
{
    public function test_accepts_valid_cpf(): void
    {
        $document = Document::fromString('52998224725');

        self::assertSame('52998224725', $document->value);
    }

    public function test_normalizes_formatted_cpf(): void
    {
        $document = Document::fromString('529.982.247-25');

        self::assertSame('52998224725', $document->value);
    }

    public function test_accepts_valid_cnpj(): void
    {
        $document = Document::fromString('11222333000181');

        self::assertSame('11222333000181', $document->value);
    }

    public function test_normalizes_formatted_cnpj(): void
    {
        $document = Document::fromString('11.222.333/0001-81');

        self::assertSame('11222333000181', $document->value);
    }

    public function test_rejects_cpf_with_wrong_check_digit(): void
    {
        $this->expectException(InvalidDocumentException::class);

        Document::fromString('52998224724');
    }

    public function test_rejects_cnpj_with_wrong_check_digit(): void
    {
        $this->expectException(InvalidDocumentException::class);

        Document::fromString('11222333000180');
    }

    public function test_rejects_cpf_with_repeated_digits(): void
    {
        $this->expectException(InvalidDocumentException::class);

        Document::fromString('11111111111');
    }

    public function test_rejects_wrong_length(): void
    {
        $this->expectException(InvalidDocumentException::class);

        Document::fromString('12345');
    }

    public function test_rejects_empty_string(): void
    {
        $this->expectException(InvalidDocumentException::class);

        Document::fromString('');
    }
}
