<?php
define('LP_MAIN_ENTRY', true);
// Load Composer Autoloader
require __DIR__ . '/vendor/autoload.php';

// Load environment variables
try {
    $dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
    $dotenv->load();
} catch (\Exception $e) {
    // Ignore missing .env
}

// Global Error & Exception Handling
error_reporting(E_ALL);
ini_set('display_errors', ($_ENV['APP_DEBUG'] ?? 'false') === 'true' ? '1' : '0');

set_error_handler(function ($severity, $message, $file, $line) {
    if (!(error_reporting() & $severity)) {
        return;
    }
    throw new ErrorException($message, 0, $severity, $file, $line);
});

set_exception_handler(function ($e) {
    // Log the error
    error_log("Uncaught Exception: " . $e->getMessage() . " in " . $e->getFile() . ":" . $e->getLine() . "\nStack trace:\n" . $e->getTraceAsString());

    // If API request, return JSON
    $isApi = strpos($_SERVER['REQUEST_URI'] ?? '', '/api') !== false || strpos($_SERVER['REQUEST_URI'] ?? '', '/public/api') !== false;
    
    if ($isApi) {
        header('Content-Type: application/json');
        http_response_code(500);
        echo json_encode([
            'error' => 'Internal Server Error',
            'message' => ($_ENV['APP_DEBUG'] ?? 'false') === 'true' ? $e->getMessage() : 'An unexpected error occurred.'
        ]);
    } else {
        // User-facing error page
        http_response_code(500);
        if (($_ENV['APP_DEBUG'] ?? 'false') === 'true') {
            echo "<h1>Internal Server Error</h1>";
            echo "<p><strong>Error:</strong> " . htmlspecialchars($e->getMessage()) . "</p>";
            echo "<p><strong>File:</strong> " . htmlspecialchars($e->getFile()) . " (" . $e->getLine() . ")</p>";
            echo "<pre>" . htmlspecialchars($e->getTraceAsString()) . "</pre>";
        } else {
            // Check if we have a custom error template
            $errorTemplate = __DIR__ . '/templates/shared/errors/500.php';
            if (file_exists($errorTemplate)) {
                include $errorTemplate;
            } else {
                echo "<h1>500 Internal Server Error</h1>";
                echo "<p>Something went wrong on our end. Please try again later.</p>";
            }
        }
    }
    exit;
});

// Traefik sends X-Forwarded-Port and X-Forwarded-Proto headers
$tempServerPort = $_SERVER['SERVER_PORT'] ?? 0;
$tempFwPort = $_SERVER['HTTP_X_FORWARDED_PORT'] ?? null;
$tempHostPort = null;
if (isset($_SERVER['HTTP_HOST'])) {
    $parsed = parse_url('http://' . $_SERVER['HTTP_HOST']);
    if (is_array($parsed)) {
        $tempHostPort = $parsed['port'] ?? null;
    }
}
// Priority: X-Forwarded-Port (Traefik) > Host header port > Server port
$tempEffectivePort = (int) ($tempFwPort ?: ($tempHostPort ?: $tempServerPort));
$masterPort = (int) ($_ENV['MASTER_PORT'] ?? 9999);
$userPort = (int) ($_ENV['USER_PORT'] ?? 7777);

// Detect if HTTPS (Traefik sets X-Forwarded-Proto)
$isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
    || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');

// Auto-redirect to user panel if accessing without port (port 80/443)
// Skip for API requests and internal calls
$isApiRequest = strpos($_SERVER['REQUEST_URI'] ?? '', '/api') !== false || strpos($_SERVER['REQUEST_URI'] ?? '', '/public/api') !== false;
$isInternalCall = defined('LP_PUBLIC_ENTRY') || ($_SERVER['REMOTE_ADDR'] ?? '') === '127.0.0.1';


if (!$isApiRequest && !$isInternalCall && in_array($tempEffectivePort, [80, 443, 0])) {
    $protocol = $isHttps ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';

    // Remove any existing port from host
    $host = preg_replace('/:\d+$/', '', $host);
    $redirectUrl = "{$protocol}://{$host}:{$userPort}" . ($_SERVER['REQUEST_URI'] ?? '/');
    header("Location: {$redirectUrl}", true, 302);
    exit;
}

// Isolate sessions by changing the session name before start
if ($tempEffectivePort === $masterPort || getenv('APP_MODE') === 'master') {
    session_name('PHPSESSID_MASTER');
} else {
    session_name('PHPSESSID_USER');
}

session_start();

// Load global settings
$configFile = __DIR__ . '/config/settings.json';
$globalSettings = [];
if (file_exists($configFile)) {
    $globalSettings = json_decode(file_get_contents($configFile), true) ?? [];
}
$app_base_domain = $globalSettings['shared_domain'] ?? $globalSettings['hostname'] ?? $_SERVER['HTTP_HOST'] ?? 'localhost';
// Remove port if exists
$app_base_domain = explode(':', $app_base_domain)[0];

// Simple routing for XAMPP
$request_uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$script_name = dirname($_SERVER['SCRIPT_NAME']);

// Normalize slashes to forward slashes
$request_uri = str_replace('\\', '/', $request_uri);
$script_name = str_replace('\\', '/', $script_name);


// Remove base path if present
if (strpos($request_uri, $script_name) === 0) {
    $path = substr($request_uri, strlen($script_name));
} else {
    $path = $request_uri;
}

$path = trim($path, '/');

// Simple router
$script_path = dirname($_SERVER['SCRIPT_NAME']);
$base_url = ($script_path === '/' || $script_path === '\\') ? '' : $script_path;
$base_url = rtrim($base_url, '/');

// Dynamically build API URL for local CURL calls
$serverPort = $_SERVER['SERVER_PORT'];
$port = ($serverPort != 80 && $serverPort != 443) ? ':' . $serverPort : '';

// Internal API URL always hits localhost port 80 inside the container
$api_url = 'http://127.0.0.1' . $base_url . '/public/api';

// Helper function to make API calls
function callAPI($endpoint, $method = 'GET', $data = null, $token = null)
{
    global $api_url;

    $ch = curl_init();
    $url = $api_url . $endpoint;

    $headers = ['Content-Type: application/json'];
    if ($token) {
        $headers[] = 'Authorization: Bearer ' . $token;
    }

    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
    curl_setopt($ch, CURLOPT_TIMEOUT, 15); // Increased Timeout
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);

    if ($data && in_array($method, ['POST', 'PUT', 'PATCH'])) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    }

    // Fix PHP Session Deadlock: Unlock session before internal CURL call
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_write_close();
    }

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err = curl_error($ch);
    curl_close($ch);

    // Re-start session to continue main request processing
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }


    if ($response === false) {
        return ['code' => 0, 'data' => ['error' => "Connection Failed: $err ($url)"]];
    }

    $result = json_decode($response, true);
    return ['code' => $httpCode, 'data' => $result];
}

// Port Detection for Master Panel
$serverPort = $_SERVER['SERVER_PORT'];
$fwPort = $_SERVER['HTTP_X_FORWARDED_PORT'] ?? null;

// Nginx Proxy Support: Detect port from Host header
$hostPort = null;
if (isset($_SERVER['HTTP_HOST'])) {
    $parsed = parse_url('http://' . $_SERVER['HTTP_HOST']);
    if (is_array($parsed)) {
        $hostPort = $parsed['port'] ?? null;
    }
}
$effectivePort = $hostPort ?: ($fwPort ?: $serverPort);

// If Port matches MASTER_PORT, load Master Panel Frontend
$masterPort = (int) ($_ENV['MASTER_PORT'] ?? 9999);
if (
    ((int) $effectivePort === $masterPort || getenv('APP_MODE') === 'master' || strpos((string) $serverPort, (string) 
        $masterPort) !== false)
) {
    if (file_exists(__DIR__ . '/index_master.php')) {
        require __DIR__ . '/index_master.php';
        exit;
    } else {
        die("Master Panel UI not installed.");
    }
}

// Check authentication for protected pages (User Panel)
if ($path !== 'login' && strpos($path, 'login') === false) {
    // ONE-CLICK LOGIN SUPPORT: If token is in URL, update session
    $urlToken = $_GET['token'] ?? null;
    if ($urlToken) {
        $userCheck = callAPI('/auth/me', 'GET', null, $urlToken);
        if ($userCheck && $userCheck['code'] === 200) {
            // If redemption token is provided, use it instead of the urlToken for the session
            $sessionToken = $userCheck['data']['new_token'] ?? $urlToken;

            $_SESSION['lp_session_token'] = $sessionToken;
            $_SESSION['user_name'] = $userCheck['data']['user']['username'] ?? 'User';
            $_SESSION['user_email'] = $userCheck['data']['user']['email'] ?? '';
            $_SESSION['user_role'] = $userCheck['data']['user']['role'] ?? 'user';

            // Security: Redirect to same URL without the token to clear it from address bar/history
            $cleanUrl = strtok($_SERVER["REQUEST_URI"], '?');
            header("Location: $cleanUrl");
            exit;
        } else {
            // Token is invalid or expired - if no existing session, force re-login
            if (!isset($_SESSION['lp_session_token'])) {
                header('Location: ' . $base_url . '/login?error=token_expired');
                exit;
            }
            // If session exists, silently redirect to clean URL (existing session is valid)
            $cleanUrl = strtok($_SERVER["REQUEST_URI"], '?');
            header("Location: $cleanUrl");
            exit;
        }
    }

    if (!isset($_SESSION['lp_session_token'])) {
        header('Location: ' . $base_url . '/login');
        exit;
    }

    // Verify token is still valid (every reload)
    $userCheck = callAPI('/auth/me', 'GET', null, $_SESSION['lp_session_token']);
    if (!$userCheck || $userCheck['code'] !== 200) {
        session_destroy();
        header('Location: ' . $base_url . '/login');
        exit;
    }

    // Update session with fresh user data
    $_SESSION['user_name'] = $userCheck['data']['user']['username'] ?? 'User';
    $_SESSION['user_email'] = $userCheck['data']['user']['email'] ?? '';
    $_SESSION['user_role'] = $userCheck['data']['user']['role'] ?? 'user';

    // BLOCK ADMIN ACCESS TO USER PANEL
    if (in_array($_SESSION['user_role'], ['admin', 'root', 'reseller'])) {
        session_destroy();
        // Redirect to Master Panel if possible, otherwise show error on login
        $masterPort = (int) ($_ENV['MASTER_PORT'] ?? 9999);
        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host = explode(':', $_SERVER['HTTP_HOST'])[0];
        // Optional: Redirect to Master Panel
        // header("Location: $protocol://$host:$masterPort");
        
        header('Location: ' . $base_url . '/login?error=admin_access_denied');
        exit;
    }
}

// Router
if (
    $path === '' || $path === 'index.php' || $path === 'logicpanel' || $path === 'dashboard' || strpos(
        $path,
        'dashboard'
    ) === 0
) {
    $title = 'Dashboard';
    $current_page = 'dashboard';
    // Removed duplicate include here
    $user_name = $_SESSION['user_name'];

    // Fetch services from API
    $servicesResponse = callAPI('/services', 'GET', null, $_SESSION['lp_session_token']);
    $services = [];
    $serviceCount = 0;
    $runningCount = 0;
    $stoppedCount = 0;

    if ($servicesResponse && $servicesResponse['code'] === 200) {
        $services = $servicesResponse['data']['services'] ?? [];
        $serviceCount = count($services);
        foreach ($services as $service) {
            if ($service['status'] === 'running') {
                $runningCount++;
            } elseif ($service['status'] === 'stopped') {
                $stoppedCount++;
            }
        }
    }

    $totalStats = [
        'cpu' => '0%',
        'memory' => '0MB',
        'disk' => '0GB'
    ];

    include 'templates/user/dashboard/index.php';

} elseif ($path === 'login' || strpos($path, 'login') !== false) {
    // Handle login form submission
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $username = $_POST['username'] ?? '';
        $password = $_POST['password'] ?? '';

        $loginResponse = callAPI('/auth/login', 'POST', [
            'username' => $username,
            'password' => $password
        ]);


        if ($loginResponse && $loginResponse['code'] === 200 && isset($loginResponse['data']['token'])) {
            $_SESSION['lp_session_token'] = $loginResponse['data']['token'];
            $_SESSION['refresh_token'] = $loginResponse['data']['refresh_token'] ?? null;
            $_SESSION['user_name'] = $loginResponse['data']['user']['username'] ?? 'User';
            $_SESSION['user_email'] = $loginResponse['data']['user']['email'] ?? '';
            $_SESSION['user_role'] = $loginResponse['data']['user']['role'] ?? 'user';

            // BLOCK ADMIN LOGIN
            if (in_array($_SESSION['user_role'], ['admin', 'root', 'reseller'])) {
                session_destroy();
                $_SESSION['login_error'] = 'Administrators must use the Master Panel.';
                header('Location: ' . $base_url . '/login');
                exit;
            }

            // PRG Pattern: Redirect after POST to prevent resubmission
            header('Location: ' . $base_url . '/');
            exit;
        } else {
            // Store error in session and redirect to prevent resubmission
            $_SESSION['login_error'] = $loginResponse['data']['error'] ?? 'Login failed. Please check your credentials.';
            header('Location: ' . $base_url . '/login');
            exit;
        }
    }

    // Get error from session if exists
    $error = $_SESSION['login_error'] ?? null;
    unset($_SESSION['login_error']); // Clear error after displaying

    include 'templates/shared/login.php';

} elseif ($path === 'logout') {
    // Call logout API
    if (isset($_SESSION['lp_session_token'])) {
        callAPI('/auth/logout', 'POST', null, $_SESSION['lp_session_token']);
    }
    session_destroy();
    header('Location: ' . $base_url . '/login');
    exit;

} elseif (strpos($path, 'apps/files') === 0) {
    // Handle file manager
    $title = 'File Manager';
    $current_page = 'apps_files';
    include 'templates/user/apps/files.php';

} elseif (strpos($path, 'apps/upload') === 0) {
    // Handle file upload
    $title = 'Upload Files';
    $current_page = 'apps_upload';
    include 'templates/user/apps/upload.php';

} elseif (strpos($path, 'apps/editor') === 0) {
    // Handle code editor
    $title = 'Code Editor';
    $current_page = 'apps_editor';
    include 'templates/user/apps/editor.php';

} elseif ($path === 'apps/nodejs') {
    $title = 'Node.js Applications';
    $current_page = 'apps_nodejs';
    include 'templates/user/apps/nodejs.php';

} elseif ($path === 'apps/python') {
    $title = 'Python Applications';
    $current_page = 'apps_python';
    include 'templates/user/apps/python.php';

} elseif (strpos($path, 'apps/create') === 0) {
    // Handle app creation (Keep for legacy or direct access)
    $title = 'Create Application';
    $current_page = 'apps_create';
    include 'templates/user/apps/create.php';

} elseif ($path === 'apps/overview') {
    // Handle overview
    $title = 'Applications Overview';
    $current_page = 'apps_overview';
    include 'templates/user/apps/overview.php';

} elseif (strpos($path, 'apps/terminal') === 0 || strpos($path, 'terminal') === 0) {
    // Handle terminal
    $title = 'Terminal';
    $current_page = 'terminal';
    include 'templates/user/apps/terminal.php';

} elseif ($path === 'databases/mysql') {
    $title = 'MySQL Databases';
    $current_page = 'databases_mysql';
    include 'templates/user/databases/mysql.php';

} elseif ($path === 'databases/postgresql') {
    $title = 'PostgreSQL Databases';
    $current_page = 'databases_postgresql';
    include 'templates/user/databases/postgresql.php';

} elseif ($path === 'databases/mongodb') {
    $title = 'MongoDB Databases';
    $current_page = 'databases_mongodb';
    include 'templates/user/databases/mongodb.php';

} elseif (strpos($path, 'databases') === 0) {
    if ($path === 'databases') {
        header("Location: $base_url/databases/mysql");
        exit;
    }
    $title = 'Databases';
    $current_page = 'databases';
    include 'templates/user/databases/index.php';

} elseif ($path === 'settings/profile') {
    $title = 'My Profile';
    $current_page = 'profile';

    // Ensure directory exists or create it
    if (!file_exists('templates/user/settings/profile.php')) {
        // Fallback or handle missing file
        echo "Profile template missing";
    } else {
        include 'templates/user/settings/profile.php';
    }

} elseif ($path === 'backups') {
    $title = 'Backups';
    $current_page = 'backups';
    if (!file_exists('templates/user/backups/index.php')) {
        echo "Backup template missing";
    } else {
        include 'templates/user/backups/index.php';
    }

} elseif ($path === 'cron') {
    $title = 'Cron Jobs';
    $current_page = 'cron';
    if (!file_exists('templates/user/cron/index.php')) {
        echo "Cron template missing";
    } else {
        include 'templates/user/cron/index.php';
    }

} elseif ($path === 'domains') {
    $title = 'Addon Domains';
    $current_page = 'domains';
    if (!file_exists('templates/user/domains/index.php')) {
        echo "Domains template missing";
    } else {
        include 'templates/user/domains/index.php';
    }

} else {
    // API Fallback for when .htaccess is ignored
    if ($isApiRequest) {
        // Fix $_SERVER for Slim if needed
        $_SERVER['SCRIPT_NAME'] = '/public/api.php';
        
        // Normalize REQUEST_URI for API routing
        if (strpos($_SERVER['REQUEST_URI'], '/api/') !== false && strpos($_SERVER['REQUEST_URI'], '/public/api/') === false) {
            // Convert /api/ to /public/api/ internally for Slim
            $_SERVER['REQUEST_URI'] = str_replace('/api/', '/public/api/', $_SERVER['REQUEST_URI']);
        }
        
        require __DIR__ . '/public/api.php';
        exit;
    }

    // 404
    http_response_code(404);
    if (file_exists('templates/shared/errors/404.php')) {
        include 'templates/shared/errors/404.php';
    } else {
        echo "404 - Page not found";
    }
}