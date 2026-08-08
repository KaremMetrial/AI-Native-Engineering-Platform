<?php

declare(strict_types=1);

namespace App\Discovery\Infrastructure;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $tenant_id
 * @property string $project_id
 * @property string $title
 * @property string $status
 * @property string $created_by
 * @property Carbon $created_at
 * @property Carbon|null $completed_at
 */
class EloquentDiscoverySession extends Model
{
    use HasUuids;

    public $timestamps = false;

    protected $table = 'discovery_sessions';

    protected $fillable = ['id', 'tenant_id', 'project_id', 'title', 'status', 'created_by', 'created_at', 'completed_at'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['created_at' => 'datetime', 'completed_at' => 'datetime'];
    }
}
