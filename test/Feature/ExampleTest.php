<?php

declare(strict_types=1);

namespace HyperfTest\Feature;

use Hyperf\Testing\TestCase;
use PHPUnit\Framework\Attributes\CoversNothing;

/**
 * @internal
 */
#[CoversNothing]
class ExampleTest extends TestCase
{
    public function testExample(): void
    {
        $this->get('/')->assertOk()->assertSee('Hyperf');
    }
}
