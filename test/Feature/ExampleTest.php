<?php

declare(strict_types=1);

namespace HyperfTest\Feature;

use PHPUnit\Framework\Attributes\CoversNothing;

/**
 * @internal
 */
#[CoversNothing]
class ExampleTest extends FeatureTestCase
{
    public function testExample(): void
    {
        $this->get('/')->assertOk()->assertSee('Hyperf');
    }
}
