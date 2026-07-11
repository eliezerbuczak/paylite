<?php

declare(strict_types=1);

namespace HyperfTest\Integration;

use HyperfTest\Support\RefreshesDatabase;
use PHPUnit\Framework\TestCase;

/**
 * Persistência é testada através dos adapters (repositories) —
 * nunca contra tabelas soltas. Ver skill tdd.
 */
abstract class IntegrationTestCase extends TestCase
{
    use RefreshesDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->refreshDatabase();
    }
}
