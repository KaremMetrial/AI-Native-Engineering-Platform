<?php

declare(strict_types=1);

namespace Tests\Unit\AiOrchestration;

use App\AiOrchestration\Domain\Provider;
use App\AiOrchestration\Domain\TenantProviderPolicy;
use InvalidArgumentException;
use Tests\TestCase;

class TenantProviderPolicyTest extends TestCase
{
    public function test_restrict_creates_a_policy_allowing_only_the_given_providers(): void
    {
        $policy = TenantProviderPolicy::restrict('tenant-1', [Provider::Anthropic]);

        $this->assertTrue($policy->allows(Provider::Anthropic));
        $this->assertFalse($policy->allows(Provider::OpenAi));
    }

    public function test_restricting_to_an_empty_provider_set_throws(): void
    {
        $this->expectException(InvalidArgumentException::class);

        TenantProviderPolicy::restrict('tenant-1', []);
    }
}
