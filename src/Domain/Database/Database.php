<?php

declare(strict_types=1);

namespace LogicPanel\Domain\Database;

use Illuminate\Database\Eloquent\Model;

class Database extends Model
{
    protected $table = 'databases';

    // Disable updated_at as it's missing in the schema
    const UPDATED_AT = null;

    protected $fillable = [
        'service_id',
        'user_id',
        'db_type',
        'db_name',
        'db_user',
        'db_password',
        'db_host',
        'db_port',
    ];

    protected $hidden = [
        'db_password',
    ];

    protected $casts = [
        'db_port' => 'integer',
        'created_at' => 'datetime',
    ];

    public function service()
    {
        return $this->belongsTo(\LogicPanel\Domain\Service\Service::class);
    }

    public function user()
    {
        return $this->belongsTo(\LogicPanel\Domain\User\User::class);
    }

    public function getConnectionString(): string
    {
        switch ($this->db_type) {
            case 'mysql':
                return "mysql://{$this->db_user}:{$this->db_password}@{$this->db_host}:{$this->db_port}/{$this->db_name}";
            case 'postgresql':
                return "postgresql://{$this->db_user}:{$this->db_password}@{$this->db_host}:{$this->db_port}/{$this->db_name}";
            case 'mongodb':
                return "mongodb://{$this->db_user}:{$this->db_password}@{$this->db_host}:{$this->db_port}/{$this->db_name}";
            default:
                return '';
        }
    }
}
