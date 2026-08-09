<?php

declare(strict_types=1);

namespace App\Discovery\Application;

use App\Discovery\Domain\DiscoverySession;
use App\Discovery\Domain\DiscoverySessionRepository;

final class ListDiscoverySessions
{
    public function __construct(
        private readonly DiscoverySessionRepository $sessions,
    ) {}

    /**
     * @return list<DiscoverySession>
     */
    public function handle(): array
    {
        return $this->sessions->findAll();
    }
}
