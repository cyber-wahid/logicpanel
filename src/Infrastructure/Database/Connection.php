<?php

declare(strict_types=1);

namespace LogicPanel\Infrastructure\Database;

use Illuminate\Database\Capsule\Manager as Capsule;

class Connection
{
    private static ?Capsule $capsule = null;

    public static function init(array $config): void
    {
        if (self::$capsule !== null) {
            return;
        }

        self::$capsule = new Capsule();

        self::$capsule->addConnection([
            'driver' => $config['driver'],
            'host' => $config['host'],
            'port' => $config['port'],
            'database' => $config['database'],
            'username' => $config['username'],
            'password' => $config['password'],
            'charset' => $config['charset'],
            'collation' => $config['collation'],
            'prefix' => $config['prefix'],
        ]);

        self::$capsule->setAsGlobal();
        self::$capsule->bootEloquent();
    }

    public static function getCapsule(): Capsule
    {
        if (self::$capsule === null) {
            throw new \RuntimeException('Database connection not initialized');
        }

        return self::$capsule;
    }

    public static function getConnection()
    {
        return self::getCapsule()->getConnection();
    }
}
