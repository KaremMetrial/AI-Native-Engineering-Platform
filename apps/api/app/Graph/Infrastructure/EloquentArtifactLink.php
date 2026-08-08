<?php

declare(strict_types=1);

namespace App\Graph\Infrastructure;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $tenant_id
 * @property string $from_version_id
 * @property string $to_version_id
 * @property string $link_type
 * @property string $created_by
 * @property Carbon $created_at
 */
class EloquentArtifactLink extends Model
{
    use HasUuids;

    public $timestamps = false;

    protected $table = 'artifact_links';

    protected $fillable = ['id', 'tenant_id', 'from_version_id', 'to_version_id', 'link_type', 'created_by', 'created_at'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['created_at' => 'datetime'];
    }
}
