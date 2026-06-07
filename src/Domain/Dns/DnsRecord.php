<?php

declare(strict_types=1);

namespace LogicPanel\Domain\Dns;

use Illuminate\Database\Eloquent\Model;

class DnsRecord extends Model
{
    protected $table = 'dns_records';

    protected $fillable = [
        'domain_id',
        'type',
        'name',
        'content',
        'ttl',
        'prio',
    ];

    protected $casts = [
        'ttl' => 'integer',
        'prio' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function domain()
    {
        return $this->belongsTo(DnsDomain::class, 'domain_id');
    }
}
