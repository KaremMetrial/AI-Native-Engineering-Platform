<?php

declare(strict_types=1);

namespace App\Discovery\Application;

use App\Discovery\Domain\Constraint;
use App\Discovery\Domain\ConstraintRepository;
use App\Discovery\Domain\DiscoverySessionRepository;
use RuntimeException;

final class ListConstraintsForSession
{
    public function __construct(
        private readonly DiscoverySessionRepository $sessions,
        private readonly ConstraintRepository $constraints,
    ) {}

    /**
     * @return list<Constraint>
     */
    public function handle(string $sessionId): array
    {
        if ($this->sessions->findById($sessionId) === null) {
            throw new RuntimeException('Discovery session not found in this tenant.');
        }

        return $this->constraints->findAllBySession($sessionId);
    }
}
