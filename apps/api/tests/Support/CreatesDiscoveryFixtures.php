<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Discovery\Domain\DiscoverySession;
use App\Discovery\Infrastructure\EloquentDiscoverySessionRepository;
use Illuminate\Support\Str;

/**
 * Shared fixture builders for Discovery module tests. Reuses
 * CreatesGraphFixtures for tenant/user/project, since a DiscoverySession
 * structurally requires a project (FK to the shared kernel).
 */
trait CreatesDiscoveryFixtures
{
    use CreatesGraphFixtures;

    private function createDiscoverySession(string $tenantId, string $projectId, string $createdBy): string
    {
        $session = DiscoverySession::start((string) Str::uuid(), $tenantId, $projectId, 'Acme kickoff', $createdBy);
        (new EloquentDiscoverySessionRepository)->save($session);

        return $session->id;
    }
}
