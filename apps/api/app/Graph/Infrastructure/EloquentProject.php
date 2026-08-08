<?php

declare(strict_types=1);

namespace App\Graph\Infrastructure;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $tenant_id
 * @property string $name
 * @property string $status
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class EloquentProject extends Model
{
    use HasUuids;

    protected $table = 'projects';

    protected $fillable = ['id', 'tenant_id', 'name', 'status'];
}
