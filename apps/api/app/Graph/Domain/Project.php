<?php

declare(strict_types=1);

namespace App\Graph\Domain;

use DateTimeImmutable;

/**
 * Shared-kernel Project entity (docs/architecture/data/42-entity-model-and-ownership.md):
 * "kept minimal -- identity, name, status -- with rich project-related
 * state living in the contexts that own it." Deliberately minimal here
 * too: this exists because Artifact structurally requires a project_id
 * (a foreign key to the shared kernel, D-463), not because the "project
 * workspace" deliverable (docs/governance/20-roadmap.md Phase 1) is being
 * built as part of the Graph kernel. That deliverable -- project CRUD,
 * membership-to-project scoping, lifecycle stages -- is separate,
 * future work.
 */
final class Project
{
    public function __construct(
        public readonly string $id,
        public readonly string $tenantId,
        private string $name,
        private ProjectStatus $status,
        public readonly DateTimeImmutable $createdAt,
    ) {}

    public static function create(string $id, string $tenantId, string $name): self
    {
        return new self($id, $tenantId, $name, ProjectStatus::Active, new DateTimeImmutable);
    }

    public function name(): string
    {
        return $this->name;
    }

    public function status(): ProjectStatus
    {
        return $this->status;
    }
}
