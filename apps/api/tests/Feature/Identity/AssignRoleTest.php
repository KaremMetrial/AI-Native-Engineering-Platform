<?php

declare(strict_types=1);

namespace Tests\Feature\Identity;

use App\Identity\Domain\Membership;
use App\Identity\Domain\Role;
use App\Identity\Domain\User;
use App\Identity\Infrastructure\EloquentMembershipRepository;
use App\Identity\Infrastructure\EloquentUserRepository;
use App\Tenancy\Infrastructure\TenantContext;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Str;
use Tests\TestCase;

class AssignRoleTest extends TestCase
{
    use DatabaseTransactions;

    public function test_owner_can_promote_a_contributor_to_admin(): void
    {
        $register = $this->postJson('/api/register', [
            'tenant_name' => 'Acme',
            'name' => 'Ada Lovelace',
            'email' => 'ada@example.test',
            'password' => 'correct-horse-battery-staple',
        ])->assertStatus(201);

        $ownerToken = $register->json('token');
        $tenantId = $register->json('tenant.id');

        $memberId = $this->addMember($tenantId, Role::Contributor);

        $response = $this->withHeader('Authorization', "Bearer {$ownerToken}")
            ->patchJson("/api/members/{$memberId}/role", ['role' => 'admin']);

        $response->assertStatus(200);

        // BindTenantContext clears the tenant once the request completes
        // (correctly -- a pooled connection must never carry it into the
        // next request), so this assertion needs it rebound: memberships
        // is RLS-protected and an unbound query sees zero rows, not "the
        // data is missing."
        $this->app->make(TenantContext::class)->bind($tenantId);
        $this->assertDatabaseHas('memberships', [
            'tenant_id' => $tenantId,
            'user_id' => $memberId,
            'role' => 'admin',
        ]);
    }

    public function test_a_contributor_cannot_promote_another_member(): void
    {
        $register = $this->postJson('/api/register', [
            'tenant_name' => 'Acme',
            'name' => 'Ada Lovelace',
            'email' => 'ada@example.test',
            'password' => 'correct-horse-battery-staple',
        ])->assertStatus(201);

        $tenantId = $register->json('tenant.id');

        $contributorId = $this->addMember($tenantId, Role::Contributor, email: 'contributor@example.test');
        $contributorToken = $this->tokenFor('contributor@example.test');

        $viewerId = $this->addMember($tenantId, Role::Viewer, email: 'viewer@example.test');

        $response = $this->withHeader('Authorization', "Bearer {$contributorToken}")
            ->patchJson("/api/members/{$viewerId}/role", ['role' => 'admin']);

        $response->assertStatus(403);
    }

    private function addMember(string $tenantId, Role $role, string $email = 'member@example.test'): string
    {
        $userRepository = new EloquentUserRepository;
        $user = User::register(id: (string) Str::uuid(), name: 'Member', email: $email, hashedPassword: bcrypt('password'));
        $userRepository->save($user);

        $tenantContext = $this->app->make(TenantContext::class);
        $tenantContext->bind($tenantId);
        (new EloquentMembershipRepository)->save(
            Membership::create(id: (string) Str::uuid(), tenantId: $tenantId, userId: $user->id, role: $role),
        );
        $tenantContext->clear();

        return $user->id;
    }

    private function tokenFor(string $email): string
    {
        $response = $this->postJson('/api/login', ['email' => $email, 'password' => 'password']);
        $token = $response->json('token');
        $this->assertIsString($token);

        return $token;
    }
}
