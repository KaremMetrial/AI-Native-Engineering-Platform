<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Graph\Domain\Project;
use App\Graph\Infrastructure\EloquentProjectRepository;
use App\Identity\Domain\Tenant;
use App\Identity\Domain\User;
use App\Identity\Infrastructure\EloquentTenantRepository;
use App\Identity\Infrastructure\EloquentUserRepository;
use Illuminate\Support\Str;

/**
 * Shared fixture builders for Graph module tests. A tenant, a user and a
 * project must all exist before an Artifact can (project_id and
 * created_by are real foreign keys) -- this is that setup, written once.
 */
trait CreatesGraphFixtures
{
    private function createTenant(): string
    {
        $tenant = Tenant::provision((string) Str::uuid(), 'Acme', 'trial', 'us');
        (new EloquentTenantRepository)->save($tenant);

        return $tenant->id;
    }

    private function createUser(): string
    {
        $user = User::register((string) Str::uuid(), 'Ada', Str::uuid().'@example.test', 'hashed');
        (new EloquentUserRepository)->save($user);

        return $user->id;
    }

    private function createProject(string $tenantId): string
    {
        $project = Project::create((string) Str::uuid(), $tenantId, 'Acme Website');
        (new EloquentProjectRepository)->save($project);

        return $project->id;
    }
}
