<?php

declare(strict_types=1);

namespace Tests\Unit\AiOrchestration;

use App\AiOrchestration\Domain\CapabilityRequirement;
use App\AiOrchestration\Domain\ModelCapabilities;
use App\AiOrchestration\Domain\ModelPricing;
use App\AiOrchestration\Domain\ModelRegistryEntry;
use App\AiOrchestration\Domain\ModelStatus;
use App\AiOrchestration\Domain\ModelTier;
use App\AiOrchestration\Domain\Provider;
use App\AiOrchestration\Domain\StreamingCapability;
use App\AiOrchestration\Domain\StructuredOutputCapability;
use App\AiOrchestration\Domain\ToolUseCapability;
use DomainException;
use Tests\TestCase;

class ModelRegistryEntryTest extends TestCase
{
    public function test_register_produces_a_trial_entry(): void
    {
        $entry = $this->registerEntry();

        $this->assertSame(ModelStatus::Trial, $entry->status());
    }

    public function test_promote_transitions_trial_to_active(): void
    {
        $entry = $this->registerEntry();

        $entry->promote();

        $this->assertSame(ModelStatus::Active, $entry->status());
    }

    public function test_promote_throws_when_not_trial(): void
    {
        $entry = $this->registerEntry();
        $entry->promote();

        $this->expectException(DomainException::class);

        $entry->promote();
    }

    public function test_deprecate_transitions_active_to_deprecated(): void
    {
        $entry = $this->registerEntry();
        $entry->promote();

        $entry->deprecate();

        $this->assertSame(ModelStatus::Deprecated, $entry->status());
    }

    public function test_deprecate_throws_when_not_active(): void
    {
        $entry = $this->registerEntry();

        $this->expectException(DomainException::class);

        $entry->deprecate();
    }

    public function test_retire_transitions_from_any_status(): void
    {
        $entry = $this->registerEntry();

        $entry->retire();

        $this->assertSame(ModelStatus::Retired, $entry->status());
    }

    public function test_is_eligible_for_requires_active_status(): void
    {
        $entry = $this->registerEntry();
        $requirement = new CapabilityRequirement(
            structuredOutput: StructuredOutputCapability::None,
            toolUse: ToolUseCapability::None,
            streaming: StreamingCapability::None,
            minContextWindow: 1,
        );

        $this->assertFalse($entry->isEligibleFor($requirement));

        $entry->promote();

        $this->assertTrue($entry->isEligibleFor($requirement));
    }

    public function test_is_eligible_for_requires_sufficient_context_window(): void
    {
        $entry = $this->registerEntry(contextWindow: 1000);
        $entry->promote();

        $requirement = new CapabilityRequirement(
            structuredOutput: StructuredOutputCapability::None,
            toolUse: ToolUseCapability::None,
            streaming: StreamingCapability::None,
            minContextWindow: 5000,
        );

        $this->assertFalse($entry->isEligibleFor($requirement));
    }

    private function registerEntry(int $contextWindow = 200000): ModelRegistryEntry
    {
        return ModelRegistryEntry::register(
            id: 'entry-1',
            modelId: 'claude-sonnet-5-20260101',
            provider: Provider::Anthropic,
            tier: ModelTier::Balanced,
            capabilities: new ModelCapabilities(
                structuredOutput: StructuredOutputCapability::StrictSchema,
                toolUse: ToolUseCapability::Parallel,
                streaming: StreamingCapability::TextAndTools,
            ),
            contextWindow: $contextWindow,
            maxOutput: 8192,
            pricing: new ModelPricing(3.0, 15.0, 0.3),
        );
    }
}
