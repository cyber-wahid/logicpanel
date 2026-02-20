#!/usr/bin/env php
<?php
/**
 * Cleanup Orphaned Service Directories
 * 
 * This script runs periodically to clean up any service directories
 * that don't have corresponding database records.
 * 
 * Should be run via cron or manually.
 */

require_once __DIR__ . '/../../../vendor/autoload.php';

use Illuminate\Database\Capsule\Manager as Capsule;
use LogicPanel\Domain\Service\Service;

// Initialize database connection
$capsule = new Capsule;
$capsule->addConnection([
    'driver' => 'mysql',
    'host' => getenv('DB_HOST') ?: 'logicpanel-db',
    'database' => getenv('DB_DATABASE') ?: 'logicpanel',
    'username' => getenv('DB_USERNAME') ?: 'logicpanel',
    'password' => getenv('DB_PASSWORD') ?: 'logicpanel_password',
    'charset' => 'utf8mb4',
    'collation' => 'utf8mb4_unicode_ci',
    'prefix' => '',
]);
$capsule->setAsGlobal();
$capsule->bootEloquent();

echo "[" . date('Y-m-d H:i:s') . "] Starting orphaned directory cleanup...\n";

// Get storage path
$storagePath = getenv('USER_APPS_PATH') ?: '/var/www/html/storage/user-apps';

if (!is_dir($storagePath)) {
    echo "Storage path not found: $storagePath\n";
    exit(1);
}

// Get all service IDs from database
$serviceIds = Service::pluck('id')->toArray();
echo "Found " . count($serviceIds) . " services in database\n";

// Scan storage directory
$dirs = glob($storagePath . '/service_*', GLOB_ONLYDIR);
$orphanedCount = 0;
$deletedCount = 0;
$failedCount = 0;

foreach ($dirs as $dir) {
    $dirname = basename($dir);
    
    if (preg_match('/service_(\d+)/', $dirname, $matches)) {
        $serviceId = (int)$matches[1];
        
        // Check if service exists in database
        if (!in_array($serviceId, $serviceIds)) {
            $orphanedCount++;
            echo "Found orphaned directory: $dirname\n";
            
            // Try to delete
            $escapedDir = escapeshellarg($dir);
            exec("rm -rf $escapedDir 2>&1", $output, $returnCode);
            
            if ($returnCode === 0 && !is_dir($dir)) {
                echo "  ✓ Deleted successfully\n";
                $deletedCount++;
            } else {
                echo "  ✗ Failed to delete (trying chmod...)\n";
                exec("chmod -R 777 $escapedDir 2>&1");
                exec("rm -rf $escapedDir 2>&1", $output2, $returnCode2);
                
                if ($returnCode2 === 0 && !is_dir($dir)) {
                    echo "  ✓ Deleted after chmod\n";
                    $deletedCount++;
                } else {
                    echo "  ✗ Failed to delete even after chmod\n";
                    $failedCount++;
                }
            }
        }
    }
}

echo "\n=== Cleanup Summary ===\n";
echo "Orphaned directories found: $orphanedCount\n";
echo "Successfully deleted: $deletedCount\n";
echo "Failed to delete: $failedCount\n";
echo "[" . date('Y-m-d H:i:s') . "] Cleanup completed\n";

exit($failedCount > 0 ? 1 : 0);
