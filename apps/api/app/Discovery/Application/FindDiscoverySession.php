<?php

declare(strict_types=1);

namespace App\Discovery\Application;

use App\Discovery\Domain\DiscoverySession;
use App\Discovery\Domain\DiscoverySessionRepository;

final class FindDiscoverySession
{
    public function __construct(
        private readonly DiscoverySessionRepository $sessions,
    ) {}

    public function handle(string $sessionId): ?DiscoverySession
    {
        return $this->sessions->findById($sessionId);
    }
}
