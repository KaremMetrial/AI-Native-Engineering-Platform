<?php

declare(strict_types=1);

namespace Tests\Unit\AiOrchestration;

use App\AiOrchestration\Domain\ModelPricing;
use InvalidArgumentException;
use Tests\TestCase;

class ModelPricingTest extends TestCase
{
    public function test_stores_the_rates(): void
    {
        $pricing = new ModelPricing(3.0, 15.0, 0.3);

        $this->assertSame(3.0, $pricing->inputPerMillionTokensUsd);
        $this->assertSame(15.0, $pricing->outputPerMillionTokensUsd);
        $this->assertSame(0.3, $pricing->cachedInputPerMillionTokensUsd);
    }

    public function test_rejects_a_negative_rate(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new ModelPricing(-1.0, 15.0, 0.3);
    }
}
