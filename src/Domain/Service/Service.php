<?php

declare(strict_types=1);

namespace LogicPanel\Domain\Service;

use Illuminate\Database\Eloquent\Model;

class Service extends Model
{
    protected $table = 'services';

    protected $fillable = [
        'user_id',
        'name',
        'domain',
        'type',
        'status',
        'container_id',
        'port',
        'env_vars',
        'cpu_limit',
        'memory_limit',
        'disk_limit',
        'runtime_version',
        'install_command',
        'build_command',
        'start_command',
    ];

    protected $casts = [
        'env_vars' => 'array',
        'cpu_limit' => 'float',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(\LogicPanel\Domain\User\User::class);
    }

    public function databases()
    {
        return $this->hasMany(\LogicPanel\Domain\Database\Database::class);
    }

    public function isRunning(): bool
    {
        return $this->status === 'running';
    }

    public function isStopped(): bool
    {
        return $this->status === 'stopped';
    }

    public function isNodeJS(): bool
    {
        return $this->type === 'nodejs';
    }

    public function isPython(): bool
    {
        return $this->type === 'python';
    }
}
