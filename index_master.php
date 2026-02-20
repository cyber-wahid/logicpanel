<?php
// Master Panel Frontend Controller
// This file is included by index.php when the Master Panel port is detected.

// Security: Prevent direct web access
if (!defined('LP_MAIN_ENTRY') && php_sapi_name() !== 'cli') {
    header('HTTP/1.1 403 Forbidden');
    die("Access Denied: Direct access to this component is not allowed.");
}

// Ensure session is started (handled by index.php usually, but good to be safe if called directly)
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Variables from index.php are available here ($path, $base_url, $api_url, etc.)

// Define Template Directory for Master
$tplDir = __DIR__ . '/templates/master';
$sharedDir = __DIR__ . '/templates/shared';

// GLOBAL AUTH CHECK for Master Panel
if ($path !== 'login') {
    $user_role = $_SESSION['user_role'] ?? '';
    if (!isset($_SESSION['lp_session_token']) || ($user_role !== 'admin' && $user_role !== 'root' && $user_role !== 'reseller')) {
        header("Location: $base_url/login");
        exit;
    }

    // Verify token validity against API
    $userCheck = callAPI('/auth/me', 'GET', null, $_SESSION['lp_session_token']);
    if (!$userCheck || $userCheck['code'] !== 200) {
        session_destroy();
        header("Location: $base_url/login");
        exit;
    }

    // Update session data
    $_SESSION['user_name'] = $userCheck['data']['user']['username'];
}

// Routing for Master Panel
if ($path === '' || $path === 'index.php' || $path === 'dashboard') {
    $page_title = 'WHM Dashboard';
    $current_page = 'dashboard';
    include $tplDir . '/dashboard.php';

} elseif ($path === 'login') {
    // Admin Login Page
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $username = $_POST['username'] ?? '';
        $password = $_POST['password'] ?? '';

        $loginResponse = callAPI('/auth/login', 'POST', [
            'username' => $username,
            'password' => $password
        ]);

        if ($loginResponse && $loginResponse['code'] === 200) {
            $role = $loginResponse['data']['user']['role'] ?? 'user';

            // Enforce Admin/Root/Reseller Role
            if ($role === 'admin' || $role === 'root' || $role === 'reseller') {
                $_SESSION['lp_session_token'] = $loginResponse['data']['token'];
                $_SESSION['refresh_token'] = $loginResponse['data']['refresh_token'];
                $_SESSION['user_name'] = $loginResponse['data']['user']['username'];
                $_SESSION['user_role'] = $role;
                $_SESSION['user_email'] = $loginResponse['data']['user']['email'];

                header("Location: $base_url/dashboard");
                exit;
            } else {
                $_SESSION['login_error'] = 'Access denied. Administrator privileges required.';
            }
        } else {
            $_SESSION['login_error'] = $loginResponse['data']['error'] ?? 'Login failed. Please check your credentials.';
        }
        header("Location: $base_url/login");
        exit;
    }

    $is_master_login = true;
    $error = $_SESSION['login_error'] ?? null;
    unset($_SESSION['login_error']);
    include $sharedDir . '/login.php';

} elseif ($path === 'logout') {
    if (isset($_SESSION['lp_session_token'])) {
        callAPI('/auth/logout', 'POST', null, $_SESSION['lp_session_token']);
    }
    session_destroy();
    header("Location: $base_url/login");
    exit;

} elseif ($path === 'accounts/create') {
    $page_title = 'Create Account';
    $current_page = 'accounts_create';
    include $tplDir . '/accounts/create.php';

} elseif ($path === 'accounts/list') {
    $page_title = 'List Accounts';
    $current_page = 'accounts_list';
    include $tplDir . '/accounts/list.php';

} elseif ($path === 'resellers') {
    $page_title = 'Resellers';
    $current_page = 'resellers';
    include $tplDir . '/resellers/list.php';

} elseif ($path === 'resellers/create') {
    $page_title = 'Create Reseller';
    $current_page = 'resellers';
    include $tplDir . '/resellers/create.php';

} elseif ($path === 'reseller-packages') {
    $page_title = 'Reseller Packages';
    $current_page = 'reseller_packages';
    include $tplDir . '/reseller-packages/list.php';

} elseif ($path === 'reseller-packages/create') {
    $page_title = 'Create Reseller Package';
    $current_page = 'reseller_packages';
    include $tplDir . '/reseller-packages/create.php';

} elseif ($path === 'reseller-packages/edit') {
    $page_title = 'Edit Reseller Package';
    $current_page = 'reseller_packages';
    include $tplDir . '/reseller-packages/edit.php';

} elseif ($path === 'accounts/edit') {
    $page_title = 'Edit Account';
    $current_page = 'accounts_list';
    include $tplDir . '/accounts/edit.php';

} elseif ($path === 'packages/list') {
    $page_title = 'Packages';
    $current_page = 'packages_list';
    include $tplDir . '/packages/list.php';

} elseif ($path === 'packages/create') {
    $type = $_GET['type'] ?? '';
    $page_title = 'Add Package';
    $current_page = ($type === 'reseller') ? 'reseller_packages' : 'packages_list';
    include $tplDir . '/packages/create.php';

} elseif ($path === 'packages/edit') {
    $type = $_GET['type'] ?? '';
    $page_title = 'Edit Package';
    $current_page = ($type === 'reseller') ? 'reseller_packages' : 'packages_list';
    include $tplDir . '/packages/edit.php'; // Requires edit.php to exist

} elseif ($path === 'terminal') {
    $page_title = 'Terminal';
    $current_page = 'terminal';
    include $tplDir . '/terminal.php';

} elseif ($path === 'settings/config') {
    $page_title = 'Server Configuration';
    $current_page = 'settings';
    include $tplDir . '/settings/index.php';

} elseif ($path === 'databases/list') {
    $page_title = 'Databases';
    $current_page = 'databases';
    include $tplDir . '/databases/list.php';

} elseif ($path === 'domains/list') {
    $page_title = 'Domains';
    $current_page = 'domains';
    include $tplDir . '/domains/list.php';

} elseif ($path === 'domains/create') {
    $page_title = 'Add Domain';
    $current_page = 'domains';
    include $tplDir . '/domains/create.php';

} elseif ($path === 'services/list') {
    $page_title = 'Service Manager';
    $current_page = 'services';
    include $tplDir . '/services/list.php';

} elseif ($path === 'settings/updates') {
    $page_title = 'System Updates';
    $current_page = 'updates';
    include $tplDir . '/settings/updates.php';

} elseif ($path === 'api-keys/list') {
    $page_title = 'API Access Keys';
    $current_page = 'api_keys';
    include $tplDir . '/api-keys/list.php';

} elseif ($path === 'security/locked-accounts') {
    $page_title = 'Locked Accounts';
    $current_page = 'locked_accounts';
    include $tplDir . '/security/locked-accounts.php';

} elseif ($path === 'settings/profile') {
    $page_title = 'My Profile';
    $current_page = 'profile';
    include $tplDir . '/settings/profile.php';

} else {
    // 404
    include $sharedDir . '/errors/404.php';
}
