<?php

declare(strict_types=1);

namespace Tests\Feature\Identity;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

class LogoutTest extends TestCase
{
    use DatabaseTransactions;

    public function test_logout_revokes_the_token_so_it_can_no_longer_be_used(): void
    {
        $register = $this->postJson('/api/register', [
            'tenant_name' => 'Acme',
            'name' => 'Ada Lovelace',
            'email' => 'ada@example.test',
            'password' => 'correct-horse-battery-staple',
        ])->assertStatus(201);

        $token = $register->json('token');

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/logout')
            ->assertStatus(200);

        // Illuminate\Auth\RequestGuard memoizes the resolved user on the
        // guard instance, and AuthManager caches that guard instance by
        // name -- both persist across these simulated in-process requests
        // within one test, unlike real separate HTTP requests. Without
        // this, the second call below would reuse the first call's
        // already-resolved (pre-deletion) user instead of re-validating
        // the now-revoked token against the database.
        Auth::forgetGuards();

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/me')
            ->assertStatus(401);
    }
}
