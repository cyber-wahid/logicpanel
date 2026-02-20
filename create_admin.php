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

// Boot Eloquent with connection retry
use Illuminate\Database\Capsule\Manager as Capsule;
use LogicPanel\Domain\User\User;

$db_host = getenv('DB_HOST') ?: ($_ENV['DB_HOST'] ?? 'logicpanel-db');
$db_port = getenv('DB_PORT') ?: ($_ENV['DB_PORT'] ?? '3306');
$db_name = getenv('DB_DATABASE') ?: ($_ENV['DB_DATABASE'] ?? 'logicpanel');
$db_user = getenv('DB_USERNAME') ?: ($_ENV['DB_USERNAME'] ?? 'logicpanel');
$db_pass = getenv('DB_PASSWORD') ?: ($_ENV['DB_PASSWORD'] ?? '');

echo "Connecting to database at {$db_host}:{$db_port}/{$db_name}...\n";

$maxRetries = 5;
$connected = false;

for ($attempt = 1; $attempt <= $maxRetries; $attempt++) {
    try {
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
        
        // Test the connection by running a simple query
        Capsule::select('SELECT 1');
        $connected = true;
        echo "Database connection successful.\n";
        break;
    } catch (\Exception $e) {
        echo "Connection attempt {$attempt}/{$maxRetries} failed: " . $e->getMessage() . "\n";
        if ($attempt < $maxRetries) {
            echo "Retrying in 5 seconds...\n";
            sleep(5);
        }
    }
}

if (!$connected) {
    echo "Error: Database connection failed after {$maxRetries} attempts.\n";
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