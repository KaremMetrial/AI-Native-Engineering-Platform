<?php

declare(strict_types=1);

namespace App\Discovery\Application;

use App\Discovery\Domain\DiscoverySession;
use App\Discovery\Domain\DiscoverySessionRepository;
use RuntimeException;

/**
 * Marks a session complete. Does not publish `Discovery.SessionCompleted`
 * (`docs/architecture/design/35-event-architecture.md`) -- that requires
 * the transactional outbox, which is cross-cutting shared infrastructure
 * with no consumer yet (Requirements, the only subscriber, does not exist
 * yet either). Building the outbox now for zero consumers would be
 * speculative; see app/Discovery/README.md.
 */
final class CompleteDiscoverySession
{
    public function __construct(
        private readonly DiscoverySessionRepository $sessions,
    ) {}

    public function handle(string $sessionId): DiscoverySession
    {
        $session = $this->sessions->findById($sessionId);

        if ($session === null) {
            throw new RuntimeException('Discovery session not found in this tenant.');
        }

        $session->complete();

        $this->sessions->save($session);

        return $session;
    }
}
