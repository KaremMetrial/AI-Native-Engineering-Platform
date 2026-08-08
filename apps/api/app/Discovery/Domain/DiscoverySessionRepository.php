<?php

declare(strict_types=1);

namespace App\Discovery\Domain;

interface DiscoverySessionRepository
{
    public function save(DiscoverySession $session): void;

    public function findById(string $id): ?DiscoverySession;
}
