<?php

declare(strict_types=1);

namespace LogicPanel\Domain\Package;

use Illuminate\Database\Eloquent\Model;

class Package extends Model
{
    protected $table = 'packages';

    protected $fillable = [
        'name',
        'description',
        'type', // user, reseller
        'created_by', // Reseller ID who created this package
        'is_global', // 1=Global (admin), 0=Reseller-specific
        'cpu_limit', // Cores (0.5, 1.0, etc.)
        'memory_limit', // Integer (MB)
        'storage_limit', // Integer (MB)
        'bandwidth_limit', // Integer (MB)
        'db_limit',
        'max_subdomains',
        'max_addon_domains',
        'limit_users',
        'limit_disk_total',
        'limit_bandwidth_total',
        'max_services',
        'max_databases',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function creator()
    {
        return $this->belongsTo(\LogicPanel\Domain\User\User::class, 'created_by');
    }

    public function users()
    {
        return $this->hasMany(\LogicPanel\Domain\User\User::class);
    }
}