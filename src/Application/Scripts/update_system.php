#!/usr/bin/env php
<?php
/**
 * LogicPanel System Update Script
 * 
 * This script handles the update process:
 * 1. Downloads latest version from GitHub
 * 2. Backs up current installation
 * 3. Applies updates
 * 4. Triggers container rebuild
 */

$logFile = __DIR__ . '/../../../storage/logs/update.log';
$backupDir = __DIR__ . '/../../../storage/backups';
$rootDir = __DIR__ . '/../../..';

function logMessage($message) {
    global $logFile;
    $timestamp = date('Y-m-d H:i:s');
    $logLine = "[$timestamp] $message\n";
    echo $logLine;
    file_put_contents($logFile, $logLine, FILE_APPEND);
}

logMessage("========================================");
logMessage("LogicPanel Update Process Started");
logMessage("========================================");

// Step 1: Get current and latest version
$currentVersion = trim(file_get_contents($rootDir . '/VERSION') ?: '0.0.0');
logMessage("Current version: $currentVersion");

try {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, 'https://api.github.com/repos/cyber-wahid/logicpanel/releases/latest');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_USERAGENT, 'LogicPanel-Updater');
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Accept: application/vnd.github.v3+json']);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode !== 200) {
        logMessage("Error: GitHub API returned HTTP $httpCode");
        exit(1);
    }
    
    $release = json_decode($response, true);
    $latestVersion = ltrim($release['tag_name'] ?? '0.0.0', 'v');
    $downloadUrl = $release['zipball_url'] ?? null;
    
    logMessage("Latest version: $latestVersion");
    
    if (version_compare($latestVersion, $currentVersion, '<=')) {
        logMessage("Already on latest version!");
        exit(0);
    }
    
} catch (Exception $e) {
    logMessage("Error checking version: " . $e->getMessage());
    exit(1);
}

// Step 2: Create backup
logMessage("Creating backup...");
if (!is_dir($backupDir)) {
    mkdir($backupDir, 0775, true);
}

$timestamp = date('Ymd_His');
$backupFile = "$backupDir/backup_$timestamp.tar.gz";

$excludes = [
    '--exclude=storage/logs/*',
    '--exclude=storage/user-apps/*',
    '--exclude=storage/backups/*',
    '--exclude=vendor',
    '--exclude=node_modules',
    '--exclude=.git'
];

$excludeStr = implode(' ', $excludes);
exec("cd $rootDir && tar -czf $backupFile $excludeStr . 2>&1", $output, $returnCode);

if ($returnCode === 0) {
    logMessage("Backup created: $backupFile");
} else {
    logMessage("Warning: Backup creation had issues");
}

// Step 3: Download latest version
logMessage("Downloading latest version...");
$tempDir = sys_get_temp_dir() . "/logicpanel_update_$timestamp";
mkdir($tempDir, 0775, true);

$zipFile = "$tempDir/latest.zip";

// Download using curl
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $downloadUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
curl_setopt($ch, CURLOPT_USERAGENT, 'LogicPanel-Updater');
curl_setopt($ch, CURLOPT_TIMEOUT, 300);
curl_setopt($ch, CURLOPT_FILE, fopen($zipFile, 'w'));

$result = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($httpCode !== 200 || !file_exists($zipFile)) {
    logMessage("Error: Failed to download update");
    exit(1);
}

logMessage("Download complete");

// Step 4: Extract and apply update
logMessage("Extracting update...");
$zip = new ZipArchive;
if ($zip->open($zipFile) === TRUE) {
    $zip->extractTo($tempDir);
    $zip->close();
    logMessage("Extraction complete");
} else {
    logMessage("Error: Failed to extract zip file");
    exit(1);
}

// Find extracted directory (GitHub creates a directory with commit hash)
$extractedDirs = glob("$tempDir/cyber-wahid-logicpanel-*");
if (empty($extractedDirs)) {
    // Fallback for different naming conventions
    $extractedDirs = glob("$tempDir/*logicpanel-*");
}
if (empty($extractedDirs)) {
    logMessage("Error: Could not find extracted directory");
    exit(1);
}

$extractedDir = $extractedDirs[0];
logMessage("Applying updates from: $extractedDir");

// Copy files (preserve .env, storage, and other important files)
$rsyncCmd = "rsync -av " .
    "--exclude='.env' " .
    "--exclude='storage/logs/*' " .
    "--exclude='storage/user-apps/*' " .
    "--exclude='storage/backups/*' " .
    "--exclude='letsencrypt/acme.json' " .
    "$extractedDir/ $rootDir/ 2>&1";

exec($rsyncCmd, $rsyncOutput, $rsyncCode);

if ($rsyncCode === 0) {
    logMessage("Files updated successfully");
} else {
    logMessage("Warning: Some files may not have been updated");
    logMessage(implode("\n", $rsyncOutput));
}

// Step 5: Update dependencies
logMessage("Updating dependencies...");
exec("cd $rootDir && composer install --no-dev --optimize-autoloader --no-interaction 2>&1", $composerOutput);
logMessage("Dependencies updated");

// Step 6: Update VERSION file
file_put_contents("$rootDir/VERSION", $latestVersion);
logMessage("Version file updated to: $latestVersion");

// Step 7: Run migrations
logMessage("Running database migrations...");
$migrateScript = "$rootDir/docker/migrate.sh";
if (file_exists($migrateScript)) {
    exec("bash $migrateScript 2>&1", $migrateOutput);
    logMessage("Migrations complete");
}

// Step 8: Clear caches
logMessage("Clearing caches...");
exec("rm -rf $rootDir/storage/framework/cache/* 2>&1");
exec("rm -rf $rootDir/storage/framework/sessions/* 2>&1");

// Step 9: Cleanup
logMessage("Cleaning up temporary files...");
exec("rm -rf $tempDir");

logMessage("========================================");
logMessage("Update completed successfully!");
logMessage("Version: $currentVersion → $latestVersion");
logMessage("========================================");
logMessage("Note: Container restart may be required for full effect");

exit(0);
