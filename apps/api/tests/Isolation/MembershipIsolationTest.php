<?php

declare(strict_types=1);

namespace Tests\Isolation;

use App\Identity\Domain\Membership;
use App\Identity\Domain\Role;
use App\Identity\Domain\Tenant;
use App\Identity\Domain\User;
use App\Identity\Infrastructure\EloquentMembershipRepository;
use App\Identity\Infrastructure\EloquentTenantRepository;
use App\Identity\Infrastructure\EloquentUserRepository;
use App\Tenancy\Infrastructure\TenantContext;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * The real cross-tenant isolation proof for `memberships`, replacing the
 * Phase 0 RLS proof of concept (deleted alongside rls_poc_scoped_items --
 * see database/migrations/2026_08_07_200002_identity_create_memberships_table.php).
 * Structurally the same suite of proofs, run against real production
 * data now instead of a throwaway table.
 */
class MembershipIsolationTest extends TestCase
{
    use DatabaseTransactions;

    private TenantContext $tenantContext;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tenantContext = $this->app->make(TenantContext::class);
    }

    protected function tearDown(): void
    {
        $this->tenantContext->clear();
        DB::statement('SET row_security = on');
        parent::tearDown();
    }

    private function createTenant(): string
    {
        $tenant = Tenant::provision(id: (string) Str::uuid(), name: 'T', plan: 'trial', region: 'us');
        (new EloquentTenantRepository)->save($tenant);

        return $tenant->id;
    }

    private function createUser(): string
    {
        $user = User::register(id: (string) Str::uuid(), name: 'U', email: Str::uuid().'@example.test', hashedPassword: 'hashed');
        (new EloquentUserRepository)->save($user);

        return $user->id;
    }

    public function test_a_tenant_only_sees_its_own_memberships(): void
    {
        $tenantA = $this->createTenant();
        $tenantB = $this->createTenant();
        $repository = new EloquentMembershipRepository;

        $this->tenantContext->bind($tenantA);
        $repository->save(Membership::create(id: (string) Str::uuid(), tenantId: $tenantA, userId: $this->createUser(), role: Role::Owner));

        $this->tenantContext->bind($tenantB);
        $repository->save(Membership::create(id: (string) Str::uuid(), tenantId: $tenantB, userId: $this->createUser(), role: Role::Owner));

        $this->tenantContext->bind($tenantA);
        $rows = DB::table('memberships')->get();

        $this->assertCount(1, $rows);
        $this->assertSame($tenantA, $rows->first()->tenant_id);
    }

    public function test_a_tenant_cannot_write_a_membership_into_another_tenant(): void
    {
        $tenantA = $this->createTenant();
        $tenantB = $this->createTenant();
        $userId = $this->createUser();

        $this->tenantContext->bind($tenantA);

        $this->expectException(QueryException::class);

        DB::transaction(function () use ($tenantB, $userId): void {
            DB::table('memberships')->insert([
                'id' => (string) Str::uuid(),
                'tenant_id' => $tenantB,
                'user_id' => $userId,
                'role' => 'owner',
                'status' => 'active',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        });
    }

    public function test_direct_id_access_to_another_tenants_membership_returns_nothing(): void
    {
        $tenantA = $this->createTenant();
        $tenantB = $this->createTenant();
        $repository = new EloquentMembershipRepository;

        $this->tenantContext->bind($tenantA);
        $membershipId = (string) Str::uuid();
        $repository->save(Membership::create(id: $membershipId, tenantId: $tenantA, userId: $this->createUser(), role: Role::Owner));

        $this->tenantContext->bind($tenantB);
        $row = DB::table('memberships')->where('id', $membershipId)->first();

        $this->assertNull($row);
    }

    public function test_missing_tenant_context_returns_zero_rows_not_another_tenants_data(): void
    {
        $tenantA = $this->createTenant();
        $repository = new EloquentMembershipRepository;

        $this->tenantContext->bind($tenantA);
        $repository->save(Membership::create(id: (string) Str::uuid(), tenantId: $tenantA, userId: $this->createUser(), role: Role::Owner));
        $this->tenantContext->clear();

        $rows = DB::table('memberships')->get();

        $this->assertCount(0, $rows);
    }

    public function test_row_security_off_bypass_attempt_is_blocked(): void
    {
        $tenantA = $this->createTenant();
        $this->tenantContext->bind($tenantA);
        (new EloquentMembershipRepository)->save(
            Membership::create(id: (string) Str::uuid(), tenantId: $tenantA, userId: $this->createUser(), role: Role::Owner),
        );

        DB::statement('SET row_security = off');

        $this->expectException(QueryException::class);

        DB::transaction(function (): void {
            DB::table('memberships')->get();
        });
    }

    public function test_runtime_role_cannot_disable_row_level_security(): void
    {
        $this->expectException(QueryException::class);

        DB::transaction(function (): void {
            DB::statement('ALTER TABLE memberships DISABLE ROW LEVEL SECURITY');
        });
    }

    public function test_runtime_role_has_neither_bypassrls_nor_table_ownership(): void
    {
        $role = DB::selectOne('SELECT rolbypassrls, rolsuper FROM pg_roles WHERE rolname = current_user');

        $this->assertFalse((bool) $role->rolbypassrls, 'platform_app must not have BYPASSRLS (D-55)');
        $this->assertFalse((bool) $role->rolsuper, 'platform_app must not be a superuser (D-55)');

        $owner = DB::selectOne("SELECT tableowner FROM pg_tables WHERE tablename = 'memberships'");

        $this->assertNotSame('platform_app', $owner->tableowner, 'platform_app must not own the table (D-55)');
    }

    public function test_a_user_can_see_their_own_memberships_across_tenants_without_a_tenant_bound(): void
    {
        $userId = $this->createUser();
        $tenantA = $this->createTenant();
        $tenantB = $this->createTenant();
        $repository = new EloquentMembershipRepository;

        $this->tenantContext->bind($tenantA);
        $repository->save(Membership::create(id: (string) Str::uuid(), tenantId: $tenantA, userId: $userId, role: Role::Owner));

        $this->tenantContext->bind($tenantB);
        $repository->save(Membership::create(id: (string) Str::uuid(), tenantId: $tenantB, userId: $userId, role: Role::Viewer));

        $this->tenantContext->clear();
        $this->tenantContext->bindActingUser($userId);

        $this->assertCount(2, $repository->findByUser($userId));
    }

    public function test_the_self_membership_carveout_does_not_expose_other_users_memberships(): void
    {
        $tenantA = $this->createTenant();
        $userA = $this->createUser();
        $userB = $this->createUser();
        $repository = new EloquentMembershipRepository;

        $this->tenantContext->bind($tenantA);
        $repository->save(Membership::create(id: (string) Str::uuid(), tenantId: $tenantA, userId: $userA, role: Role::Owner));
        $repository->save(Membership::create(id: (string) Str::uuid(), tenantId: $tenantA, userId: $userB, role: Role::Viewer));

        $this->tenantContext->clear();
        $this->tenantContext->bindActingUser($userA);

        $rows = DB::table('memberships')->get();

        $this->assertCount(1, $rows);
        $this->assertSame($userA, $rows->first()->user_id);
    }
}
