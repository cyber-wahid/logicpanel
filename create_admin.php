<?php

require __DIR__ . '/vendor/autoload.php';

// Check if running from CLI
if (php_sapi_name() !== 'cli') {
    exit;
}

// Parse arguments
$options = getopt('', ['user:', 'email:', 'pass:']);

$username = $options['user'] ?? 'admin';
$email = $options['email'] ?? 'admin@example.com';
$password = $options['pass'] ?? 'password';

// Load environment variables if .env exists
if (file_exists(__DIR__ . '/.env')) {
    $dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
    $dotenv->load();
}

$host = getenv('DB_HOST') ?: 'logicpanel-db';
$db = getenv('DB_DATABASE') ?: 'logicpanel';
$user = getenv('DB_USERNAME') ?: 'logicpanel';
$pass = getenv('DB_PASSWORD') ?: 'logicpanel';

echo "Connecting to database...\n";

try {
    $pdo = new PDO("mysql:host=$host;dbname=$db", $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);

    // Check if user already exists
    $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? OR username = ?");
    $stmt->execute([$email, $username]);
    $existing = $stmt->fetch();

    if ($existing) {
        echo "User already exists. Updating password...\n";
        $stmt = $pdo->prepare("UPDATE users SET password_hash = ?, role = 'admin', status = 'active' WHERE id = ?");
        $stmt->execute([password_hash($password, PASSWORD_BCRYPT), $existing['id']]);
        echo "Admin user updated successfully.\n";
    } else {
        echo "Creating new admin user...\n";
        $stmt = $pdo->prepare("INSERT INTO users (username, email, password_hash, role, status, created_at, updated_at) VALUES (?, ?, ?, 'admin', 'active', NOW(), NOW())");
        $stmt->execute([$username, $email, password_hash($password, PASSWORD_BCRYPT)]);
        echo "Admin user created successfully.\n";
    }

} catch (PDOException $e) {
    echo "Database Error: " . $e->getMessage() . "\n";
    exit(1);
}
