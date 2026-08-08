<?php

declare(strict_types=1);

namespace App\AiOrchestration\Application;

use App\AiOrchestration\Domain\CapabilityRequirement;
use App\AiOrchestration\Domain\ModelRegistryEntry;
use App\AiOrchestration\Domain\ModelRegistryRepository;
use App\AiOrchestration\Domain\ModelStatus;
use App\AiOrchestration\Domain\TenantProviderPolicyRepository;

/**
 * The candidate-filter portion of the gateway's routing stage
 * (docs/architecture/ai/51-ai-gateway.md, D-566): governance before
 * anything else, then status and capability match. Deliberately stops
 * before ranking -- ranking needs evaluation scores (`57`), cost and
 * observed latency, none of which exist without real workflows and a
 * real adapter (see app/AiOrchestration/README.md). Candidates are
 * returned in registry order, not a meaningful preference order.
 */
final class SelectCandidateModels
{
    public function __construct(
        private readonly TenantProviderPolicyRepository $policies,
        private readonly ModelRegistryRepository $models,
    ) {}

    /**
     * @return list<ModelRegistryEntry>
     */
    public function handle(string $tenantId, CapabilityRequirement $requirement): array
    {
        $policy = $this->policies->findByTenant($tenantId);

        $active = $this->models->findByStatus(ModelStatus::Active);

        return array_values(array_filter(
            $active,
            fn (ModelRegistryEntry $entry): bool => ($policy === null || $policy->allows($entry->provider))
                && $entry->isEligibleFor($requirement),
        ));
    }
}
