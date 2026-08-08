<?php

declare(strict_types=1);

namespace App\Discovery\Infrastructure;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $tenant_id
 * @property string $session_id
 * @property string $statement
 * @property string $created_by
 * @property Carbon $created_at
 */
class EloquentConstraint extends Model
{
    use HasUuids;

    public $timestamps = false;

    protected $table = 'discovery_constraints';

    protected $fillable = ['id', 'tenant_id', 'session_id', 'statement', 'created_by', 'created_at'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['created_at' => 'datetime'];
    }
}
