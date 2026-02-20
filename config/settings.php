<?php

return [
    'app' => [
        'name' => $_ENV['APP_NAME'] ?? 'LogicPanel',
        'env' => $_ENV['APP_ENV'] ?? 'production',
        'debug' => filter_var($_ENV['APP_DEBUG'] ?? false, FILTER_VALIDATE_BOOLEAN),
        'url' => $_ENV['APP_URL'] ?? 'http://localhost',
        'timezone' => $_ENV['APP_TIMEZONE'] ?? 'UTC',
    ],

    'database' => [
        'driver' => $_ENV['DB_CONNECTION'] ?? 'mysql',
        'host' => (function () {
            $host = $_ENV['DB_HOST'] ?? '127.0.0.1';
            // If in non-docker environment and host is docker hostname, fallback to localhost
            if ($host === 'logicpanel-db' && strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
                return '127.0.0.1';
            }
            return $host;
        })(),
        'port' => $_ENV['DB_PORT'] ?? 3306,
        'database' => $_ENV['DB_DATABASE'] ?? 'logicpanel',
        'username' => $_ENV['DB_USERNAME'] ?? 'root',
        'password' => $_ENV['DB_PASSWORD'] ?? '',
        'charset' => 'utf8mb4',
        'collation' => 'utf8mb4_unicode_ci',
        'prefix' => '',
    ],

    'encryption' => [
        'key' => $_ENV['ENCRYPTION_KEY'] ?? '',
    ],

    'jwt' => [
        'secret' => $_ENV['JWT_SECRET'] ?? '',
        'expiry' => (int) ($_ENV['JWT_EXPIRY'] ?? 3600),
        'refresh_expiry' => (int) ($_ENV['JWT_REFRESH_EXPIRY'] ?? 604800),
    ],

    'docker' => [
        'socket' => $_ENV['DOCKER_SOCKET'] ?? '/var/run/docker.sock',
        'network' => $_ENV['DOCKER_NETWORK'] ?? 'logicpanel_network',
        'user_apps_path' => $_ENV['USER_APPS_PATH'] ?? __DIR__ . '/../storage/user-apps',
        'user_apps_volume' => $_ENV['USER_APPS_VOLUME'] ?? 'logicpanel_logicpanel_user_apps',
    ],

    'db_provisioner' => [
        'url' => $_ENV['DB_PROVISIONER_URL'] ?? 'http://db-provisioner:3001',
        'secret' => $_ENV['DB_PROVISIONER_SECRET'] ?? '',
    ],

    'databases' => [
        'mysql' => [
            'host' => $_ENV['MYSQL_INTERNAL_HOST'] ?? 'mysql',
            'port' => (int) ($_ENV['MYSQL_INTERNAL_PORT'] ?? 3306),
        ],
        'postgresql' => [
            'host' => $_ENV['POSTGRES_INTERNAL_HOST'] ?? 'postgres',
            'port' => (int) ($_ENV['POSTGRES_INTERNAL_PORT'] ?? 5432),
        ],
        'mongodb' => [
            'host' => $_ENV['MONGO_INTERNAL_HOST'] ?? 'mongo',
            'port' => (int) ($_ENV['MONGO_INTERNAL_PORT'] ?? 27017),
        ],
    ],

    'redis' => [
        'host' => $_ENV['REDIS_HOST'] ?? '127.0.0.1',
        'port' => (int) ($_ENV['REDIS_PORT'] ?? 6379),
        'password' => $_ENV['REDIS_PASSWORD'] ?? null,
    ],

    'file_manager' => [
        'max_size' => (int) ($_ENV['FILE_MANAGER_MAX_SIZE'] ?? 10485760), // 10MB
        'allowed_extensions' => explode(',', $_ENV['FILE_MANAGER_ALLOWED_EXTENSIONS'] ?? 'js,py,json,txt,md,yml,yaml,env,html,css,zip,png,jpg,jpeg,xml,sql,tar,gz'),
    ],

    'rate_limit' => [
        'enabled' => filter_var($_ENV['RATE_LIMIT_ENABLED'] ?? true, FILTER_VALIDATE_BOOLEAN),
        'requests' => (int) ($_ENV['RATE_LIMIT_REQUESTS'] ?? 100),
        'window' => (int) ($_ENV['RATE_LIMIT_WINDOW'] ?? 60),
    ],

    'logging' => [
        'channel' => $_ENV['LOG_CHANNEL'] ?? 'stderr',
        'level' => $_ENV['LOG_LEVEL'] ?? 'debug',
        'path' => $_ENV['LOG_PATH'] ?? __DIR__ . '/../storage/logs',
    ],

    'resources' => [
        'default_cpu_limit' => (float) ($_ENV['DEFAULT_CPU_LIMIT'] ?? 0.5),
        'default_memory_limit' => $_ENV['DEFAULT_MEMORY_LIMIT'] ?? '512M',
        'default_disk_limit' => $_ENV['DEFAULT_DISK_LIMIT'] ?? '1G',
    ],

    'whmcs' => [
        'api_url' => $_ENV['WHMCS_API_URL'] ?? '',
        'api_key' => $_ENV['WHMCS_API_KEY'] ?? '',
        'api_secret' => $_ENV['WHMCS_API_SECRET'] ?? '',
    ],
];
