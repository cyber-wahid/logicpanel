<?php
/**
 * Health Check Endpoint
 * Used by Traefik to check if the application is running
 */

// Simple health check - return 200 if PHP is working
header('Content-Type: application/json');
header('Cache-Control: no-store, no-cache, must-revalidate');

$health = [
    'status' => 'healthy',
    'timestamp' => date('c'),
    'service' => 'logicpanel-app'
];

// Optional: Check database connection
try {
    // Quick DB check if settings are available
    if (file_exists(__DIR__ . '/../vendor/autoload.php')) {
        require_once __DIR__ . '/../vendor/autoload.php';

        // Use environment variables with defaults, ensuring they're properly sanitized
        $host = $_ENV['DB_HOST'] ?? 'logicpanel-db';
        $db = $_ENV['DB_DATABASE'] ?? 'logicpanel';
        $user = $_ENV['DB_USERNAME'] ?? 'logicpanel';
        $pass = $_ENV['DB_PASSWORD'] ?? '';

        // Validate host and database names to prevent injection
        if (!preg_match('/^[a-zA-Z0-9_.-]+$/', $host) || !preg_match('/^[a-zA-Z0-9_-]+$/', $db)) {
            throw new Exception('Invalid database configuration');
        }

        $pdo = new PDO("mysql:host=$host;dbname=$db", $user, $pass, [
            PDO::ATTR_TIMEOUT => 2,
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
        ]);
        $health['database'] = 'connected';
    }
} catch (Exception $e) {
    $health['database'] = 'error';
    // $health['database_error'] = $e->getMessage(); // Hidden for security
    // Don't fail health check for DB issues - let the circuit breaker handle it
}

http_response_code(200);
echo json_encode($health, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
