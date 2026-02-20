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
        // Try to get public host from server context or environment variable
        $publicHost = $_SERVER['HTTP_HOST'] ?? $_ENV['APP_DOMAIN'] ?? 'localhost';
        
        // Strip out port if we picked up 'domain:port' from HTTP_HOST
        if (strpos($publicHost, ':') !== false) {
            $publicHost = explode(':', $publicHost)[0];
        }

        // If the stored db_host is an internal docker name, use the public host
        $host = (strpos($this->db_host, 'lp_') === 0 || $this->db_host === 'mariadb' || $this->db_host === 'postgres' || $this->db_host === 'mongo') 
            ? $publicHost 
            : $this->db_host;

        switch ($this->db_type) {
            case 'mysql':
                return "mysql://{$this->db_user}:{$this->db_password}@{$host}:{$this->db_port}/{$this->db_name}";
            case 'postgresql':
                return "postgresql://{$this->db_user}:{$this->db_password}@{$host}:{$this->db_port}/{$this->db_name}";
            case 'mongodb':
                // Auth source is typically admin for LogicPanel provisioned MongoDB 
                return "mongodb://{$this->db_user}:{$this->db_password}@{$host}:{$this->db_port}/{$this->db_name}?authSource=admin";
            default:
                return '';
        }
    }
}
