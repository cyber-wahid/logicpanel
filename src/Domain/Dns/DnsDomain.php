<?php

declare(strict_types=1);

namespace LogicPanel\Domain\Dns;

use Illuminate\Database\Eloquent\Model;

class DnsDomain extends Model
{
    protected $table = 'dns_domains';

    protected $fillable = [
        'user_id',
        'domain_name',
        'status',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(\LogicPanel\Domain\User\User::class);
    }

    public function records()
    {
        return $this->hasMany(DnsRecord::class, 'domain_id');
    }
}
