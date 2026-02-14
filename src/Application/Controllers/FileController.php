<?php

declare(strict_types=1);

namespace LogicPanel\Application\Controllers;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use LogicPanel\Domain\Service\Service;

class FileController
{
    private string $userAppsPath;
    private int $maxFileSize;
    private array $allowedExtensions;

    public function __construct(array $config)
    {
        $this->userAppsPath = $config['user_apps_path'];
        $this->maxFileSize = $config['max_size'];
        $this->allowedExtensions = $config['allowed_extensions'];
    }

    public function list(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        try {
            $userId = $request->getAttribute('userId');
            $serviceId = (int) $args['serviceId'];
            $path = $request->getQueryParams()['path'] ?? '/';

            // Verify service ownership
            $service = Service::where('id', $serviceId)
                ->where('user_id', $userId)
                ->first();

            if (!$service) {
                return $this->jsonResponse($response, ['error' => 'Service not found or access denied'], 404);
            }

            $basePath = $this->userAppsPath . "/service_{$serviceId}";
            $fullPath = $this->sanitizePath($basePath, $path);

            if (!$this->isPathSafe($basePath, $fullPath)) {
                return $this->jsonResponse($response, ['error' => 'Invalid path security check failed'], 400);
            }

            if (!file_exists($fullPath)) {
                // Try to create the directory if it's the root and missing (common first run issue)
                if ($path === '/' && !is_dir($fullPath)) {
                    try {
                        if (!mkdir($fullPath, 0777, true)) {
                            return $this->jsonResponse($response, ['error' => "Root directory not found and could not be created: $fullPath"], 404);
                        }
                    } catch (\Throwable $e) {
                        return $this->jsonResponse($response, ['error' => "Root directory missing and create failed: " . $e->getMessage()], 500);
                    }
                } else {
                    return $this->jsonResponse($response, ['error' => "Directory not found: $path"], 404);
                }
            }

            if (!is_dir($fullPath)) {
                return $this->jsonResponse($response, ['error' => "Path is not a directory: $path"], 400);
            }

            $files = [];
            $items = scandir($fullPath);

            if ($items === false) {
                return $this->jsonResponse($response, ['error' => "Failed to scan directory: $path"], 500);
            }

            foreach ($items as $item) {
                if ($item === '.' || $item === '..') {
                    continue;
                }

                $itemPath = $fullPath . '/' . $item;
                // Use relative path for frontend
                $relPath = ($path === '/') ? $item : ltrim($path, '/') . '/' . $item;
                if ($path !== '/')
                    $relPath = '/' . $relPath;
                else
                    $relPath = '/' . $item;

                $isDir = is_dir($itemPath);

                // Calculate size - for directories, get total size of contents
                $size = 0;
                if ($isDir) {
                    $size = $this->getDirectorySize($itemPath);
                } else {
                    $size = filesize($itemPath);
                }

                // Get file permissions
                $perms = @fileperms($itemPath);
                $permsOctal = $perms !== false ? substr(sprintf('%o', $perms), -4) : '0000';

                $files[] = [
                    'name' => $item,
                    'path' => $relPath,
                    'type' => $isDir ? 'directory' : 'file',
                    'size' => $size,
                    'modified' => filemtime($itemPath),
                    'perms' => $permsOctal,
                ];
            }

            return $this->jsonResponse($response, [
                'path' => $path,
                'files' => $files,
            ]);
        } catch (\Throwable $e) {
            // Log the error for debugging
            $logFile = sys_get_temp_dir() . '/logicpanel_file_error.log';
            file_put_contents($logFile, date('Y-m-d H:i:s') . ' - ' . $e->getMessage() . "\n" . $e->getTraceAsString() . "\n", FILE_APPEND);

            return $this->jsonResponse($response, ['error' => 'Internal Server Error: ' . $e->getMessage()], 500);
        }
    }

    public function read(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $userId = $request->getAttribute('userId');
        $serviceId = (int) $args['serviceId'];
        $path = $request->getQueryParams()['path'] ?? '';

        // Verify service ownership
        $service = Service::where('id', $serviceId)
            ->where('user_id', $userId)
            ->first();

        if (!$service) {
            return $this->jsonResponse($response, ['error' => 'Service not found'], 404);
        }

        $basePath = $this->userAppsPath . "/service_{$serviceId}";
        $fullPath = $this->sanitizePath($basePath, $path);

        if (!$this->isPathSafe($basePath, $fullPath)) {
            return $this->jsonResponse($response, ['error' => 'Invalid path'], 400);
        }

        if (!is_file($fullPath)) {
            return $this->jsonResponse($response, ['error' => 'File not found'], 404);
        }

        $content = file_get_contents($fullPath);
        $extension = pathinfo($fullPath, PATHINFO_EXTENSION);

        return $this->jsonResponse($response, [
            'path' => $path,
            'filename' => basename($fullPath),
            'content' => $content,
            'extension' => $extension,
            'size' => strlen($content),
        ]);
    }

    public function download(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $userId = $request->getAttribute('userId');
        $serviceId = (int) $args['serviceId'];
        $path = $request->getQueryParams()['path'] ?? '';

        // Verify service ownership
        $service = Service::where('id', $serviceId)
            ->where('user_id', $userId)
            ->first();

        if (!$service) {
            return $this->jsonResponse($response, ['error' => 'Service not found'], 404);
        }

        $basePath = $this->userAppsPath . "/service_{$serviceId}";
        $fullPath = $this->sanitizePath($basePath, $path);

        if (!$this->isPathSafe($basePath, $fullPath)) {
            return $this->jsonResponse($response, ['error' => 'Invalid path'], 400);
        }

        if (!is_file($fullPath)) {
            return $this->jsonResponse($response, ['error' => 'File not found'], 404);
        }

        $content = file_get_contents($fullPath);
        $filename = basename($fullPath);

        $response->getBody()->write($content);
        return $response
            ->withHeader('Content-Type', 'application/octet-stream')
            ->withHeader('Content-Disposition', "attachment; filename=\"{$filename}\"")
            ->withHeader('Content-Length', (string) strlen($content));
    }

    public function upload(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $userId = $request->getAttribute('userId');
        $serviceId = (int) $args['serviceId'];

        // Verify service ownership
        $service = Service::where('id', $serviceId)
            ->where('user_id', $userId)
            ->first();

        if (!$service) {
            return $this->jsonResponse($response, ['error' => 'Service not found'], 404);
        }

        $uploadedFiles = $request->getUploadedFiles();
        $parsedBody = $request->getParsedBody();
        $targetPath = $parsedBody['path'] ?? '/';

        if (empty($uploadedFiles['file'])) {
            return $this->jsonResponse($response, ['error' => 'No file uploaded'], 400);
        }

        $uploadedFile = $uploadedFiles['file'];

        if ($uploadedFile->getError() !== UPLOAD_ERR_OK) {
            return $this->jsonResponse($response, ['error' => 'Upload error'], 400);
        }

        // Check file size
        if ($uploadedFile->getSize() > $this->maxFileSize) {
            return $this->jsonResponse($response, ['error' => 'File too large'], 400);
        }

        // Check file extension
        $filename = $uploadedFile->getClientFilename();
        $extension = pathinfo($filename, PATHINFO_EXTENSION);
        if (!in_array(strtolower($extension), $this->allowedExtensions)) {
            return $this->jsonResponse($response, ['error' => 'File type not allowed: ' . $extension], 400);
        }

        $basePath = $this->userAppsPath . "/service_{$serviceId}";
        $fullPath = $this->sanitizePath($basePath, $targetPath . '/' . $filename);

        if (!$this->isPathSafe($basePath, dirname($fullPath))) {
            return $this->jsonResponse($response, ['error' => 'Invalid path'], 400);
        }

        try {
            // Ensure directory exists
            $dir = dirname($fullPath);
            if (!is_dir($dir)) {
                mkdir($dir, 0755, true);
            }

            $uploadedFile->moveTo($fullPath);

            return $this->jsonResponse($response, [
                'message' => 'File uploaded successfully',
                'filename' => $filename,
                'path' => $targetPath . '/' . $filename,
            ]);

        } catch (\Exception $e) {
            return $this->jsonResponse($response, [
                'error' => 'Failed to upload file',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    public function update(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $userId = $request->getAttribute('userId');
        $serviceId = (int) $args['serviceId'];
        $data = $request->getParsedBody();

        $path = $data['path'] ?? '';
        $content = $data['content'] ?? '';

        // Verify service ownership
        $service = Service::where('id', $serviceId)
            ->where('user_id', $userId)
            ->first();

        if (!$service) {
            return $this->jsonResponse($response, ['error' => 'Service not found'], 404);
        }

        $basePath = $this->userAppsPath . "/service_{$serviceId}";
        $fullPath = $this->sanitizePath($basePath, $path);

        if (!$this->isPathSafe($basePath, $fullPath)) {
            return $this->jsonResponse($response, ['error' => 'Invalid path'], 400);
        }

        // Check file extension
        $extension = pathinfo($fullPath, PATHINFO_EXTENSION);
        if (!in_array($extension, $this->allowedExtensions)) {
            return $this->jsonResponse($response, ['error' => 'File type not allowed'], 400);
        }

        // Check file size
        if (strlen($content) > $this->maxFileSize) {
            return $this->jsonResponse($response, ['error' => 'File too large'], 400);
        }

        try {
            // Create directory if it doesn't exist
            $dir = dirname($fullPath);
            if (!is_dir($dir)) {
                mkdir($dir, 0755, true);
            }

            file_put_contents($fullPath, $content);

            return $this->jsonResponse($response, [
                'message' => 'File updated successfully',
                'path' => $path,
            ]);

        } catch (\Exception $e) {
            return $this->jsonResponse($response, [
                'error' => 'Failed to update file',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    public function delete(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $userId = $request->getAttribute('userId');
        $serviceId = (int) $args['serviceId'];
        $data = $request->getParsedBody();

        $items = $data['items'] ?? [];
        if (empty($items) && isset($data['path'])) {
            $items = [$data['path']];
        }

        if (empty($items)) {
            return $this->jsonResponse($response, ['error' => 'No items specified'], 400);
        }

        // Check if permanent delete or move to trash
        $permanent = $data['permanent'] ?? false;

        $service = Service::where('id', $serviceId)->where('user_id', $userId)->first();
        if (!$service) {
            return $this->jsonResponse($response, ['error' => 'Service not found'], 404);
        }

        $basePath = $this->userAppsPath . "/service_{$serviceId}";
        $trashPath = $basePath . '/.trash';

        // Create trash directory if it doesn't exist
        if (!$permanent && !is_dir($trashPath)) {
            mkdir($trashPath, 0755, true);
        }

        $successCount = 0;
        $errors = [];

        foreach ($items as $path) {
            if ($path === '/' || empty($path))
                continue; // Skip root

            $fullPath = $this->sanitizePath($basePath, $path);

            if (!$this->isPathSafe($basePath, $fullPath)) {
                $errors[] = "$path: Invalid path";
                continue;
            }

            if (!file_exists($fullPath)) {
                $errors[] = "$path: Not found";
                continue;
            }

            try {
                if ($permanent) {
                    // Permanent delete
                    if (is_dir($fullPath)) {
                        if ($this->recursiveDelete($fullPath)) {
                            $successCount++;
                        } else {
                            $errors[] = "$path: Failed to delete directory";
                        }
                    } else {
                        if (unlink($fullPath)) {
                            $successCount++;
                        } else {
                            $errors[] = "$path: Failed to delete file";
                        }
                    }
                } else {
                    // Move to trash
                    $fileName = basename($fullPath);
                    $trashItemPath = $trashPath . '/' . time() . '_' . $fileName;

                    // Store original path in a metadata file
                    $metaFile = $trashItemPath . '.meta';
                    file_put_contents($metaFile, json_encode([
                        'originalPath' => $path,
                        'deletedAt' => date('Y-m-d H:i:s'),
                        'type' => is_dir($fullPath) ? 'directory' : 'file'
                    ]));

                    if (rename($fullPath, $trashItemPath)) {
                        $successCount++;
                    } else {
                        // Remove meta file if move failed
                        if (file_exists($metaFile))
                            unlink($metaFile);
                        $errors[] = "$path: Failed to move to trash";
                    }
                }
            } catch (\Exception $e) {
                $errors[] = "$path: " . $e->getMessage();
            }
        }

        if ($successCount === 0 && count($errors) > 0) {
            return $this->jsonResponse($response, ['error' => 'Failed to delete items', 'details' => $errors], 500);
        }

        $message = $permanent ? "Permanently deleted $successCount items" : "Moved $successCount items to trash";
        return $this->jsonResponse($response, ['message' => $message, 'errors' => $errors, 'successCount' => $successCount]);
    }

    /**
     * List items in trash
     */
    public function listTrash(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $userId = $request->getAttribute('userId');
        $serviceId = (int) $args['serviceId'];

        $service = Service::where('id', $serviceId)->where('user_id', $userId)->first();
        if (!$service) {
            return $this->jsonResponse($response, ['error' => 'Service not found'], 404);
        }

        $basePath = $this->userAppsPath . "/service_{$serviceId}";
        $trashPath = $basePath . '/.trash';

        if (!is_dir($trashPath)) {
            return $this->jsonResponse($response, ['files' => []]);
        }

        $items = [];
        $files = scandir($trashPath);

        foreach ($files as $file) {
            if ($file === '.' || $file === '..' || str_ends_with($file, '.meta')) {
                continue;
            }

            $fullPath = $trashPath . '/' . $file;
            $metaFile = $fullPath . '.meta';

            $meta = [
                'originalPath' => 'Unknown',
                'deletedAt' => date('Y-m-d H:i:s', filemtime($fullPath)),
                'type' => is_dir($fullPath) ? 'directory' : 'file'
            ];

            if (file_exists($metaFile)) {
                $meta = json_decode(file_get_contents($metaFile), true) ?? $meta;
            }

            $items[] = [
                'trashName' => $file,
                'name' => preg_replace('/^\d+_/', '', $file), // Remove timestamp prefix
                'originalPath' => $meta['originalPath'],
                'deletedAt' => $meta['deletedAt'],
                'type' => $meta['type'],
                'size' => is_file($fullPath) ? filesize($fullPath) : 0
            ];
        }

        return $this->jsonResponse($response, ['files' => $items]);
    }

    /**
     * Restore item from trash
     */
    public function restoreFromTrash(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $userId = $request->getAttribute('userId');
        $serviceId = (int) $args['serviceId'];
        $data = $request->getParsedBody();

        $trashName = $data['trashName'] ?? '';
        if (empty($trashName)) {
            return $this->jsonResponse($response, ['error' => 'Trash item name required'], 400);
        }

        $service = Service::where('id', $serviceId)->where('user_id', $userId)->first();
        if (!$service) {
            return $this->jsonResponse($response, ['error' => 'Service not found'], 404);
        }

        $basePath = $this->userAppsPath . "/service_{$serviceId}";
        $trashPath = $basePath . '/.trash';
        $trashItemPath = $trashPath . '/' . $trashName;
        $metaFile = $trashItemPath . '.meta';

        if (!file_exists($trashItemPath)) {
            return $this->jsonResponse($response, ['error' => 'Trash item not found'], 404);
        }

        // Get original path from metadata
        $originalPath = null;
        if (file_exists($metaFile)) {
            $meta = json_decode(file_get_contents($metaFile), true);
            $originalPath = $meta['originalPath'] ?? null;
        }

        if (!$originalPath) {
            return $this->jsonResponse($response, ['error' => 'Cannot restore: original path unknown'], 400);
        }

        $restorePath = $this->sanitizePath($basePath, $originalPath);

        // Check if a file already exists at restore location
        if (file_exists($restorePath)) {
            // Add suffix to avoid overwriting
            $pathInfo = pathinfo($restorePath);
            $suffix = '_restored_' . time();
            if (is_dir($trashItemPath)) {
                $restorePath = $pathInfo['dirname'] . '/' . $pathInfo['filename'] . $suffix;
            } else {
                $restorePath = $pathInfo['dirname'] . '/' . $pathInfo['filename'] . $suffix . '.' . ($pathInfo['extension'] ?? '');
            }
        }

        // Ensure parent directory exists
        $parentDir = dirname($restorePath);
        if (!is_dir($parentDir)) {
            mkdir($parentDir, 0755, true);
        }

        if (rename($trashItemPath, $restorePath)) {
            // Remove metadata file
            if (file_exists($metaFile)) {
                unlink($metaFile);
            }

            $relativePath = str_replace($basePath, '', $restorePath);
            return $this->jsonResponse($response, [
                'message' => 'Item restored successfully',
                'restoredTo' => $relativePath
            ]);
        }

        return $this->jsonResponse($response, ['error' => 'Failed to restore item'], 500);
    }

    /**
     * Empty trash
     */
    public function emptyTrash(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $userId = $request->getAttribute('userId');
        $serviceId = (int) $args['serviceId'];

        $service = Service::where('id', $serviceId)->where('user_id', $userId)->first();
        if (!$service) {
            return $this->jsonResponse($response, ['error' => 'Service not found'], 404);
        }

        $basePath = $this->userAppsPath . "/service_{$serviceId}";
        $trashPath = $basePath . '/.trash';

        if (!is_dir($trashPath)) {
            return $this->jsonResponse($response, ['message' => 'Trash is already empty']);
        }

        $deletedCount = 0;
        $files = scandir($trashPath);

        foreach ($files as $file) {
            if ($file === '.' || $file === '..')
                continue;

            $fullPath = $trashPath . '/' . $file;
            if (is_dir($fullPath)) {
                $this->recursiveDelete($fullPath);
            } else {
                unlink($fullPath);
            }
            $deletedCount++;
        }

        return $this->jsonResponse($response, ['message' => "Emptied trash ($deletedCount items deleted)"]);
    }

    private function recursiveDelete(string $path): bool
    {
        if (is_dir($path)) {
            $items = scandir($path);
            foreach ($items as $item) {
                if ($item === '.' || $item === '..') {
                    continue;
                }
                if (!$this->recursiveDelete($path . '/' . $item)) {
                    return false;
                }
            }
            return rmdir($path);
        }
        return unlink($path);
    }


    public function mkdir(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $userId = $request->getAttribute('userId');
        $serviceId = (int) $args['serviceId'];
        $data = $request->getParsedBody();

        $path = $data['path'] ?? '';

        // Verify service ownership
        $service = Service::where('id', $serviceId)
            ->where('user_id', $userId)
            ->first();

        if (!$service) {
            return $this->jsonResponse($response, ['error' => 'Service not found'], 404);
        }

        $basePath = $this->userAppsPath . "/service_{$serviceId}";
        $fullPath = $this->sanitizePath($basePath, $path);

        if (!$this->isPathSafe($basePath, $fullPath)) {
            return $this->jsonResponse($response, ['error' => 'Invalid path'], 400);
        }

        try {
            mkdir($fullPath, 0755, true);

            return $this->jsonResponse($response, [
                'message' => 'Directory created successfully',
                'path' => $path,
            ]);

        } catch (\Exception $e) {
            return $this->jsonResponse($response, [
                'error' => 'Failed to create directory',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    public function extract(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $userId = $request->getAttribute('userId');
        $serviceId = (int) $args['serviceId'];
        $data = $request->getParsedBody();
        $path = $data['path'] ?? '';

        // Target directory: current directory of the archive
        $destination = dirname($path);

        $service = Service::where('id', $serviceId)
            ->where('user_id', $userId)
            ->first();

        if (!$service) {
            return $this->jsonResponse($response, ['error' => 'Service not found'], 404);
        }

        $basePath = $this->userAppsPath . "/service_{$serviceId}";
        $fullPath = $this->sanitizePath($basePath, $path);

        // Ensure destination is safe (it's the same dir as the file, so it should be if the file is)
        $fullDestPath = dirname($fullPath);

        if (!$this->isPathSafe($basePath, $fullPath)) {
            return $this->jsonResponse($response, ['error' => 'Invalid path security violation'], 400);
        }

        if (!file_exists($fullPath)) {
            return $this->jsonResponse($response, ['error' => 'Archive file not found'], 404);
        }

        $ext = strtolower(pathinfo($fullPath, PATHINFO_EXTENSION));

        try {
            if ($ext === 'zip') {
                $zip = new \ZipArchive;
                if ($zip->open($fullPath) === TRUE) {
                    $zip->extractTo($fullDestPath);
                    $zip->close();
                } else {
                    return $this->jsonResponse($response, ['error' => 'Failed to open ZIP file'], 500);
                }
            } elseif ($ext === 'tar' || ($ext === 'gz' && str_ends_with($path, '.tar.gz'))) {
                try {
                    $phar = new \PharData($fullPath);
                    $phar->extractTo($fullDestPath, null, true); // true = allow overwrite
                } catch (\Exception $e) {
                    return $this->jsonResponse($response, ['error' => 'Failed to extract TAR/GZ: ' . $e->getMessage()], 500);
                }
            } else {
                return $this->jsonResponse($response, ['error' => "Unsupported archive format: .$ext"], 400);
            }

            return $this->jsonResponse($response, [
                'message' => 'Extracted successfully',
                'path' => $path
            ]);

        } catch (\Exception $e) {
            return $this->jsonResponse($response, ['error' => 'Extraction failed: ' . $e->getMessage()], 500);
        }
    }

    public function copy(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        return $this->processBulkAction($request, $response, $args, 'copy');
    }

    public function move(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        return $this->processBulkAction($request, $response, $args, 'move');
    }

    /**
     * Rename a file or folder
     */
    public function rename(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        try {
            $userId = $request->getAttribute('userId');
            $serviceId = (int) $args['serviceId'];
            $data = $request->getParsedBody();
            $oldPath = $data['oldPath'] ?? '';
            $newName = $data['newName'] ?? '';

            if (empty($oldPath) || empty($newName)) {
                return $this->jsonResponse($response, ['error' => 'oldPath and newName are required'], 400);
            }

            // Validate newName (no slashes, no special directory traversal)
            if (preg_match('/[\/\\\\]/', $newName) || $newName === '.' || $newName === '..') {
                return $this->jsonResponse($response, ['error' => 'Invalid new name'], 400);
            }

            $service = Service::where('id', $serviceId)->where('user_id', $userId)->first();
            if (!$service) {
                return $this->jsonResponse($response, ['error' => 'Service not found'], 404);
            }

            $basePath = $this->userAppsPath . "/service_{$serviceId}";
            $fullOldPath = $this->sanitizePath($basePath, $oldPath);

            if (!$this->isPathSafe($basePath, $fullOldPath)) {
                return $this->jsonResponse($response, ['error' => 'Invalid old path'], 400);
            }

            if (!file_exists($fullOldPath)) {
                return $this->jsonResponse($response, ['error' => 'File or folder not found'], 404);
            }

            // Build new path
            $parentDir = dirname($fullOldPath);
            $fullNewPath = $parentDir . '/' . $newName;

            if (!$this->isPathSafe($basePath, $fullNewPath)) {
                return $this->jsonResponse($response, ['error' => 'Invalid new path'], 400);
            }

            if (file_exists($fullNewPath)) {
                return $this->jsonResponse($response, ['error' => 'A file or folder with that name already exists'], 409);
            }

            if (!rename($fullOldPath, $fullNewPath)) {
                return $this->jsonResponse($response, ['error' => 'Rename failed'], 500);
            }

            // Calculate relative new path for response
            $relativeNewPath = str_replace($basePath, '', $fullNewPath);

            return $this->jsonResponse($response, [
                'message' => 'Renamed successfully',
                'oldPath' => $oldPath,
                'newPath' => $relativeNewPath
            ]);

        } catch (\Exception $e) {
            return $this->jsonResponse($response, ['error' => 'Rename failed: ' . $e->getMessage()], 500);
        }
    }

    private function processBulkAction(ServerRequestInterface $request, ResponseInterface $response, array $args, string $action): ResponseInterface
    {
        $userId = $request->getAttribute('userId');
        $serviceId = (int) $args['serviceId'];
        $data = $request->getParsedBody();
        $items = $data['items'] ?? []; // Array of paths
        $destination = $data['destination'] ?? ''; // Destination folder path

        if (empty($items) || !is_array($items)) {
            return $this->jsonResponse($response, ['error' => 'No items selected'], 400);
        }

        $service = Service::where('id', $serviceId)->where('user_id', $userId)->first();
        if (!$service) {
            return $this->jsonResponse($response, ['error' => 'Service not found'], 404);
        }

        $basePath = $this->userAppsPath . "/service_{$serviceId}";
        $fullDestDir = $this->sanitizePath($basePath, $destination);

        // Ensure destination exists
        if (!is_dir($fullDestDir)) {
            return $this->jsonResponse($response, ['error' => 'Destination directory not found'], 404);
        }

        if (!$this->isPathSafe($basePath, $fullDestDir)) {
            return $this->jsonResponse($response, ['error' => 'Invalid destination path'], 400);
        }

        $successCount = 0;
        $errors = [];

        foreach ($items as $itemPath) {
            $fullSrcPath = $this->sanitizePath($basePath, $itemPath);
            $fileName = basename($fullSrcPath);
            $fullDestPath = $fullDestDir . '/' . $fileName;

            if (!$this->isPathSafe($basePath, $fullSrcPath)) {
                $errors[] = "$itemPath: Security violation";
                continue;
            }

            if (!file_exists($fullSrcPath)) {
                $errors[] = "$itemPath: Not found";
                continue;
            }

            if ($fullSrcPath === $fullDestPath) {
                $errors[] = "$itemPath: Source and destination are the same";
                continue;
            }

            // Check if destination exists
            if (file_exists($fullDestPath)) {
                // For now, simple error. Later could support auto-rename or overwrite flag
                $errors[] = "$fileName: Already exists in destination";
                continue;
            }

            try {
                if ($action === 'move') {
                    if (rename($fullSrcPath, $fullDestPath)) {
                        $successCount++;
                    } else {
                        $errors[] = "$itemPath: Move failed";
                    }
                } elseif ($action === 'copy') {
                    if (is_dir($fullSrcPath)) {
                        if ($this->recursiveCopy($fullSrcPath, $fullDestPath)) {
                            $successCount++;
                        } else {
                            $errors[] = "$itemPath: Copy failed";
                        }
                    } else {
                        if (copy($fullSrcPath, $fullDestPath)) {
                            $successCount++;
                        } else {
                            $errors[] = "$itemPath: Copy failed";
                        }
                    }
                }
            } catch (\Exception $e) {
                $errors[] = "$itemPath: " . $e->getMessage();
            }
        }

        return $this->jsonResponse($response, [
            'message' => ucfirst($action) . " completed. Success: $successCount, Failed: " . count($errors),
            'errors' => $errors,
            'successCount' => $successCount
        ], count($errors) > 0 && $successCount === 0 ? 500 : 200);
    }

    private function recursiveCopy(string $src, string $dst): bool
    {
        $dir = opendir($src);
        if (!$dir)
            return false;

        if (!is_dir($dst)) {
            mkdir($dst, 0755, true);
        }

        while (($file = readdir($dir)) !== false) {
            if (($file != '.') && ($file != '..')) {
                if (is_dir($src . '/' . $file)) {
                    $this->recursiveCopy($src . '/' . $file, $dst . '/' . $file);
                } else {
                    copy($src . '/' . $file, $dst . '/' . $file);
                }
            }
        }
        closedir($dir);
        return true;
    }

    private function sanitizePath(string $basePath, string $path): string
    {
        // Normalize slashes
        $path = str_replace('\\', '/', $path);

        // Remove null bytes
        $path = str_replace("\0", '', $path);

        // Explicitly block traversal attempts in the string itself for extra safety
        if (strpos($path, '../') !== false || strpos($path, '..\\') !== false) {
            // We return a path that will definitely fail isPathSafe/file check logic, 
            // but we strip it just in case. 
            // Ideally we should throw here, but following the pattern we'll just sanitize aggressively.
            $path = str_replace(['../', '..\\'], '', $path);
        }

        $path = ltrim($path, '/');

        // Prevent empty path resulting in base directory access if not intended? 
        // No, accessing root '/' is valid.

        $fullPath = $basePath . '/' . $path;

        // Normalize full path slashes
        return str_replace(['\\', '//'], '/', $fullPath);
    }

    private function isPathSafe(string $basePath, string $fullPath): bool
    {
        $realBasePath = realpath($basePath);

        // If the base path itself doesn't exist, nothing is safe
        if ($realBasePath === false) {
            return false;
        }

        $realFullPath = realpath($fullPath);

        // Standardize slashes for Windows compatibility
        $realBasePath = str_replace('\\', '/', $realBasePath);

        if ($realFullPath) {
            $realFullPath = str_replace('\\', '/', $realFullPath);
        }


        // If path doesn't exist yet (e.g. new file), allow if parent is safe
        if (!$realFullPath) {
            // For new file check parent
            $parent = dirname($fullPath);
            $realParent = realpath($parent);
            if ($realParent) {
                $realParent = str_replace('\\', '/', $realParent);
                // Parent must be inside base path
                return str_starts_with($realParent, $realBasePath);
            }
            // If parent also doesn't exist, unsafe (unless recursive create, but we limit depth creation implicitly)
            return false;
        }

        // Ensure the path is within the base path
        // We append a trailing slash to base path to prevent partial matches 
        // (e.g. /var/www/site vs /var/www/site_backup) unless it's exact match
        if ($realBasePath === $realFullPath) {
            return true;
        }

        // Ensure $realBasePath ends with slash for strict prefix check
        $baseWithSlash = rtrim($realBasePath, '/') . '/';

        return str_starts_with($realFullPath, $baseWithSlash);
    }

    private function jsonResponse(ResponseInterface $response, array $data, int $status = 200): ResponseInterface
    {
        // Clear any previous output (e.g. warnings/notices)
        if (ob_get_length()) {
            ob_clean();
        }

        $response->getBody()->write(json_encode($data));
        return $response
            ->withStatus($status)
            ->withHeader('Content-Type', 'application/json');
    }

    /**
     * Calculate the total size of a directory (recursive)
     * Uses a non-recursive approach with a level limit to avoid performance issues on huge directories
     */
    private function getDirectorySize(string $path, int $maxDepth = 3): int
    {
        $totalSize = 0;

        if (!is_dir($path)) {
            return 0;
        }

        try {
            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($path, \RecursiveDirectoryIterator::SKIP_DOTS),
                \RecursiveIteratorIterator::SELF_FIRST
            );

            // Limit depth to avoid performance issues
            $iterator->setMaxDepth($maxDepth);

            foreach ($iterator as $file) {
                if ($file->isFile()) {
                    $totalSize += $file->getSize();
                }
            }
        } catch (\Exception $e) {
            // If we can't read the directory, return 0
            return 0;
        }

        return $totalSize;
    }

    /**
     * Change file/folder permissions (chmod)
     */
    public function chmod(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        try {
            $userId = $request->getAttribute('userId');
            $serviceId = (int) $args['serviceId'];
            $data = $request->getParsedBody();

            $path = $data['path'] ?? '';
            $mode = $data['mode'] ?? '';
            $recursive = $data['recursive'] ?? false;

            if (empty($path) || empty($mode)) {
                return $this->jsonResponse($response, ['error' => 'Path and mode are required'], 400);
            }

            // Validate mode format (octal like 0755, 755, 0644, etc.)
            if (!preg_match('/^[0-7]{3,4}$/', $mode)) {
                return $this->jsonResponse($response, ['error' => 'Invalid permission mode. Use format like 755 or 0755'], 400);
            }

            $service = Service::where('id', $serviceId)->where('user_id', $userId)->first();
            if (!$service) {
                return $this->jsonResponse($response, ['error' => 'Service not found'], 404);
            }

            $basePath = $this->userAppsPath . "/service_{$serviceId}";
            $fullPath = $this->sanitizePath($basePath, $path);

            if (!$this->isPathSafe($basePath, $fullPath)) {
                return $this->jsonResponse($response, ['error' => 'Invalid path'], 400);
            }

            if (!file_exists($fullPath)) {
                return $this->jsonResponse($response, ['error' => 'File or folder not found'], 404);
            }

            // Convert to octal
            $octalMode = octdec($mode);

            if ($recursive && is_dir($fullPath)) {
                // Recursive chmod
                $this->recursiveChmod($fullPath, $octalMode);
            } else {
                // Single file/folder chmod
                if (!chmod($fullPath, $octalMode)) {
                    return $this->jsonResponse($response, ['error' => 'Failed to change permissions'], 500);
                }
            }

            return $this->jsonResponse($response, [
                'message' => 'Permissions updated successfully',
                'path' => $path,
                'mode' => $mode
            ]);

        } catch (\Exception $e) {
            return $this->jsonResponse($response, ['error' => 'Failed to change permissions: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Recursively change permissions
     */
    private function recursiveChmod(string $path, int $mode): void
    {
        chmod($path, $mode);

        if (is_dir($path)) {
            $items = scandir($path);
            foreach ($items as $item) {
                if ($item === '.' || $item === '..')
                    continue;
                $this->recursiveChmod($path . '/' . $item, $mode);
            }
        }
    }
}
