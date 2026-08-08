<?php

declare(strict_types=1);

namespace App\Identity\Infrastructure;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\HasApiTokens;

/**
 * Eloquent persistence model for the Identity module's User entity
 * (Domain/User.php). Lives here, not app/Models/, because app/Models/ is
 * exactly the "group by technical type" layout docs/delivery/11 overrides
 * (`config/auth.php`'s `providers.users.model` points here).
 *
 * @property string $id
 * @property string $name
 * @property string $email
 * @property string $status
 * @property string $password
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
#[Fillable(['id', 'name', 'email', 'password', 'status'])]
#[Hidden(['password', 'remember_token'])]
class EloquentUser extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, HasUuids, Notifiable;

    protected $table = 'users';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }
}
