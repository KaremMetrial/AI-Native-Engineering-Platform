<?php

declare(strict_types=1);

namespace App\AiOrchestration\Infrastructure;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $tenant_id
 * @property string $workflow_name
 * @property array<string, mixed> $capability_requirement
 * @property string $requested_by
 * @property string $status
 * @property string|null $selected_model_id
 * @property string|null $failure_reason
 * @property Carbon $created_at
 */
class EloquentGenerationRecord extends Model
{
    use HasUuids;

    public $timestamps = false;

    protected $table = 'generation_records';

    protected $fillable = [
        'id', 'tenant_id', 'workflow_name', 'capability_requirement', 'requested_by',
        'status', 'selected_model_id', 'failure_reason', 'created_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'capability_requirement' => 'array',
            'created_at' => 'datetime',
        ];
    }
}
