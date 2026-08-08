<?php

declare(strict_types=1);

namespace Tests\Integration\Identity;

use App\Identity\Domain\Tenant;
use App\Identity\Infrastructure\EloquentTenantRepository;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Str;
use Tests\TestCase;

class EloquentTenantRepositoryTest extends TestCase
{
    use DatabaseTransactions;

    public function test_save_then_find_round_trips_a_tenant(): void
    {
        $repository = new EloquentTenantRepository;
        $tenant = Tenant::provision(id: (string) Str::uuid(), name: 'Acme', plan: 'trial', region: 'us');

        $repository->save($tenant);
        $found = $repository->findById($tenant->id);

        $this->assertNotNull($found);
        $this->assertSame($tenant->id, $found->id);
        $this->assertSame('Acme', $found->name());
        $this->assertSame('trial', $found->plan);
    }

    public function test_find_by_id_returns_null_when_not_found(): void
    {
        $repository = new EloquentTenantRepository;

        $this->assertNull($repository->findById((string) Str::uuid()));
    }
}
