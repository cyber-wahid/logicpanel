<?php

declare(strict_types=1);

namespace LogicPanel\Domain\Cron;

use Illuminate\Database\Eloquent\Model;
use LogicPanel\Domain\Service\Service;

class CronJob extends Model
{
    protected $table = 'cron_jobs';

    protected $fillable = [
        'service_id',
        'schedule',
        'command',
        'is_active',
        'last_run',
        'last_result'
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'last_run' => 'datetime',
    ];

    public function service()
    {
        return $this->belongsTo(Service::class);
    }
}
