<?php

declare(strict_types=1);

namespace LogicPanel\Application\Controllers;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use LogicPanel\Domain\Service\Service;
use Symfony\Component\Process\Process;

class BackupController
{
    private string $userAppsPath;

    public function __construct()
    {
        // Path to user-apps storage (host path, accessible from PHP container)
        $this->userAppsPath = dirname(__DIR__, 3) . '/storage/user-apps';

        if (!is_dir($this->userAppsPath)) {
            mkdir($this->userAppsPath, 0755, true);
        }
    }

    /**
     * List all backups for user's applications
     */
    public function index(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $userId = $request->getAttribute('userId');
        $backups = [];

        // Get all user's services
        $services = Service::where('user_id', $userId)->get();

        foreach ($services as $service) {
            $backupDir = $this->userAppsPath . "/service_{$service->id}/backup";

            if (!is_dir($backupDir)) {
                continue;
            }

            // Scan for backup files
            $files = glob($backupDir . '/*.zip');
            if ($files) {
                foreach ($files as $file) {
                    $backups[] = [
                        'name' => basename($file),
                        'app_name' => $service->name,
                        'service_id' => $service->id,
                        'size' => $this->formatSize(filesize($file)),
                        'date' => date('Y-m-d H:i:s', filemtime($file)),
                        'type' => 'application',
                        'status' => 'ready'
                    ];
                }
            }

            // Check for pending backups
            $pendingFiles = glob($backupDir . '/*.pending');
            if ($pendingFiles) {
                foreach ($pendingFiles as $file) {
                    // Check for stale pending files (> 10 mins)
                    if (time() - filemtime($file) > 600) {
                        @unlink($file);
                        continue;
                    }

                    $baseName = basename($file, '.pending');
                    $backups[] = [
                        'name' => $baseName,
                        'app_name' => $service->name,
                        'service_id' => $service->id,
                        'size' => '-',
                        'date' => date('Y-m-d H:i:s', filemtime($file)),
                        'type' => 'application',
                        'status' => 'creating'
                    ];
                }
            }
        }

        // Sort by date desc
        usort($backups, function ($a, $b) {
            return strtotime($b['date']) - strtotime($a['date']);
        });

        return $this->jsonResponse($response, ['backups' => $backups]);
    }

    /**
     * Create backup for a specific application
     */
    public function createAppBackup(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $userId = $request->getAttribute('userId');
        $data = $request->getParsedBody();
        $serviceId = (int) ($data['service_id'] ?? 0);

        if (!$serviceId) {
            return $this->jsonResponse($response, ['error' => 'Service ID required'], 400);
        }

        // Verify user owns this service
        $service = Service::where('id', $serviceId)
            ->where('user_id', $userId)
            ->first();

        if (!$service) {
            return $this->jsonResponse($response, ['error' => 'Service not found'], 404);
        }

        $appDir = $this->userAppsPath . "/service_{$serviceId}";
        $backupDir = $appDir . "/backup";

        // Create backup directory if it doesn't exist
        if (!is_dir($backupDir)) {
            mkdir($backupDir, 0755, true);
        }

        // Generate backup filename
        $timestamp = date('Y-m-d_H-i-s');
        $backupFilename = "backup_{$timestamp}.zip";
        $backupPath = $backupDir . '/' . $backupFilename;
        $pendingFile = $backupPath . '.pending';

        // Create pending marker
        file_put_contents($pendingFile, 'creating');

        // Create backup using zip command - exclude the backup folder itself
        $zip = new \ZipArchive();
        if ($zip->open($backupPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== TRUE) {
            @unlink($pendingFile);
            return $this->jsonResponse($response, ['error' => 'Failed to create backup archive'], 500);
        }

        // Add files to archive (excluding backup folder)
        $this->addFilesToZip($zip, $appDir, '', ['backup']);
        $zip->close();

        // Remove pending marker
        @unlink($pendingFile);

        return $this->jsonResponse($response, [
            'message' => 'Backup created successfully',
            'file' => $backupFilename,
            'status' => 'ready'
        ]);
    }

    /**
     * Recursively add files to ZIP archive
     */
    private function addFilesToZip(\ZipArchive $zip, string $source, string $prefix = '', array $exclude = []): void
    {
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($source, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($iterator as $file) {
            $filePath = $file->getRealPath();
            $relativePath = substr($filePath, strlen($source) + 1);

            // Skip excluded directories
            $skipThis = false;
            foreach ($exclude as $excludeDir) {
                if (strpos($relativePath, $excludeDir) === 0) {
                    $skipThis = true;
                    break;
                }
            }
            if ($skipThis)
                continue;

            $localPath = $prefix ? $prefix . '/' . $relativePath : $relativePath;

            if ($file->isDir()) {
                $zip->addEmptyDir($localPath);
            } else {
                $zip->addFile($filePath, $localPath);
            }
        }
    }

    /**
     * Delete a backup file
     */
    public function delete(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $userId = $request->getAttribute('userId');
        $filename = basename($args['filename']); // Security: prevent directory traversal

        // Find the backup file across user's services
        $services = Service::where('user_id', $userId)->get();

        foreach ($services as $service) {
            $backupPath = $this->userAppsPath . "/service_{$service->id}/backup/{$filename}";

            if (file_exists($backupPath)) {
                unlink($backupPath);
                return $this->jsonResponse($response, ['message' => 'Backup deleted']);
            }
        }

        return $this->jsonResponse($response, ['error' => 'Backup not found'], 404);
    }

    /**
     * Download a backup file
     */
    public function download(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $userId = $request->getAttribute('userId');
        $filename = basename($args['filename']);

        // Find the backup file across user's services
        $services = Service::where('user_id', $userId)->get();

        foreach ($services as $service) {
            $backupPath = $this->userAppsPath . "/service_{$service->id}/backup/{$filename}";

            if (file_exists($backupPath)) {
                $fh = fopen($backupPath, 'rb');
                $stream = new \Slim\Psr7\Stream($fh);

                return $response
                    ->withHeader('Content-Type', 'application/zip')
                    ->withHeader('Content-Disposition', 'attachment; filename="' . $filename . '"')
                    ->withHeader('Content-Length', (string) filesize($backupPath))
                    ->withBody($stream);
            }
        }

        return $this->jsonResponse($response, ['error' => 'Backup not found'], 404);
    }

    /**
     * Restore a backup
     */
    public function restore(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $userId = $request->getAttribute('userId');
        $data = $request->getParsedBody();
        $filename = basename($data['filename'] ?? '');
        $targetServiceId = (int) ($data['service_id'] ?? 0);

        if (!$filename) {
            return $this->jsonResponse($response, ['error' => 'Filename required'], 400);
        }

        // Find the backup file
        $services = Service::where('user_id', $userId)->get();
        $backupPath = null;
        $sourceServiceId = null;

        foreach ($services as $service) {
            $path = $this->userAppsPath . "/service_{$service->id}/backup/{$filename}";
            if (file_exists($path)) {
                $backupPath = $path;
                $sourceServiceId = $service->id;
                break;
            }
        }

        if (!$backupPath) {
            return $this->jsonResponse($response, ['error' => 'Backup not found'], 404);
        }

        // Determine target service (same as source if not specified)
        $targetServiceId = $targetServiceId ?: $sourceServiceId;

        // Verify user owns target service
        $targetService = Service::where('id', $targetServiceId)
            ->where('user_id', $userId)
            ->first();

        if (!$targetService) {
            return $this->jsonResponse($response, ['error' => 'Target service not found'], 404);
        }

        $appDir = $this->userAppsPath . "/service_{$targetServiceId}";

        // Extract backup (overwrite existing files, but preserve backup folder)
        $zip = new \ZipArchive();
        if ($zip->open($backupPath) === TRUE) {
            $zip->extractTo($appDir);
            $zip->close();
            return $this->jsonResponse($response, ['message' => 'Backup restored successfully']);
        }

        return $this->jsonResponse($response, ['error' => 'Failed to extract backup'], 500);
    }

    /**
     * Stub for DB backup (not implemented for user panel)
     */
    public function createDbBackup(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        return $this->jsonResponse($response, ['error' => 'Database backups are managed through Adminer'], 400);
    }

    private function formatSize($bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        $bytes /= pow(1024, $pow);
        return round($bytes, 2) . ' ' . $units[$pow];
    }

    private function jsonResponse(ResponseInterface $response, array $data, int $status = 200): ResponseInterface
    {
        $response->getBody()->write(json_encode($data));
        return $response
            ->withStatus($status)
            ->withHeader('Content-Type', 'application/json');
    }
}
