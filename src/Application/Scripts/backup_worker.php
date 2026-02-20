<?php

// Worker script to create backup in background
// Usage: php backup_worker.php <filename> <sourceDir> <backupDir>

if ($argc < 4) {
    die("Usage: php backup_worker.php <filename_without_ext> <sourceDir> <backupDir>\n");
}

$filename = $argv[1];
$sourceDir = $argv[2];
$backupDir = $argv[3];

// Ensure directories exist
if (!is_dir($sourceDir) || !is_dir($backupDir)) {
    error_log("Worker: Source or Backup dir missing.");
    exit(1);
}

$pendingFile = $backupDir . '/' . $filename . '.zip.pending';
$targetFile = $backupDir . '/' . $filename . '.zip';

// Create ZipArchive
$zip = new ZipArchive();
if ($zip->open($targetFile, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== TRUE) {
    error_log("Worker: Cannot create zip file at $targetFile");
    // Remove pending file to signal failure (or keep it to show stuck state? Better to remove so it disappears)
    @unlink($pendingFile);
    exit(1);
}

// Add user-apps directory
$files = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($sourceDir),
    RecursiveIteratorIterator::LEAVES_ONLY
);

$fileCount = 0;
foreach ($files as $name => $file) {
    if (!$file->isDir()) {
        $filePath = $file->getRealPath();

        if (!is_readable($filePath))
            continue;

        $relativePath = substr($filePath, strlen($sourceDir) + 1);

        if (strpos($relativePath, '.') === 0)
            continue;

        // EXCLUDE HEAVY DIRECTORIES
        $normRelativePath = str_replace('\\', '/', $relativePath);
        if (preg_match('#(^|/)(\.git|node_modules|vendor)($|/)#', $normRelativePath)) {
            continue;
        }

        $zip->addFile($filePath, $relativePath);
        $fileCount++;
    }
}

if ($fileCount === 0) {
    $zip->addFromString('readme.txt', 'This backup is empty because no user applications were found.');
}

$zip->close();

// Remove pending file to mark completion
// Since we wrote directly to .zip, we just remove the pending marker
@unlink($pendingFile);

exit(0);
