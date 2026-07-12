<?php

declare(strict_types=1);

namespace HyperfTest\Feature;

use Hyperf\Testing\TestCase;
use HyperfTest\Support\RefreshesDatabase;

abstract class FeatureTestCase extends TestCase
{
    use RefreshesDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->refreshDatabase();
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        restore_error_handler();
        restore_exception_handler();
    }
}
