<?php
/**
 * LogicPanel - Admin Account Creator
 * 
 * Usage: php create_admin.php --user="admin" --email="admin@example.com" --pass="password"
 * 
 * This script is called by install.sh during installation.
 * It boots Eloquent, creates the admin user, and exits.
 */

require __DIR__ . '/vendor/autoload.php';

// Load .env
try {
    $dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
    $dotenv->load();
} catch (\Exception $e) {
    echo "Warning: Could not load .env file: " . $e->getMessage() . "\n";
}

// Parse CLI arguments
$options = getopt('', ['user:', 'email:', 'pass:']);

$username = $options['user'] ?? null;
$email    = $options['email'] ?? null;
$password = $options['pass'] ?? null;

if (!$username || !$email || !$password) {
    echo "Error: Missing required arguments.\n";
    echo "Usage: php create_admin.php --user=\"admin\" --email=\"admin@example.com\" --pass=\"password\"\n";
    exit(1);
}

// Boot Eloquent
use Illuminate\Database\Capsule\Manager as Capsule;
use LogicPanel\Domain\User\User;

try {
    $db_host = $_ENV['DB_HOST'] ?? getenv('DB_HOST') ?: 'localhost';
    $db_port = $_ENV['DB_PORT'] ?? getenv('DB_PORT') ?: '3306';
    $db_name = $_ENV['DB_DATABASE'] ?? getenv('DB_DATABASE') ?: 'logicpanel';
    $db_user = $_ENV['DB_USERNAME'] ?? getenv('DB_USERNAME') ?: 'logicpanel';
    $db_pass = $_ENV['DB_PASSWORD'] ?? getenv('DB_PASSWORD') ?: '';

    echo "Connecting to database: {$db_host}:{$db_port}, DB: {$db_name}, User: {$db_user}\n";

    $capsule = new Capsule;
    $capsule->addConnection([
        'driver'    => 'mysql',
        'host'      => $db_host,
        'port'      => $db_port,
        'database'  => $db_name,
        'username'  => $db_user,
        'password'  => $db_pass,
        'charset'   => 'utf8mb4',
        'collation' => 'utf8mb4_unicode_ci',
        'prefix'    => '',
    ]);
    $capsule->setAsGlobal();
    $capsule->bootEloquent();
} catch (\Exception $e) {
    echo "Error: Database connection failed: " . $e->getMessage() . "\n";
    exit(1);
}

// Create or update admin user
try {
    $existing = User::where('email', $email)->first();

    if ($existing) {
        echo "Admin user '{$email}' already exists (ID: {$existing->id}). Updating credentials...\n";
        $existing->username = $username;
        $existing->setPassword($password);
        $existing->role = 'admin';
        $existing->status = 'active';
        $existing->save();
        echo "Admin user updated successfully.\n";
    } else {
        $user = new User();
        $user->username = $username;
        $user->email = $email;
        $user->setPassword($password);
        $user->role = 'admin';
        $user->status = 'active';
        $user->save();
        echo "Admin user created successfully (ID: {$user->id}).\n";
    }

    exit(0);
} catch (\Exception $e) {
    echo "Error: Failed to create admin user: " . $e->getMessage() . "\n";
    exit(1);
}