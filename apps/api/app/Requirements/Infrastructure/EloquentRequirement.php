<?php

declare(strict_types=1);

namespace App\Requirements\Infrastructure;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $tenant_id
 * @property string $document_id
 * @property string $text
 * @property list<mixed> $acceptance_criteria
 * @property string $status
 * @property string $created_by
 * @property Carbon $created_at
 */
class EloquentRequirement extends Model
{
    use HasUuids;

    public $timestamps = false;

    protected $table = 'requirements';

    protected $fillable = ['id', 'tenant_id', 'document_id', 'text', 'acceptance_criteria', 'status', 'created_by', 'created_at'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'acceptance_criteria' => 'array',
            'created_at' => 'datetime',
        ];
    }
}
