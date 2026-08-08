<?php

declare(strict_types=1);

namespace App\Identity\Infrastructure;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Eloquent persistence model for the Identity module's Tenant entity
 * (Domain/Tenant.php). `tenants` itself carries no `tenant_id` and no RLS
 * policy -- it is the isolation root, not a tenant-scoped table.
 *
 * @property string $id
 * @property string $name
 * @property string $status
 * @property string $plan
 * @property string $region
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class EloquentTenant extends Model
{
    use HasUuids;

    protected $table = 'tenants';

    protected $fillable = ['id', 'name', 'status', 'plan', 'region'];
}
