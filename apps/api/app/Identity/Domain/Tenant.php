<?php

declare(strict_types=1);

namespace App\Identity\Domain;

use DateTimeImmutable;
use DomainException;

/**
 * The isolation boundary (docs/architecture/data/42-entity-model-and-ownership.md
 * shared kernel). Framework-free per D-130 -- persistence lives in
 * Infrastructure/EloquentTenant and EloquentTenantRepository.
 */
final class Tenant
{
    public function __construct(
        public readonly string $id,
        private string $name,
        private TenantStatus $status,
        public readonly string $plan,
        public readonly string $region,
        public readonly DateTimeImmutable $createdAt,
    ) {}

    public static function provision(string $id, string $name, string $plan, string $region): self
    {
        return new self($id, $name, TenantStatus::Active, $plan, $region, new DateTimeImmutable);
    }

    public function name(): string
    {
        return $this->name;
    }

    public function status(): TenantStatus
    {
        return $this->status;
    }

    public function suspend(): void
    {
        if ($this->status === TenantStatus::Suspended) {
            throw new DomainException('Tenant is already suspended.');
        }

        $this->status = TenantStatus::Suspended;
    }

    public function reactivate(): void
    {
        $this->status = TenantStatus::Active;
    }
}
