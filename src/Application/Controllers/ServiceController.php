<?php

declare(strict_types=1);

namespace LogicPanel\Application\Controllers;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use LogicPanel\Domain\Service\Service;
use LogicPanel\Domain\User\User;
use LogicPanel\Domain\Domain\Domain;
use LogicPanel\Infrastructure\Docker\DockerService;
use Firebase\JWT\JWT;

class ServiceController
{
    private DockerService $dockerService;

    public function __construct(DockerService $dockerService)
    {
        $this->dockerService = $dockerService;
    }

    public function index(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        try {
            $userId = $request->getAttribute('userId');
            $queryParams = $request->getQueryParams();

            $page = (int) ($queryParams['page'] ?? 1);
            $perPage = (int) ($queryParams['per_page'] ?? 15);
            if ($perPage > 100)
                $perPage = 100; // Cap per page

            $query = Service::where('user_id', $userId);

            $total = $query->count();
            $services = $query->skip(($page - 1) * $perPage)
                ->take($perPage)
                ->get();

            // Sync status with Docker (with error handling)
            $services->transform(function ($service) {
                if ($service->container_id) {
                    try {
                        $isRunning = $this->dockerService->isContainerRunning($service->container_id);
                        $newStatus = $isRunning ? 'running' : 'stopped';

                        // Only update DB if status changed
                        if ($service->status !== $newStatus && $service->status !== 'creating' && $service->status !== 'error') {
                            $service->status = $newStatus;
                            $service->save();
                        }
                    } catch (\Exception $e) {
                        // Log error but don't fail the entire request
                        error_log("Failed to check container status for service {$service->id}: " . $e->getMessage());
                        // Keep existing status
                    }
                }
                return $service;
            });

            return $this->jsonResponse($response, [
                'services' => $services->map(function ($service) {
                    // Handle comma-separated domains
                    $domains = explode(',', $service->domain);
                    $primaryDomain = trim($domains[0] ?? '');

                    return [
                        'id' => $service->id,
                        'name' => $service->name,
                        'domain' => $service->domain,
                        'url' => 'https://' . $primaryDomain,
                        'type' => $service->type,
                        'status' => $service->status,
                        'port' => $service->port > 0 ? $service->port : ($service->type === 'python' ? 5000 : 3000),
                        'container_id' => $service->container_id,
                        'version' => $service->runtime_version,
                        'created_at' => $service->created_at->toIso8601String(),
                    ];
                }),
                'pagination' => [
                    'total' => $total,
                    'current_page' => $page,
                    'per_page' => $perPage,
                    'total_pages' => ceil($total / $perPage)
                ]
            ]);
        } catch (\Exception $e) {
            error_log("ServiceController::index error: " . $e->getMessage());
            error_log("Stack trace: " . $e->getTraceAsString());

            return $this->jsonResponse($response, [
                'error' => 'Failed to load services',
                'message' => $e->getMessage(),
                'services' => [],
                'pagination' => [
                    'total' => 0,
                    'current_page' => 1,
                    'per_page' => 15,
                    'total_pages' => 0
                ]
            ], 500);
        }
    }

    public function create(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        // Start output buffering to catch any unwanted output (warnings, notices)
        ob_start();

        try {
            $userId = $request->getAttribute('userId');
            $data = $request->getParsedBody();

            $name = $data['name'] ?? '';
            $type = $data['runtime'] ?? $data['type'] ?? 'nodejs';

            // Load settings from settings.json
            $configFile = __DIR__ . '/../../../config/settings.json';
            $settings = [];
            if (file_exists($configFile)) {
                $settings = json_decode(file_get_contents($configFile), true) ?? [];
            }

            // Generate domain placeholders
            $appDomain = $settings['shared_domain'] ?? $settings['hostname'] ?? $_ENV['APP_DOMAIN'] ?? 'cyberit.cloud';
            $customDomain = !empty($data['domain']) ? $data['domain'] : null;
            $domain = $customDomain; // Can be null initially

            $plan = $data['plan'] ?? 'starter';
            $version = $data['version'] ?? '';

            $installCmd = $data['install_command'] ?? '';
            $postInstallCmd = $data['post_install_command'] ?? '';
            $buildCmd = $data['build_command'] ?? '';
            $startCmd = $data['start_command'] ?? '';
            $rootDirectory = $data['root_directory'] ?? '';
            $startupFile = $data['startup_file'] ?? '';

            // Process Env Vars
            $envVars = [];
            if (isset($data['env_keys']) && isset($data['env_values']) && is_array($data['env_keys'])) {
                foreach ($data['env_keys'] as $index => $key) {
                    if (!empty($key)) {
                        $envVars[$key] = $data['env_values'][$index] ?? '';
                    }
                }
            }

            if (empty($name)) {
                ob_clean();
                return $this->jsonResponse($response, ['message' => 'Name is required'], 400);
            }

            // Resource Limits (Enforce LogicPanel Package Limits)
            $user = User::with('package')->find($userId);
            $package = $user->package ?? null;

            if ($package) {
                // Check Max Services Limit
                $currentServices = Service::where('user_id', $userId)->count();
                // 0 means unlimited? Or check specific value. Assuming > 0 is limit.
                if ($package->max_services > 0 && $currentServices >= $package->max_services) {
                    ob_clean();
                    return $this->jsonResponse($response, ['message' => "Service limit reached. Your plan allows max {$package->max_services} services."], 403);
                }

                $cpu = (float) $package->cpu_limit;
                $mem = $package->memory_limit . 'M';
                $disk = $package->storage_limit . 'M';
            } else {
                // Fallback defaults if no package assigned
                $cpu = 0.5;
                $mem = '512M';
                $disk = '1G';

                // Allow manual override override via 'plan' specific logic only if no package
                if ($plan === 'basic') {
                    $cpu = 1.0;
                    $mem = '1G';
                    $disk = '5G';
                } elseif ($plan === 'pro') {
                    $cpu = 2.0;
                    $mem = '2G';
                    $disk = '10G';
                }
            }

            // Github Repo Logic
            $githubRepo = $data['github_repo'] ?? '';
            $githubBranch = $data['github_branch'] ?? 'main';

            // Docker Image Selection and Default Commands
            $image = 'node:18-slim';
            if ($type === 'nodejs') {
                if (empty($installCmd))
                    $installCmd = 'npm install --include=dev';
                if (empty($startCmd)) {
                    // If startup_file is provided, use it; otherwise default to npm start
                    if (!empty($startupFile)) {
                        $startCmd = "node {$startupFile}";
                    } else {
                        $startCmd = 'npm start';
                    }
                }
                if (strpos($version, '20') !== false)
                    $image = 'node:20-slim';
                elseif (strpos($version, '16') !== false)
                    $image = 'node:16-slim';
                else
                    $image = 'node:18-slim';
            } elseif ($type === 'python') {
                if (empty($installCmd))
                    $installCmd = 'pip install -r requirements.txt';
                if (empty($startCmd)) {
                    if (!empty($startupFile)) {
                        $startCmd = "python {$startupFile}";
                    } else {
                        $startCmd = 'python app.py';
                    }
                }
                // Use slim images instead of alpine for better compatibility with compiled packages (pandas, numpy, etc.)
                if (strpos($version, '3.10') !== false)
                    $image = 'python:3.10-slim';
                elseif (strpos($version, '3.9') !== false)
                    $image = 'python:3.9-slim';
                else
                    $image = 'python:3.11-slim';
            }

            // Create service record first (no port needed with Traefik)
            $service = new Service();
            $service->user_id = $userId;
            $service->name = $name;
            $service->domain = $domain ?: ''; // temporary
            $service->type = $type;
            $service->status = 'creating';
            $service->port = ($type === 'python') ? 5000 : 3000;
            $service->cpu_limit = $cpu;
            $service->memory_limit = $mem;
            $service->disk_limit = $disk;
            $service->runtime_version = $version;
            $service->install_command = $installCmd;
            $service->build_command = $buildCmd;
            $service->start_command = $startCmd;
            $service->env_vars = $envVars;

            $service->save();

            // If no custom domain, generate unique one based on ID
            if (empty($domain)) {
                // Format: ServiceID-UserID.AppDomain (e.g., 1-43.cyberit.cloud)
                $domain = "{$service->id}-{$userId}.{$appDomain}";
                $service->domain = $domain;
                $service->save();
            }


            // Create Docker container with Nginx Proxy routing
            $containerInfo = $this->dockerService->createContainer(
                "service_{$service->id}",
                $image,
                $domain,  // Use the generated or custom domain
                $envVars,
                $cpu,
                $mem,
                $type,
                $githubRepo,
                $githubBranch,
                $installCmd,
                $postInstallCmd,
                $buildCmd,
                $startCmd,
                $rootDirectory,
                $disk, // Pass Disk Limit
                (bool) ($settings['enable_ssl'] ?? false),
                (string) ($settings['letsencrypt_email'] ?? '')
            );

            // Update service with container ID immediately
            $service->container_id = $containerInfo['container_id'];
            $service->status = 'deploying'; // Changed from 'creating' to 'deploying'
            $service->save();

            // Write .env file if env vars provided
            if (!empty($envVars)) {
                $this->writeEnvFile($service, $envVars);
            }

            // Return immediately - status will be updated by background check
            ob_clean();
            return $this->jsonResponse($response, [
                'message' => 'Application deployment started successfully',
                'service' => [
                    'id' => $service->id,
                    'name' => $service->name,
                    'domain' => $service->domain,
                    'type' => $service->type,
                    'status' => 'deploying',
                    'container_id' => $service->container_id,
                    'url' => 'https://' . $service->domain
                ],
                'note' => 'Application is being deployed. Refresh the page in a few seconds to see the status.'
            ], 201);

        } catch (\Exception $e) {
            ob_clean();

            // Rollback: delete ghost service record so user can retry
            if (isset($service) && $service->id) {
                try {
                    // Also try to clean up any partial Docker artifacts
                    if (!empty($service->container_id)) {
                        try {
                            $this->dockerService->removeContainer($service->container_id);
                        } catch (\Exception $dockerEx) {
                            // Container might not exist yet, ignore
                        }
                    }
                    // Remove partial app directory
                    try {
                        $this->dockerService->removeAppDirectory("service_{$service->id}");
                    } catch (\Exception $dirEx) {
                        // Ignore cleanup errors
                    }
                    // Remove traefik config if generated
                    try {
                        $routerName = preg_replace('/[^a-zA-Z0-9-]/', '-', "service_{$service->id}");
                        $this->dockerService->removeTraefikConfig($routerName);
                    } catch (\Exception $traefikEx) {
                        // Ignore
                    }
                    $service->delete();
                    error_log("Rolled back ghost service record ID {$service->id} after creation failure");
                } catch (\Exception $cleanupEx) {
                    error_log("Cleanup failed for service {$service->id}: " . $cleanupEx->getMessage());
                }
            }

            error_log("ServiceController::create error: " . $e->getMessage());

            return $this->jsonResponse($response, [
                'error' => 'Failed to create application.',
                'details' => $e->getMessage()
            ], 500);
        }
    }


    public function show(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $userId = $request->getAttribute('userId');
        $serviceId = (int) $args['id'];

        $service = Service::where('id', $serviceId)
            ->where('user_id', $userId)
            ->first();

        if (!$service) {
            return $this->jsonResponse($response, ['error' => 'Service not found'], 404);
        }

        $stats = [];
        if ($service->container_id) {
            try {
                // Sync status first
                $isRunning = $this->dockerService->isContainerRunning($service->container_id);
                $newStatus = $isRunning ? 'running' : 'stopped';
                if ($service->status !== $newStatus) {
                    $service->status = $newStatus;
                    $service->save();
                }

                if ($isRunning) {
                    $stats = $this->dockerService->getContainerStats($service->container_id);
                }
            } catch (\Exception $e) {
                $stats = ['error' => 'Failed to get stats'];
            }
        }

        return $this->jsonResponse($response, [
            'service' => [
                'id' => $service->id,
                'name' => $service->name,
                'domain' => $service->domain,
                'type' => $service->type,
                'status' => $service->status,
                'port' => $service->port,
                'container_id' => $service->container_id,
                'cpu_limit' => $service->cpu_limit,
                'memory_limit' => $service->memory_limit,
                'disk_limit' => $service->disk_limit,
                'created_at' => $service->created_at->toIso8601String(),
                'stats' => $stats,
            ],
        ]);
    }

    public function start(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $userId = $request->getAttribute('userId');
        $serviceId = (int) $args['id'];

        $service = Service::where('id', $serviceId)
            ->where('user_id', $userId)
            ->first();

        if (!$service) {
            return $this->jsonResponse($response, ['error' => 'Service not found'], 404);
        }

        if (!$service->container_id) {
            return $this->jsonResponse($response, ['error' => 'No container associated'], 400);
        }

        try {
            $this->dockerService->startContainer($service->container_id);
            $service->status = 'running';
            $service->save();

            return $this->jsonResponse($response, ['message' => 'Service started successfully']);
        } catch (\Exception $e) {
            // Handle case where container might be missing
            if (strpos($e->getMessage(), 'No such container') !== false) {
                $service->status = 'error'; // Or 'stopped', but error indicates it needs attention (recreation)
                $service->save();
                return $this->jsonResponse($response, [
                    'error' => 'Container missing. Please delete and recreate the app.',
                    'message' => 'Container missing'
                ], 404);
            }

            return $this->jsonResponse($response, [
                'error' => 'Failed to start service',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    public function stop(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $userId = $request->getAttribute('userId');
        $serviceId = (int) $args['id'];

        $service = Service::where('id', $serviceId)
            ->where('user_id', $userId)
            ->first();

        if (!$service) {
            return $this->jsonResponse($response, ['error' => 'Service not found'], 404);
        }

        if (!$service->container_id) {
            return $this->jsonResponse($response, ['error' => 'No container associated'], 400);
        }

        try {
            $this->dockerService->stopContainer($service->container_id);
            $service->status = 'stopped';
            $service->save();

            return $this->jsonResponse($response, ['message' => 'Service stopped successfully']);
        } catch (\Exception $e) {
            // If container not found, just mark as stopped
            if (strpos($e->getMessage(), 'No such container') !== false) {
                $service->status = 'stopped';
                $service->save();
                return $this->jsonResponse($response, ['message' => 'Service marked as stopped (Container missing)']);
            }

            return $this->jsonResponse($response, [
                'error' => 'Failed to stop service',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    public function restart(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $userId = $request->getAttribute('userId');
        $serviceId = (int) $args['id'];

        $service = Service::where('id', $serviceId)
            ->where('user_id', $userId)
            ->first();

        if (!$service) {
            return $this->jsonResponse($response, ['error' => 'Service not found'], 404);
        }

        if (!$service->container_id) {
            return $this->jsonResponse($response, ['error' => 'No container associated'], 400);
        }

        try {
            $this->dockerService->restartContainer($service->container_id);
            $service->status = 'running';
            $service->save();

            return $this->jsonResponse($response, ['message' => 'Service restarted successfully']);
        } catch (\Exception $e) {
            if (strpos($e->getMessage(), 'No such container') !== false) {
                $service->status = 'stopped';
                $service->save();
                return $this->jsonResponse($response, ['error' => 'Container missing'], 404);
            }

            return $this->jsonResponse($response, [
                'error' => 'Failed to restart service',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    public function logs(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $userId = $request->getAttribute('userId');
        $serviceId = (int) $args['id'];

        $service = Service::where('id', $serviceId)
            ->where('user_id', $userId)
            ->first();

        if (!$service) {
            return $this->jsonResponse($response, ['error' => 'Service not found'], 404);
        }

        if (!$service->container_id) {
            return $this->jsonResponse($response, ['error' => 'No container associated'], 400);
        }

        try {
            $logs = $this->dockerService->getContainerLogs($service->container_id);

            return $this->jsonResponse($response, [
                'logs' => $logs,
            ]);
        } catch (\Exception $e) {
            return $this->jsonResponse($response, [
                'error' => 'Failed to get logs',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    public function stats(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $userId = $request->getAttribute('userId');
        $serviceId = (int) $args['id'];

        $service = Service::where('id', $serviceId)
            ->where('user_id', $userId)
            ->first();

        if (!$service) {
            return $this->jsonResponse($response, ['error' => 'Service not found'], 404);
        }

        if (!$service->container_id) {
            return $this->jsonResponse($response, ['error' => 'No container associated'], 400);
        }

        try {
            $stats = $this->dockerService->getContainerStats($service->container_id);

            return $this->jsonResponse($response, [
                'stats' => $stats,
            ]);
        } catch (\Exception $e) {
            return $this->jsonResponse($response, [
                'error' => 'Failed to get stats',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    public function update(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $userId = $request->getAttribute('userId');
        $serviceId = (int) $args['id'];

        $service = Service::where('id', $serviceId)
            ->where('user_id', $userId)
            ->first();

        if (!$service) {
            return $this->jsonResponse($response, ['error' => 'Service not found'], 404);
        }

        $data = json_decode((string) $request->getBody(), true) ?: [];

        // Update allowed fields
        $allowedFields = ['name', 'domain', 'install_command', 'build_command', 'start_command', 'env_vars', 'runtime_version', 'status'];
        $domainChanged = false;

        foreach ($allowedFields as $field) {
            if (isset($data[$field])) {
                if ($field === 'status') {
                    // Handle status actions (RESTful way to start/stop/restart)
                    $action = $data[$field];
                    try {
                        if ($action === 'running' || $action === 'start') {
                            $this->dockerService->startContainer($service->container_id);
                            $service->status = 'running';
                        } elseif ($action === 'stopped' || $action === 'stop') {
                            $this->dockerService->stopContainer($service->container_id);
                            $service->status = 'stopped';
                        } elseif ($action === 'restart') {
                            $this->dockerService->restartContainer($service->container_id);
                            $service->status = 'running';
                        }
                    } catch (\Exception $e) {
                        // Log error but continue with other updates
                    }
                } elseif ($field === 'domain') {
                    // Validate Domain (allow comma separated, alphanumeric, dots, hyphens)
                    $domains = explode(',', $data[$field]);
                    $cleanDomains = [];
                    foreach ($domains as $d) {
                        $d = trim($d);
                        if (!empty($d)) {
                            // Basic validation
                            if (!preg_match('/^[a-zA-Z0-9.-]+$/', $d)) {
                                return $this->jsonResponse($response, ['error' => 'Invalid domain format: ' . $d], 400);
                            }
                            $cleanDomains[] = $d;
                        }
                    }
                    $newDomainStr = implode(',', $cleanDomains);

                    if ($service->domain !== $newDomainStr) {
                        $service->domain = $newDomainStr;
                        $domainChanged = true;
                    }
                } else {
                    if (in_array($field, ['install_command', 'build_command', 'start_command', 'runtime_version'])) {
                        if ($service->$field !== $data[$field]) {
                            $domainChanged = true; // Force recreation if commands change
                        }
                    }
                    $service->$field = $data[$field];
                }
            }
        }

        $service->save();

        // If env_vars were updated, also write them to a .env file in the app directory
        if (isset($data['env_vars']) && is_array($data['env_vars'])) {
            $this->writeEnvFile($service, $data['env_vars']);
        }

        // RECREATE CONTAINER IF DOMAIN OR COMMANDS CHANGED
        // VIRTUAL_HOST, LETSENCRYPT_HOST etc are baked into container labels/env at creation.
        // We must destroy and recreate.
        if ($domainChanged && $service->container_id) {
            try {
                // 1. Remove old container
                try {
                    $this->dockerService->removeContainer($service->container_id);
                } catch (\Exception $e) {
                    // Ignore if missing
                }

                // 2. Prepare params for new container
                // We need to fetch necessary params that might not be in $data but are in DB

                // Image Determination (Re-used logic from create - should ideally be refactored to a helper)
                $image = 'node:18-slim'; // Default
                if ($service->type === 'nodejs') {
                    if (strpos($service->runtime_version, '20') !== false)
                        $image = 'node:20-slim';
                    elseif (strpos($service->runtime_version, '16') !== false)
                        $image = 'node:16-slim';
                    else
                        $image = 'node:18-slim';
                } elseif ($service->type === 'python') {
                    if (strpos($service->runtime_version, '3.10') !== false)
                        $image = 'python:3.10-slim';
                    elseif (strpos($service->runtime_version, '3.9') !== false)
                        $image = 'python:3.9-slim';
                    else
                        $image = 'python:3.11-slim';
                }

                // Create Container
                $containerInfo = $this->dockerService->createContainer(
                    "service_{$service->id}",
                    $image,
                    $service->domain,
                    $service->env_vars ?? [],
                    $service->cpu_limit,
                    $service->memory_limit,
                    $service->type,
                    '', // Repo not needed for recreation, code already exists
                    'main',
                    $service->install_command ?: '',
                    $service->build_command ?: '',
                    $service->start_command ?: ''
                );

                // Update Service with new Container ID
                $service->container_id = $containerInfo['container_id'];
                $service->status = 'running';
                $service->save();

            } catch (\Exception $e) {
                return $this->jsonResponse($response, [
                    'message' => 'Domain updated but failed to restart app: ' . $e->getMessage(),
                    'service' => $service,
                ], 500);
            }
        }

        return $this->jsonResponse($response, [
            'message' => 'Service updated successfully' . ($domainChanged ? ' and restarted.' : ''),
            'service' => $service,
        ]);
    }

    /**
     * Write environment variables to a .env file in the app's directory
     */
    private function writeEnvFile(Service $service, array $envVars): void
    {
        // Use the same path as DockerService - /var/www/html/storage/user-apps in container
        // or relative path that works in both environments
        $appPath = $_ENV['USER_APPS_PATH'] ?? '/var/www/html/storage/user-apps';

        // Fallback: if we're on Windows (local dev), use storage path relative to current script
        if (!is_dir($appPath) && PHP_OS_FAMILY === 'Windows') {
            $appPath = dirname(__DIR__, 3) . '/storage/user-apps';
        }

        $envFilePath = $appPath . "/service_{$service->id}/.env";

        $content = "# Auto-generated by LogicPanel - Do not edit manually\n";
        $content .= "# Last updated: " . date('Y-m-d H:i:s') . "\n\n";

        foreach ($envVars as $key => $value) {
            // Escape values that contain special characters
            $escapedValue = $this->escapeEnvValue($value);
            $content .= "{$key}={$escapedValue}\n";
        }

        // Ensure the directory exists
        $dir = dirname($envFilePath);
        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }

        file_put_contents($envFilePath, $content);
        @chmod($envFilePath, 0644);
    }

    /**
     * Escape special characters in .env values
     */
    private function escapeEnvValue(string $value): string
    {
        // If value contains spaces, quotes, or special chars, wrap in quotes
        if (preg_match('/[\s"\'\\\\$`!]/', $value)) {
            // Escape existing quotes and wrap in double quotes
            $escaped = str_replace(['\\', '"'], ['\\\\', '\\"'], $value);
            return "\"{$escaped}\"";
        }
        return $value;
    }

    public function command(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $userId = $request->getAttribute('userId');
        $serviceId = (int) $args['id'];
        $data = $request->getParsedBody();
        $command = $data['command'] ?? '';
        $cwd = $data['cwd'] ?? '';

        $service = Service::where('id', $serviceId)
            ->where('user_id', $userId)
            ->first();

        if (!$service) {
            return $this->jsonResponse($response, ['error' => 'Service not found'], 404);
        }

        if (!$service->container_id) {
            return $this->jsonResponse($response, ['error' => 'No container associated'], 400);
        }

        if (!$this->dockerService->isContainerRunning($service->container_id)) {
            return $this->jsonResponse($response, [
                'error' => 'Application is not running. Please start it first.',
                'message' => 'Container is stopped.'
            ], 400);
        }

        if (empty($command)) {
            return $this->jsonResponse($response, ['error' => 'Command required'], 400);
        }

        // Determine working directory
        // If cwd is empty, default to container workdir which corresponds to /app in user view but /storage/{name} in reality
        // LogicPanel maps volume to /storage. Container is started with WORKDIR /storage/{name}
        // User wants to see /app.

        $internalCwd = $cwd;
        if (empty($cwd) || $cwd === '/app' || $cwd === '~') {
            $internalCwd = "/storage/service_{$service->id}";
        } else if (str_starts_with($cwd, '/app')) {
            // Map /app to /storage/{name} logic?
            // Or just trust the relative path?
            // Let's assume user is smart or we handle it.
            // If user does `cd ..`, they might escape to /storage.
            // We'll stick to running the command. `docker exec -w` takes absolute path.
        }

        try {
            // Execute command
            // We append " && pwd" to get the new directory if it changed (e.g. cd)
            // But "docker exec -w" sets the starting directory.
            // If command contains "cd", it only affects that shell instance unless we chain it.
            // Since this is a one-off exec, "cd" won't persist across requests.
            // UI needs to handle this by tracking CWD and sending it back.
            // BUT "cd newdir" produces no output.
            // Users want stateful terminal feel.
            // We can wrap it: sh -c "cd {cwd} && {command} && echo '___PWD___' && pwd"

            // Fix for "cd" command: if user types "cd ..", we run "cd {cwd} && cd .. && pwd" to get new path.

            // SECURITY FIX: Escape command for shell
            // $safeCommand = str_replace('"', '\"', $command); // Basic escape - INSUFFICIENT

            // Check if user is trying to CD
            if (preg_match('/^cd\s+(.+)$/', trim($command), $matches)) {
                $targetDir = trim($matches[1]);
                // Construct command: cd "{current}" && cd "{target}" && echo "__PWD:$(pwd)"
                // We must use escapeshellarg for paths, but we need to be careful with && chaining

                $wrappedCommand = 'cd ' . escapeshellarg($internalCwd) . ' && cd ' . escapeshellarg($targetDir) . ' && echo "__PWD:$(pwd)"';
            } else {
                // Regular command
                // cd "{current}" && {command}
                // We use escapeshellarg for the dir, but the command itself...
                // The user WANTS to run arbitrary commands (that's the point of the terminal).
                // We just need to ensure they can't break out of the "sh -c" wrapper in executeCommand.
                // executeCommand does: sh -c 'COMMAND'
                // If we pass: cd /app && ls -la
                // executeCommand runs: sh -c 'cd /app && ls -la'
                // This is generally safe content-wise IF executeCommand handles the wrapping correctly.
                // Let's check dockerService->executeCommand again.

                // DockerService::executeCommand uses new Process(['docker', 'exec', ..., 'sh', '-c', $command])
                // Symfony Process component handles argument escaping automatically for the outer shell.
                // So we just need to ensure our concatenated string is valid shell syntax.

                // However, to be ultra safe and prevent ambiguous parsing:
                $wrappedCommand = 'cd ' . escapeshellarg($internalCwd) . ' && ' . $command;
            }

            // We use executeCommand from DockerService (needs to be exposed/public)
            $output = $this->dockerService->executeCommand($service->container_id, $wrappedCommand);

            $newCwd = $internalCwd;

            // Parse output for PWD
            if (preg_match('/__PWD:(.+)/', $output, $matches)) {
                $newCwd = trim($matches[1]);
                $output = str_replace($matches[0], '', $output); // Remove PWD marker from output
            }

            // Map internal paths back to /app for display?
            // /storage/name -> /app
            // For now, let's keep it real path to avoid confusion or do simple replacement
            $displayCwd = $newCwd;
            // if (str_starts_with($newCwd, "/storage/{$service->name}")) {
            //    $displayCwd = str_replace("/storage/{$service->name}", "/app", $newCwd);
            // }

            return $this->jsonResponse($response, [
                'output' => $output,
                'cwd' => $displayCwd
            ]);

        } catch (\Exception $e) {
            return $this->jsonResponse($response, [
                'error' => 'Command failed',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    public function delete(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $userId = $request->getAttribute('userId');
        $serviceId = (int) $args['id'];

        $service = Service::where('id', $serviceId)
            ->where('user_id', $userId)
            ->first();

        if (!$service) {
            return $this->jsonResponse($response, ['error' => 'Service not found'], 404);
        }

        $errors = [];

        // 1. Remove Docker container (try both by ID and by name)
        $containerName = "logicpanel_app_service_{$service->id}";

        // Try by container_id first
        if ($service->container_id) {
            try {
                $this->dockerService->removeContainer($service->container_id);
            } catch (\Exception $e) {
                // Ignore if container doesn't exist
                if (strpos($e->getMessage(), 'No such container') === false) {
                    $errors[] = "Container ID removal: " . $e->getMessage();
                }
            }
        }

        // Also try by container name (in case ID was wrong or container was recreated)
        try {
            $this->dockerService->removeContainer($containerName);
        } catch (\Exception $e) {
            // Ignore - container might already be removed
        }

        // 2. Remove Traefik config file
        try {
            $this->dockerService->removeTraefikConfig("service_{$service->id}");
        } catch (\Exception $e) {
            $errors[] = "Traefik config removal: " . $e->getMessage();
        }

        // 3. Remove from Domains table
        try {
            $domainList = explode(',', $service->domain);
            foreach ($domainList as $dName) {
                $dName = trim($dName);
                if ($dName) {
                    Domain::where('name', $dName)->delete();
                }
            }
        } catch (\Exception $e) {
            $errors[] = "Domain cleanup: " . $e->getMessage();
        }

        // 4. Delete storage directory
        try {
            $this->deleteServiceDirectory($service);
        } catch (\Exception $e) {
            $errors[] = "Directory deletion: " . $e->getMessage();
        }

        // 5. Delete service record from database
        try {
            $service->delete();
        } catch (\Exception $e) {
            return $this->jsonResponse($response, [
                'error' => 'Failed to delete service record',
                'message' => $e->getMessage(),
            ], 500);
        }

        // Return success even with warnings (service is deleted)
        if (!empty($errors)) {
            return $this->jsonResponse($response, [
                'message' => 'Service deleted with some cleanup warnings',
                'warnings' => $errors
            ]);
        }

        return $this->jsonResponse($response, ['message' => 'Service deleted successfully']);
    }

    private function deleteServiceDirectory(Service $service): void
    {
        $serviceDir = "service_{$service->id}";
        $basePath = $_ENV['USER_APPS_HOST_PATH'] ?? '/var/www/html/storage/user-apps';
        // Note: We need the HOST path for mounting in the cleaner container.
        // If we are inside a container, we might need a mapping.
        // Assuming USER_APPS_HOST_PATH is correctly set in .env to the host's path.

        // However, for verification `is_dir`, we need the INTERNAL path.
        $internalBasePath = $_ENV['USER_APPS_PATH'] ?? '/var/www/html/storage/user-apps';
        $fullPath = $internalBasePath . '/' . $serviceDir;

        if (!is_dir($fullPath)) {
            error_log("Directory not found: $fullPath");
            return;
        }

        error_log("Deleting service directory: $fullPath");

        // Strategy: Cleaner Container
        // We spawn a lightweight Alpine container, mount the storage volume, and delete the user's folder.
        // This avoids giving the PHP user root privileges via sudo.
        // It also ensures permissions don't block deletion (since we run as root inside that temp container).

        $containerName = "cleaner_" . uniqid();

        // Verify we have the host path for mounting
        // Fallback to DockerService's default if env not set
        $hostStoragePath = $_ENV['USER_APPS_HOST_PATH'] ?? '/var/www/html/storage/user-apps';

        // Command: docker run --rm --name cleanup_xxx -v /host/path:/work alpine rm -rf /work/service_123
        $command = [
            'docker',
            'run',
            '--rm',
            '--name',
            $containerName,
            '-v',
            "{$hostStoragePath}:/work",
            'alpine',
            'rm',
            '-rf',
            "/work/{$serviceDir}"
        ];

        // Use DockerService's helper or raw process? ServiceController has private dockerService property.
        // We can't access private methods easily if they aren't exposed.
        // But we can use Symfony Process directly like DockerService does.

        try {
            $process = new \Symfony\Component\Process\Process($command);
            $process->setTimeout(60);
            $process->run();

            if (!$process->isSuccessful()) {
                throw new \RuntimeException($process->getErrorOutput());
            }
            error_log("✓ Deleted successfully via Cleaner Container");

        } catch (\Exception $e) {
            error_log("✗ Cleaner Container failed: " . $e->getMessage());
            error_log("Falling back to PHP recursive delete (weak)");
            $this->recursiveDeletePHP($fullPath);
        }
    }

    private function recursiveDeletePHP(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        try {
            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($dir, \RecursiveDirectoryIterator::SKIP_DOTS),
                \RecursiveIteratorIterator::CHILD_FIRST
            );

            foreach ($iterator as $item) {
                $path = $item->getRealPath();
                @chmod($path, 0777);

                if ($item->isDir()) {
                    @rmdir($path);
                } else {
                    @unlink($path);
                }
            }

            @chmod($dir, 0777);
            @rmdir($dir);

        } catch (\Exception $e) {
            error_log("PHP recursive delete error: " . $e->getMessage());
        }
    }

    private function recursiveDelete(string $dir): void
    {
        // This method is deprecated - use recursiveDeletePHP instead
        $this->recursiveDeletePHP($dir);
    }

    private function jsonResponse(ResponseInterface $response, array $data, int $status = 200): ResponseInterface
    {
        $response->getBody()->write(json_encode($data));
        return $response
            ->withStatus($status)
            ->withHeader('Content-Type', 'application/json');
    }
    public function getTerminalToken(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $userId = $request->getAttribute('userId');
        $serviceId = (int) $args['id'];

        file_put_contents(sys_get_temp_dir() . '/terminal_debug.log', date('Y-m-d H:i:s') . " - Accessing getTerminalToken for Service $serviceId User $userId\n", FILE_APPEND);

        $service = Service::where('id', $serviceId)
            ->where('user_id', $userId)
            ->first();

        if (!$service || !$service->container_id) {
            return $this->jsonResponse($response, ['error' => 'Service or container not found'], 404);
        }

        // Generate Short-lived JWT for Terminal Gateway
        // Expiry: 1 minute (Client must connect immediately)
        $payload = [
            'iss' => 'logicpanel-backend',
            'aud' => 'logicpanel-gateway',
            'iat' => time(),
            'exp' => time() + 60,
            'sub' => $userId,
            'service_id' => $service->id,
            'container_id' => $service->container_id,
            'mode' => 'user'  // User terminal - exec into app container
        ];

        file_put_contents(sys_get_temp_dir() . '/terminal_debug.log', date('Y-m-d H:i:s') . " - Token Payload: " . json_encode($payload) . "\n", FILE_APPEND);

        // Use the shared secret from environment
        $secret = $_ENV['JWT_SECRET'] ?? 'secret';
        $token = JWT::encode($payload, $secret, 'HS256');

        // Build dynamic gateway URL based on request host
        // We route through Apache proxy at /ws/terminal to handle SSL properly
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';

        // Determine protocol (wss for HTTPS, ws for HTTP)
        $isSecure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');
        $wsProtocol = $isSecure ? 'wss' : 'ws';

        // Use the proxy path /ws/terminal instead of direct port
        // Strip port from host to use standard 443 wss in production
        $cleanHost = explode(':', $host)[0];
        $gatewayUrl = "{$wsProtocol}://{$cleanHost}/ws/terminal";

        return $this->jsonResponse($response, [
            'token' => $token,
            'gateway_url' => $gatewayUrl
        ]);
    }


}
