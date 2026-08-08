<?php

declare(strict_types=1);

namespace App\AiOrchestration\Infrastructure;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property string $tenant_id
 * @property list<mixed> $allowed_providers
 * @property Carbon $created_at
 */
class EloquentTenantProviderPolicy extends Model
{
    use HasUuids;

    public $timestamps = false;

    protected $table = 'tenant_provider_policies';

    protected $primaryKey = 'tenant_id';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = ['tenant_id', 'allowed_providers', 'created_at'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'allowed_providers' => 'array',
            'created_at' => 'datetime',
        ];
    }
}
