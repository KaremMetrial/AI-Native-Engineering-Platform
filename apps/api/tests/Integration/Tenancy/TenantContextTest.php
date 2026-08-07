<?php

declare(strict_types=1);

namespace Tests\Integration\Tenancy;

use App\Tenancy\Infrastructure\TenantContext;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class TenantContextTest extends TestCase
{
    use DatabaseTransactions;

    public function test_bind_sets_the_postgres_session_variable(): void
    {
        $tenantId = (string) Str::uuid();
        $context = new TenantContext;

        $context->bind($tenantId);

        $this->assertSame($tenantId, DB::selectOne("SELECT current_setting('app.tenant_id', true) AS value")->value);
        $this->assertSame($tenantId, $context->current());
    }

    public function test_clear_resets_the_postgres_session_variable(): void
    {
        $context = new TenantContext;
        $context->bind((string) Str::uuid());

        $context->clear();

        $value = DB::selectOne("SELECT current_setting('app.tenant_id', true) AS value")->value;
        $this->assertTrue($value === null || $value === '');
        $this->assertFalse($context->bound());
    }
}
