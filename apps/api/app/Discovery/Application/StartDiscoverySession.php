<?php

declare(strict_types=1);

namespace App\Discovery\Application;

use App\Discovery\Domain\DiscoverySession;
use App\Discovery\Domain\DiscoverySessionRepository;
use App\Graph\Application\FindProject;
use Illuminate\Support\Str;
use RuntimeException;

final class StartDiscoverySession
{
    public function __construct(
        private readonly FindProject $findProject,
        private readonly DiscoverySessionRepository $sessions,
    ) {}

    public function handle(string $tenantId, string $projectId, string $title, string $createdBy): DiscoverySession
    {
        if ($this->findProject->handle($projectId) === null) {
            throw new RuntimeException('Project not found in this tenant.');
        }

        $session = DiscoverySession::start(
            id: (string) Str::uuid(),
            tenantId: $tenantId,
            projectId: $projectId,
            title: $title,
            createdBy: $createdBy,
        );

        $this->sessions->save($session);

        return $session;
    }
}
