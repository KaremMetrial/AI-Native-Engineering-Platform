<?php

declare(strict_types=1);

namespace App\Discovery\Application;

use App\Discovery\Domain\Assumption;
use App\Discovery\Domain\AssumptionRepository;
use App\Discovery\Domain\DiscoverySessionRepository;
use RuntimeException;

final class ListAssumptionsForSession
{
    public function __construct(
        private readonly DiscoverySessionRepository $sessions,
        private readonly AssumptionRepository $assumptions,
    ) {}

    /**
     * @return list<Assumption>
     */
    public function handle(string $sessionId): array
    {
        if ($this->sessions->findById($sessionId) === null) {
            throw new RuntimeException('Discovery session not found in this tenant.');
        }

        return $this->assumptions->findAllBySession($sessionId);
    }
}
