<?php

declare(strict_types=1);

namespace App\AiOrchestration\Domain;

use DateTimeImmutable;
use DomainException;

/**
 * The P-9 sync/async boundary made concrete
 * (docs/architecture/04-non-functional-requirements.md: "no AI inference
 * in a synchronous request path"): a request to generate something
 * creates this record, queues a job, and returns immediately. The job
 * advances this record's state; the HTTP caller polls it. Named after
 * D-469's GenerationRecord ("the source of truth for AI cost and
 * lineage") -- this pass only carries candidate selection, not cost or
 * lineage yet, since nothing dispatches to a real provider (see
 * GenerationStatus's docblock).
 */
final class GenerationRecord
{
    public function __construct(
        public readonly string $id,
        public readonly string $tenantId,
        public readonly string $workflowName,
        public readonly CapabilityRequirement $capabilityRequirement,
        public readonly string $requestedBy,
        private GenerationStatus $status,
        public readonly DateTimeImmutable $createdAt,
        private ?string $selectedModelId = null,
        private ?string $failureReason = null,
    ) {}

    public static function request(
        string $id,
        string $tenantId,
        string $workflowName,
        CapabilityRequirement $capabilityRequirement,
        string $requestedBy,
    ): self {
        return new self($id, $tenantId, $workflowName, $capabilityRequirement, $requestedBy, GenerationStatus::Queued, new DateTimeImmutable);
    }

    public function selectModel(string $modelId): void
    {
        if ($this->status !== GenerationStatus::Queued) {
            throw new DomainException("Generation request [{$this->id}] is no longer queued.");
        }

        $this->status = GenerationStatus::Selected;
        $this->selectedModelId = $modelId;
    }

    public function fail(string $reason): void
    {
        if ($this->status !== GenerationStatus::Queued) {
            throw new DomainException("Generation request [{$this->id}] is no longer queued.");
        }

        $this->status = GenerationStatus::Failed;
        $this->failureReason = $reason;
    }

    public function status(): GenerationStatus
    {
        return $this->status;
    }

    public function selectedModelId(): ?string
    {
        return $this->selectedModelId;
    }

    public function failureReason(): ?string
    {
        return $this->failureReason;
    }
}
