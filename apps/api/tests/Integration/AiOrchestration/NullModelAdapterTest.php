<?php

declare(strict_types=1);

namespace Tests\Integration\AiOrchestration;

use App\AiOrchestration\Domain\ModelAdapter;
use App\AiOrchestration\Domain\Provider;
use App\AiOrchestration\Domain\ProviderNotConfigured;
use App\AiOrchestration\Infrastructure\NullModelAdapter;
use Tests\TestCase;

/**
 * Proves the default ModelAdapter binding fails loudly rather than
 * faking a response -- see NullModelAdapter's own docblock.
 */
class NullModelAdapterTest extends TestCase
{
    public function test_dispatch_throws_provider_not_configured(): void
    {
        $adapter = new NullModelAdapter;

        $this->expectException(ProviderNotConfigured::class);

        $adapter->dispatch('claude-sonnet-5-20260101', 'Summarize this brief.');
    }

    public function test_it_is_bound_as_the_default_model_adapter(): void
    {
        $adapter = $this->app->make(ModelAdapter::class);

        $this->assertInstanceOf(NullModelAdapter::class, $adapter);
        $this->assertSame(Provider::Anthropic, $adapter->provider());
    }
}
