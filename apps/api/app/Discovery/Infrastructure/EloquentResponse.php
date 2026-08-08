<?php

declare(strict_types=1);

namespace App\Discovery\Infrastructure;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $tenant_id
 * @property string $question_id
 * @property string $content
 * @property string $responded_by
 * @property Carbon $created_at
 */
class EloquentResponse extends Model
{
    use HasUuids;

    public $timestamps = false;

    protected $table = 'discovery_responses';

    protected $fillable = ['id', 'tenant_id', 'question_id', 'content', 'responded_by', 'created_at'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['created_at' => 'datetime'];
    }
}
