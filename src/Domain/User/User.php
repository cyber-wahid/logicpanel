<?php

declare(strict_types=1);

namespace LogicPanel\Domain\User;

use Illuminate\Database\Eloquent\Model;

class User extends Model
{
    protected $table = 'users';

    protected $fillable = [
        'username',
        'email',
        'password_hash',
        'role',
        'status',
        'package_id',
        'owner_id',
    ];

    protected $hidden = [
        'password_hash',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function package()
    {
        return $this->belongsTo(\LogicPanel\Domain\Package\Package::class);
    }

    public function services()
    {
        return $this->hasMany(\LogicPanel\Domain\Service\Service::class);
    }

    public function databases()
    {
        return $this->hasMany(\LogicPanel\Domain\Database\Database::class);
    }

    public function apiKeys()
    {
        return $this->hasMany(\LogicPanel\Domain\User\ApiKey::class);
    }

    public function owner()
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function ownedUsers()
    {
        return $this->hasMany(User::class, 'owner_id');
    }

    public function domains()
    {
        return $this->hasMany(\LogicPanel\Domain\Domain\Domain::class);
    }

    public function verifyPassword(string $password): bool
    {
        return password_verify($password, $this->password_hash);
    }

    public function setPassword(string $password): void
    {
        $this->password_hash = password_hash($password, PASSWORD_BCRYPT);
    }

    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    public function isReseller(): bool
    {
        return $this->role === 'reseller';
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }
}
