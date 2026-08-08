<?php

declare(strict_types=1);

namespace App\Discovery\Application;

use App\Discovery\Domain\Constraint;
use App\Discovery\Domain\ConstraintRepository;
use App\Discovery\Domain\DiscoverySessionRepository;
use App\Discovery\Domain\SessionStatus;
use Illuminate\Support\Str;
use RuntimeException;

final class CaptureConstraint
{
    public function __construct(
        private readonly DiscoverySessionRepository $sessions,
        private readonly ConstraintRepository $constraints,
    ) {}

    public function handle(string $tenantId, string $sessionId, string $statement, string $createdBy): Constraint
    {
        $session = $this->sessions->findById($sessionId);

        if ($session === null) {
            throw new RuntimeException('Discovery session not found in this tenant.');
        }

        if ($session->status() !== SessionStatus::InProgress) {
            throw new RuntimeException('Cannot capture a constraint on a completed discovery session.');
        }

        $constraint = Constraint::capture(
            id: (string) Str::uuid(),
            tenantId: $tenantId,
            sessionId: $sessionId,
            statement: $statement,
            createdBy: $createdBy,
        );

        $this->constraints->save($constraint);

        return $constraint;
    }
}
