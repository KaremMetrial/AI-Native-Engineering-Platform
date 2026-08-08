<?php

declare(strict_types=1);

namespace App\Identity\Infrastructure;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Eloquent persistence model for the Identity module's Membership entity
 * (Domain/Membership.php). `memberships` is tenant-scoped and RLS-enforced
 * (database/migrations/*_identity_create_memberships_table.php) -- the
 * first real tenant-scoped table in the platform, replacing the Phase 0
 * RLS proof of concept.
 *
 * @property string $id
 * @property string $tenant_id
 * @property string $user_id
 * @property string $role
 * @property string $status
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class EloquentMembership extends Model
{
    use HasUuids;

    protected $table = 'memberships';

    protected $fillable = ['id', 'tenant_id', 'user_id', 'role', 'status'];

    /**
     * @return BelongsTo<EloquentTenant, $this>
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(EloquentTenant::class, 'tenant_id');
    }

    /**
     * @return BelongsTo<EloquentUser, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(EloquentUser::class, 'user_id');
    }
}
