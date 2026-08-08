<?php

declare(strict_types=1);

namespace Tests\Unit\AiOrchestration;

use App\AiOrchestration\Domain\CapabilityRequirement;
use App\AiOrchestration\Domain\ModelCapabilities;
use App\AiOrchestration\Domain\StreamingCapability;
use App\AiOrchestration\Domain\StructuredOutputCapability;
use App\AiOrchestration\Domain\ToolUseCapability;
use Tests\TestCase;

class ModelCapabilitiesTest extends TestCase
{
    public function test_a_model_satisfies_an_equal_requirement(): void
    {
        $capabilities = new ModelCapabilities(
            structuredOutput: StructuredOutputCapability::StrictSchema,
            toolUse: ToolUseCapability::Sequential,
            streaming: StreamingCapability::Text,
        );
        $requirement = new CapabilityRequirement(
            structuredOutput: StructuredOutputCapability::StrictSchema,
            toolUse: ToolUseCapability::Sequential,
            streaming: StreamingCapability::Text,
            minContextWindow: 1,
        );

        $this->assertTrue($capabilities->satisfies($requirement));
    }

    public function test_a_model_satisfies_a_weaker_requirement(): void
    {
        $capabilities = new ModelCapabilities(
            structuredOutput: StructuredOutputCapability::ToolSchema,
            toolUse: ToolUseCapability::Parallel,
            streaming: StreamingCapability::TextAndTools,
        );
        $requirement = new CapabilityRequirement(
            structuredOutput: StructuredOutputCapability::None,
            toolUse: ToolUseCapability::None,
            streaming: StreamingCapability::None,
            minContextWindow: 1,
        );

        $this->assertTrue($capabilities->satisfies($requirement));
    }

    public function test_a_model_does_not_satisfy_a_stronger_structured_output_requirement(): void
    {
        $capabilities = new ModelCapabilities(
            structuredOutput: StructuredOutputCapability::JsonMode,
            toolUse: ToolUseCapability::None,
            streaming: StreamingCapability::None,
        );
        $requirement = new CapabilityRequirement(
            structuredOutput: StructuredOutputCapability::StrictSchema,
            toolUse: ToolUseCapability::None,
            streaming: StreamingCapability::None,
            minContextWindow: 1,
        );

        $this->assertFalse($capabilities->satisfies($requirement));
    }

    public function test_a_model_does_not_satisfy_a_stronger_tool_use_requirement(): void
    {
        $capabilities = new ModelCapabilities(
            structuredOutput: StructuredOutputCapability::None,
            toolUse: ToolUseCapability::Sequential,
            streaming: StreamingCapability::None,
        );
        $requirement = new CapabilityRequirement(
            structuredOutput: StructuredOutputCapability::None,
            toolUse: ToolUseCapability::Parallel,
            streaming: StreamingCapability::None,
            minContextWindow: 1,
        );

        $this->assertFalse($capabilities->satisfies($requirement));
    }

    public function test_a_model_does_not_satisfy_a_stronger_streaming_requirement(): void
    {
        $capabilities = new ModelCapabilities(
            structuredOutput: StructuredOutputCapability::None,
            toolUse: ToolUseCapability::None,
            streaming: StreamingCapability::Text,
        );
        $requirement = new CapabilityRequirement(
            structuredOutput: StructuredOutputCapability::None,
            toolUse: ToolUseCapability::None,
            streaming: StreamingCapability::TextAndTools,
            minContextWindow: 1,
        );

        $this->assertFalse($capabilities->satisfies($requirement));
    }

    public function test_a_model_without_vision_does_not_satisfy_a_vision_requirement(): void
    {
        $capabilities = new ModelCapabilities(
            structuredOutput: StructuredOutputCapability::None,
            toolUse: ToolUseCapability::None,
            streaming: StreamingCapability::None,
            vision: false,
        );
        $requirement = new CapabilityRequirement(
            structuredOutput: StructuredOutputCapability::None,
            toolUse: ToolUseCapability::None,
            streaming: StreamingCapability::None,
            minContextWindow: 1,
            requiresVision: true,
        );

        $this->assertFalse($capabilities->satisfies($requirement));
    }

    public function test_a_model_without_a_deterministic_seed_does_not_satisfy_that_requirement(): void
    {
        $capabilities = new ModelCapabilities(
            structuredOutput: StructuredOutputCapability::None,
            toolUse: ToolUseCapability::None,
            streaming: StreamingCapability::None,
            deterministicSeed: false,
        );
        $requirement = new CapabilityRequirement(
            structuredOutput: StructuredOutputCapability::None,
            toolUse: ToolUseCapability::None,
            streaming: StreamingCapability::None,
            minContextWindow: 1,
            requiresDeterministicSeed: true,
        );

        $this->assertFalse($capabilities->satisfies($requirement));
    }
}
