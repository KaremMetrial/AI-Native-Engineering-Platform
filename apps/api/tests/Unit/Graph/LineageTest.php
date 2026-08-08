<?php

declare(strict_types=1);

namespace Tests\Unit\Graph;

use App\Graph\Domain\Lineage;
use Tests\TestCase;

class LineageTest extends TestCase
{
    public function test_human_lineage_is_not_ai_generated(): void
    {
        $lineage = Lineage::human();

        $this->assertFalse($lineage->isAiGenerated());
        $this->assertNull($lineage->model);
        $this->assertSame([], $lineage->inputVersionIds);
    }

    public function test_a_lineage_with_a_model_is_ai_generated(): void
    {
        $lineage = new Lineage(model: 'claude-sonnet-5', promptVersion: 'v3', inputVersionIds: ['v-1'], tokens: 500, cost: 0.02);

        $this->assertTrue($lineage->isAiGenerated());
    }
}
