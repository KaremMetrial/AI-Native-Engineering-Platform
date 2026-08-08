<?php

declare(strict_types=1);

namespace App\Requirements\Infrastructure;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $tenant_id
 * @property string $project_id
 * @property string $type
 * @property string $title
 * @property string $status
 * @property string $created_by
 * @property Carbon $created_at
 */
class EloquentRequirementDocument extends Model
{
    use HasUuids;

    public $timestamps = false;

    protected $table = 'requirement_documents';

    protected $fillable = ['id', 'tenant_id', 'project_id', 'type', 'title', 'status', 'created_by', 'created_at'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['created_at' => 'datetime'];
    }
}
