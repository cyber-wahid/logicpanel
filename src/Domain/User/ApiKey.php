<?php

declare(strict_types=1);

namespace LogicPanel\Domain\User;

use Illuminate\Database\Eloquent\Model;

class ApiKey extends Model
{
    protected $table = 'api_keys';

    // Disable Eloquent timestamps - DB handles created_at with default
    public $timestamps = false;

    protected $fillable = [
        'user_id',
        'name',
        'api_key',
        'key_hash',
        'permissions',
        'status',
        'last_used_at',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'last_used_at' => 'datetime',
        'permissions' => 'array',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
