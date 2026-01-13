<?php

declare(strict_types=1);

namespace FormaFlow\Users\Infrastructure\Persistence\Eloquent;

use Database\factories\UserModelFactory;
use Illuminate\Auth\Authenticatable;
use Illuminate\Contracts\Auth\Access\Authorizable as AuthorizableContract;
use Illuminate\Contracts\Auth\Authenticatable as AuthenticatableContract;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

final class UserModel extends Model implements AuthenticatableContract, AuthorizableContract
{
    use HasFactory;
    use HasApiTokens;
    use Notifiable;
    use Authenticatable;

    protected $table = 'users';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'name',
        'email',
        'password',
        'timezone',
        'email_verified_at',
        'remember_token',
    ];

    protected $hidden = [
        'password',
        'remember_token'
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
    ];

    public function can($abilities, $arguments = []): bool
    {
        return true;
    }

    public static function newFactory(): UserModelFactory
    {
        return UserModelFactory::new();
    }
}
