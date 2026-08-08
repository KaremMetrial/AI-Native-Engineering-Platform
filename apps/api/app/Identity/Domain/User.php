<?php

declare(strict_types=1);

namespace App\Identity\Domain;

use DateTimeImmutable;

/**
 * A person's platform identity (docs/architecture/data/42-entity-model-and-ownership.md
 * shared kernel) -- global, not tenant-scoped. Tenant membership is a
 * separate relation (Membership), since a user may belong to several
 * tenants. Framework-free per D-130.
 */
final class User
{
    public function __construct(
        public readonly string $id,
        private string $name,
        private string $email,
        private string $hashedPassword,
        private UserStatus $status,
        public readonly DateTimeImmutable $createdAt,
    ) {}

    public static function register(string $id, string $name, string $email, string $hashedPassword): self
    {
        return new self($id, $name, $email, $hashedPassword, UserStatus::Active, new DateTimeImmutable);
    }

    public function name(): string
    {
        return $this->name;
    }

    public function email(): string
    {
        return $this->email;
    }

    public function hashedPassword(): string
    {
        return $this->hashedPassword;
    }

    public function status(): UserStatus
    {
        return $this->status;
    }

    public function isActive(): bool
    {
        return $this->status === UserStatus::Active;
    }
}
