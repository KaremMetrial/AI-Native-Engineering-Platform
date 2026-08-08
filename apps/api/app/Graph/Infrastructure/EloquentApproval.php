<?php

declare(strict_types=1);

namespace App\Graph\Infrastructure;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $tenant_id
 * @property string $artifact_version_id
 * @property string $approved_by
 * @property string $decision
 * @property string|null $comment
 * @property Carbon $created_at
 */
class EloquentApproval extends Model
{
    use HasUuids;

    public $timestamps = false;

    protected $table = 'approvals';

    protected $fillable = ['id', 'tenant_id', 'artifact_version_id', 'approved_by', 'decision', 'comment', 'created_at'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['created_at' => 'datetime'];
    }
}
