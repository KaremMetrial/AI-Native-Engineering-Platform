<?php

declare(strict_types=1);

namespace App\Graph\Infrastructure;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $tenant_id
 * @property string $project_id
 * @property string $type
 * @property string|null $current_version_id
 * @property string $status
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class EloquentArtifact extends Model
{
    use HasUuids;

    protected $table = 'artifacts';

    protected $fillable = ['id', 'tenant_id', 'project_id', 'type', 'current_version_id', 'status'];
}
