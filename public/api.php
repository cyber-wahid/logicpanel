<?php

declare(strict_types=1);

use DI\Container;
use Slim\Factory\AppFactory;
use Dotenv\Dotenv;
use LogicPanel\Infrastructure\Database\Connection;
use LogicPanel\Application\Services\JwtService;
use LogicPanel\Infrastructure\Docker\DockerService;
use LogicPanel\Infrastructure\Database\DatabaseProvisionerService;
use LogicPanel\Application\Middleware\AuthMiddleware;
use LogicPanel\Application\Middleware\CorsMiddleware;
use LogicPanel\Application\Controllers\AuthController;
use LogicPanel\Application\Controllers\ServiceController;
use LogicPanel\Application\Controllers\DatabaseController;
use LogicPanel\Application\Controllers\FileController;

// Disable error display for production security
ini_set('display_errors', '0');
ini_set('display_startup_errors', '0');
error_reporting(E_ALL & ~E_NOTICE & ~E_DEPRECATED);

// Catch all errors and output as JSON
// Catch all errors and output as JSON after logging to stderr
set_error_handler(function ($errno, $errstr, $errfile, $errline) {
    if (!(error_reporting() & $errno)) {
        // This error code is not included in error_reporting
        return;
    }

    // Log to stderr for Docker
    error_log("PHP Error: [$errno] $errstr in $errfile:$errline");

    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode([
        'error' => 'PHP Error',
        'message' => $errstr,
        'file' => basename($errfile), // Hide full path
        'line' => $errline
    ]);
    exit;
});

// Debug logging to stderr for Docker visibility
$debugInfo = [
    'timestamp' => date('Y-m-d H:i:s'),
    'request_uri' => $_SERVER['REQUEST_URI'] ?? 'unknown',
    'method' => $_SERVER['REQUEST_METHOD'] ?? 'unknown'
];
error_log("API Debug: " . json_encode($debugInfo));

header('X-API-Reached: true');

require __DIR__ . '/../vendor/autoload.php';

// Load environment variables
try {
    $dotenv = Dotenv::createImmutable(__DIR__ . '/..');
    $dotenv->load();
} catch (\Exception $e) {
    // Ignore missing .env
}

// Ensure settings are loaded
$settings = require __DIR__ . '/../config/settings.php';

// Initialize database connection
// Initialize database connection with error handling
try {
    Connection::init($settings['database']);
} catch (\Throwable $e) {
    error_log("Critical Database Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Database Connection Failed', 'message' => $e->getMessage()]);
    exit;
}

// Create Container
$container = new Container();

// Register settings
$container->set('settings', $settings);

// Register logging service first
// Register logging service first
$container->set(\LogicPanel\Application\Services\LoggingService::class, function () use ($settings) {
    $config = $settings['logging'] ?? [];
    $channel = $config['channel'] ?? 'LogicPanel';
    // If channel is 'stderr' or 'stdout', map to php:// streams. 
    // But here we want the path. install.sh sets LOG_CHANNEL=stderr

    $path = $config['path'] ?? 'php://stderr';

    // Override path if channel is specifically stderr/stdout and path wasn't updated
    if (($config['channel'] ?? '') === 'stderr') {
        $path = 'php://stderr';
    }

    $level = $config['level'] ?? 'debug';

    return new \LogicPanel\Application\Services\LoggingService($channel, $path, $level);
});

// Register services
$container->set(\LogicPanel\Application\Services\TokenBlacklistService::class, function ($container) {
    return new \LogicPanel\Application\Services\TokenBlacklistService(
        $container->get(\LogicPanel\Application\Services\LoggingService::class)
    );
});

$container->set(JwtService::class, function ($container) use ($settings) {
    return new JwtService(
        $settings['jwt'],
        $container->get(\LogicPanel\Application\Services\LoggingService::class)
    );
});

$container->set(DockerService::class, function () use ($settings) {
    return new DockerService($settings['docker']);
});

$container->set(DatabaseProvisionerService::class, function () use ($settings) {
    return new DatabaseProvisionerService($settings['db_provisioner']);
});

// Register middleware
$container->set(AuthMiddleware::class, function ($container) {
    return new AuthMiddleware(
        $container->get(JwtService::class),
        $container->get(\LogicPanel\Application\Services\TokenBlacklistService::class),
        $container->get(\LogicPanel\Application\Services\LoggingService::class)
    );
});

$container->set(CorsMiddleware::class, function () {
    return new CorsMiddleware();
});

// Register controllers
$container->set(AuthController::class, function ($container) {
    return new AuthController(
        $container->get(JwtService::class),
        $container->get(\LogicPanel\Application\Services\TokenBlacklistService::class)
    );
});

$container->set(ServiceController::class, function ($container) {
    return new ServiceController($container->get(DockerService::class));
});

$container->set(DatabaseController::class, function ($container) {
    return new DatabaseController($container->get(DatabaseProvisionerService::class));
});

$container->set(FileController::class, function () use ($settings) {
    $config = $settings['file_manager'];
    $config['user_apps_path'] = $settings['docker']['user_apps_path'];
    return new FileController($config);
});

// Master Panel Services
$container->set(\LogicPanel\Application\Services\SystemBridgeService::class, function () {
    return new \LogicPanel\Application\Services\SystemBridgeService();
});

// Master Panel Controllers
$container->set(\LogicPanel\Application\Controllers\Master\AccountController::class, function ($container) {
    return new \LogicPanel\Application\Controllers\Master\AccountController(
        $container->get(\LogicPanel\Application\Services\SystemBridgeService::class),
        $container->get(DockerService::class),
        $container->get(JwtService::class)
    );
});

$container->set(\LogicPanel\Application\Controllers\Master\ServiceController::class, function ($container) {
    return new \LogicPanel\Application\Controllers\Master\ServiceController(
        $container->get(\LogicPanel\Application\Services\SystemBridgeService::class),
        $container->get(DockerService::class)
    );
});

$container->set(\LogicPanel\Application\Controllers\Master\SystemController::class, function ($container) {
    return new \LogicPanel\Application\Controllers\Master\SystemController(
        $container->get(\LogicPanel\Application\Services\SystemBridgeService::class)
    );
});

$container->set(\LogicPanel\Application\Controllers\Master\DomainController::class, function () {
    return new \LogicPanel\Application\Controllers\Master\DomainController();
});

// DI for API Token Middleware and controllers used by API routes (routes_api.php)
$container->set(\LogicPanel\Application\Middleware\ApiTokenMiddleware::class, function () {
    return new \LogicPanel\Application\Middleware\ApiTokenMiddleware();
});

$container->set(\LogicPanel\Application\Controllers\Master\PackageController::class, function () {
    return new \LogicPanel\Application\Controllers\Master\PackageController();
});

$container->set(\LogicPanel\Application\Controllers\Master\DatabaseController::class, function ($container) {
    return new \LogicPanel\Application\Controllers\Master\DatabaseController(
        $container->get(DatabaseProvisionerService::class)
    );
});

$container->set(\LogicPanel\Application\Controllers\Master\ApiKeyController::class, function () {
    return new \LogicPanel\Application\Controllers\Master\ApiKeyController();
});

$container->set(LogicPanel\Application\Controllers\CronController::class, function ($container) {
    return new LogicPanel\Application\Controllers\CronController($container->get(DockerService::class));
});

$container->set(LogicPanel\Application\Controllers\SystemController::class, function ($container) {
    return new LogicPanel\Application\Controllers\SystemController($container->get(DockerService::class));
});

// Set container to create App with on AppFactory
AppFactory::setContainer($container);
$app = AppFactory::create();

// Set base path dynamically to handle both XAMPP and Docker
$scriptPath = dirname(dirname($_SERVER['SCRIPT_NAME'])); // Gets parent of /public
$basePath = ($scriptPath === '/' || $scriptPath === '\\') ? '' : $scriptPath;
$basePath = rtrim($basePath, '/');

// Detect request origin to set correct Slim base path
$requestUri = $_SERVER['REQUEST_URI'] ?? '';
if (strpos($requestUri, '/v1/api/') !== false) {
    // External API request (WHMCS/Blesta): empty base path so Slim sees /v1/api/...
    $app->setBasePath('');
} elseif (strpos($requestUri, '/api/') !== false && strpos($requestUri, '/public/api/') === false) {
    // Request came from /api/, set base path accordingly
    $app->setBasePath($basePath . '/api');
} else {
    // Request came from /public/api/
    $app->setBasePath($basePath . '/public/api');
}

// Register middleware
$app->addRoutingMiddleware();
$app->addBodyParsingMiddleware();

// Error middleware
$app->addErrorMiddleware(
    (bool) ($settings['app']['debug'] ?? false),
    true,
    true
);

// Register routes based on Port / Role
$serverPort = $_SERVER['SERVER_PORT'];
$fwPort = $_SERVER['HTTP_X_FORWARDED_PORT'] ?? null;
$hostPort = null;
if (isset($_SERVER['HTTP_HOST'])) {
    $parsed = parse_url('http://' . $_SERVER['HTTP_HOST']);
    if (is_array($parsed))
        $hostPort = $parsed['port'] ?? null;
}
$effectivePort = $hostPort ?: ($fwPort ?: $serverPort);

try {
    // LogicPanel Dual-Port Routing
    $masterPort = (int) ($_ENV['MASTER_PORT'] ?? 967);
    if ((int) $effectivePort === $masterPort || getenv('APP_MODE') === 'master') {
        // Master Panel Routes
        $routes = require __DIR__ . '/../src/routes_master.php';
    } else {
        // User Panel Routes
        $routes = require __DIR__ . '/../src/routes_user.php';
    }

    $routes($app);

    // Load API routes (token-based, for WHMCS/Blesta modules)
    $apiRoutes = require __DIR__ . '/../src/routes_api.php';
    $apiRoutes($app);

    // Force JSON response for all API calls
    header('Content-Type: application/json');

    // Run app
    $app->run();
} catch (\Throwable $e) {
    // Log error to stderr directly
    error_log("API Fatal Error: " . $e->getMessage() . "\n" . $e->getTraceAsString());

    if (ob_get_level())
        ob_end_clean();

    header('Content-Type: application/json');
    http_response_code(500);
    echo json_encode([
        'error' => 'API Fatal Error',
        'message' => $e->getMessage(),
        'trace' => ($_ENV['APP_DEBUG'] ?? 'false') === 'true' ? $e->getTraceAsString() : null
    ]);
    exit;
}
