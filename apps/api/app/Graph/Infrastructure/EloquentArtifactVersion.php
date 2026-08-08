<?php

declare(strict_types=1);

namespace App\Graph\Infrastructure;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $tenant_id
 * @property string $artifact_id
 * @property int $version_number
 * @property string $content
 * @property string|null $lineage_model
 * @property string|null $lineage_prompt_version
 * @property list<string> $lineage_input_version_ids
 * @property int|null $lineage_tokens
 * @property float|null $lineage_cost
 * @property string $created_by
 * @property Carbon $created_at
 */
class EloquentArtifactVersion extends Model
{
    use HasUuids;

    public $timestamps = false;

    protected $table = 'artifact_versions';

    protected $fillable = [
        'id', 'tenant_id', 'artifact_id', 'version_number', 'content',
        'lineage_model', 'lineage_prompt_version', 'lineage_input_version_ids',
        'lineage_tokens', 'lineage_cost', 'created_by', 'created_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'lineage_input_version_ids' => 'array',
            'created_at' => 'datetime',
        ];
    }
}
