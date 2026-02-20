<?php
/**
 * LogicPanel Adminer Auto-Login Helper - Sessionless
 */

require_once __DIR__ . '/../vendor/autoload.php';

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

try {
    $dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
    $dotenv->load();
} catch (\Exception $e) {
}

$jwtSecret = $_ENV['JWT_SECRET'] ?? 'your-super-secret-key-change-in-production';
$authToken = $_GET['auth'] ?? $_COOKIE['lp_adminer_auth'] ?? null;
$is_authenticated = false;

if ($authToken) {
    try {
        JWT::decode($authToken, new Key($jwtSecret, 'HS256'));
        $is_authenticated = true;
    } catch (\Exception $e) {
    }
}

if (!$is_authenticated) {
    session_name('PHPSESSID_USER');
    @session_start();

    if (isset($_SESSION['lp_session_token']) && is_string($_SESSION['lp_session_token'])) {
        $is_authenticated = true;
        $authToken = JWT::encode([
            'iss' => 'logicpanel',
            'iat' => time(),
            'exp' => time() + 3600,
            'purpose' => 'adminer_access'
        ], $jwtSecret, 'HS256');
    }
    session_write_close();
}

if (!$is_authenticated) {
    http_response_code(403);
    die('Access Denied. Please login to LogicPanel first.');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $server = $_POST['server'] ?? 'lp-mysql-mother';
    $username = $_POST['username'] ?? '';
    $db = $_POST['db'] ?? '';
    $driver = $_POST['driver'] ?? 'server';

    $params = ['auth' => $authToken];
    if ($driver !== 'server' && $driver !== 'mysql') {
        $params[$driver] = $server;
    } else {
        $params['server'] = $server;
    }
    $params['username'] = $username;
    if ($db)
        $params['db'] = $db;

    header("Location: adminer.php?" . http_build_query($params));
    exit;
}

header("Location: adminer.php?auth=" . urlencode($authToken));
exit;
