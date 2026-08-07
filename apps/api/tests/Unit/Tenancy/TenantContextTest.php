<?php

declare(strict_types=1);

namespace Tests\Unit\Tenancy;

use App\Tenancy\Infrastructure\TenantContext;
use RuntimeException;
use Tests\TestCase;

class TenantContextTest extends TestCase
{
    public function test_current_throws_when_no_tenant_is_bound(): void
    {
        $context = new TenantContext;

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('fail closed');

        $context->current();
    }

    public function test_bound_is_false_before_any_bind_call(): void
    {
        $context = new TenantContext;

        $this->assertFalse($context->bound());
    }
}
