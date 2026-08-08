<?php

declare(strict_types=1);

namespace App\Discovery\Application;

use App\Discovery\Domain\Assumption;
use App\Discovery\Domain\AssumptionRepository;
use App\Discovery\Domain\DiscoverySessionRepository;
use App\Discovery\Domain\SessionStatus;
use Illuminate\Support\Str;
use RuntimeException;

final class CaptureAssumption
{
    public function __construct(
        private readonly DiscoverySessionRepository $sessions,
        private readonly AssumptionRepository $assumptions,
    ) {}

    public function handle(string $tenantId, string $sessionId, string $statement, string $createdBy): Assumption
    {
        $session = $this->sessions->findById($sessionId);

        if ($session === null) {
            throw new RuntimeException('Discovery session not found in this tenant.');
        }

        if ($session->status() !== SessionStatus::InProgress) {
            throw new RuntimeException('Cannot capture an assumption on a completed discovery session.');
        }

        $assumption = Assumption::capture(
            id: (string) Str::uuid(),
            tenantId: $tenantId,
            sessionId: $sessionId,
            statement: $statement,
            createdBy: $createdBy,
        );

        $this->assumptions->save($assumption);

        return $assumption;
    }
}
