<?php

declare(strict_types=1);

namespace App\AiOrchestration\Infrastructure;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $model_id
 * @property string $provider
 * @property string $tier
 * @property string $structured_output
 * @property string $tool_use
 * @property string $streaming
 * @property bool $vision
 * @property bool $deterministic_seed
 * @property int $context_window
 * @property int $max_output
 * @property float $input_per_million_tokens_usd
 * @property float $output_per_million_tokens_usd
 * @property float $cached_input_per_million_tokens_usd
 * @property string $status
 * @property Carbon $created_at
 */
class EloquentModelRegistryEntry extends Model
{
    use HasUuids;

    public $timestamps = false;

    protected $table = 'model_registry_entries';

    protected $fillable = [
        'id', 'model_id', 'provider', 'tier', 'structured_output', 'tool_use', 'streaming',
        'vision', 'deterministic_seed', 'context_window', 'max_output',
        'input_per_million_tokens_usd', 'output_per_million_tokens_usd', 'cached_input_per_million_tokens_usd',
        'status', 'created_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'vision' => 'boolean',
            'deterministic_seed' => 'boolean',
            'input_per_million_tokens_usd' => 'float',
            'output_per_million_tokens_usd' => 'float',
            'cached_input_per_million_tokens_usd' => 'float',
            'created_at' => 'datetime',
        ];
    }
}
