<?php

declare(strict_types=1);

namespace LogicPanel\Application\Controllers;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use LogicPanel\Domain\Service\Service;
use LogicPanel\Infrastructure\Docker\DockerService;
use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Exception\ClientException as GuzzleClientException;

class SystemController
{
    private DockerService $dockerService;

    public function __construct(DockerService $dockerService)
    {
        $this->dockerService = $dockerService;
    }

    /**
     * Get system-wide stats (old method, kept for backwards compatibility)
     */
    public function stats(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $stats = $this->getSystemStats();
        return $this->jsonResponse($response, $stats);
    }

    /**
     * Get container-specific stats for the logged-in user's services
     */
    public function containerStats(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $userId = $request->getAttribute('userId');

        // Get user and package limits
        $user = \LogicPanel\Domain\User\User::with('package')->find($userId);
        $diskLimitBytes = 0;
        $memLimitBytes = 0;
        $cpuLimitCores = 0;

        if ($user && $user->package) {
            $diskLimitBytes = $user->package->storage_limit * 1024 * 1024; // MB to Bytes
            $memLimitBytes = $user->package->memory_limit * 1024 * 1024; // MB to Bytes
            $cpuLimitCores = (float) $user->package->cpu_limit;
        }

        // Get all services for this user
        $services = Service::where('user_id', $userId)
            ->whereNotNull('container_id')
            ->where('status', 'running')
            ->get();

        $containerStats = [];
        $totalCpu = 0;
        $totalMemUsed = 0;
        $totalMemLimit = 0;
        $totalDisk = 0;

        foreach ($services as $service) {
            if (empty($service->container_id))
                continue;

            try {
                $stats = $this->dockerService->getContainerStats($service->container_id);

                // Parse memory values for aggregation
                $memParts = explode(' / ', $stats['memory']);
                $memUsedBytes = $this->parseBytes($memParts[0] ?? '0');
                $memLimitBytes = $this->parseBytes($memParts[1] ?? '0');

                // Parse disk - assuming format like "50MB" or "1.2GB"
                // Docker implementation usually returns single string
                $diskStr = $stats['disk'] ?? '0B';
                // Remove any non-standard chars if needed, parseBytes handles basic units
                $diskBytes = $this->parseBytes($diskStr);

                $cpuPercent = (float) str_replace('%', '', $stats['cpu']);

                $totalCpu += $cpuPercent;
                $totalMemUsed += $memUsedBytes;
                $totalMemLimit += $memLimitBytes;
                $totalDisk += $diskBytes;

                $containerStats[] = [
                    'id' => $service->id,
                    'name' => $service->name,
                    'type' => $service->type,
                    'container_id' => substr($service->container_id, 0, 12),
                    'cpu' => $stats['cpu'],
                    'memory' => $stats['memory'],
                    'disk' => $stats['disk'],
                    'status' => 'running'
                ];
            } catch (\Exception $e) {
                $containerStats[] = [
                    'id' => $service->id,
                    'name' => $service->name,
                    'type' => $service->type,
                    'container_id' => substr($service->container_id, 0, 12),
                    'cpu' => 'N/A',
                    'memory' => 'N/A',
                    'disk' => 'N/A',
                    'status' => 'error'
                ];
            }
        }

        // Calculate summary/total percentages against PACKAGE limits
        // CPU: sum of % from all containers (each container max is e.g. 100% if 1 core assigned)
        // If package limit is 1.0 cores, we sum the raw percentages.
        $cpuUsedVal = round($totalCpu, 1);
        $cpuPercentResult = ($cpuLimitCores > 0) ? ($cpuUsedVal / ($cpuLimitCores * 100) * 100) : 0;

        $memPercentResult = ($memLimitBytes > 0) ? round(($totalMemUsed / $memLimitBytes) * 100) : 0;
        $diskPercentResult = ($diskLimitBytes > 0) ? round(($totalDisk / $diskLimitBytes) * 100) : 0;

        return $this->jsonResponse($response, [
            'containers' => $containerStats,
            'summary' => [
                'total_containers' => count($containerStats),
                'cpu' => [
                    'used' => $cpuUsedVal . '%',
                    'limit' => $cpuLimitCores . ($cpuLimitCores > 1 ? ' Cores' : ' Core'),
                    'percent' => round(min($cpuPercentResult, 100), 1)
                ],
                'memory' => [
                    'used' => $this->formatBytes($totalMemUsed),
                    'limit' => $this->formatBytes($memLimitBytes),
                    'percent' => min($memPercentResult, 100)
                ],
                'disk' => [
                    'used' => $this->formatBytes($totalDisk),
                    'limit' => ($diskLimitBytes > 0) ? $this->formatBytes($diskLimitBytes) : '∞',
                    'percent' => min($diskPercentResult, 100)
                ]
            ]
        ]);
    }

    private function getSystemStats(): array
    {
        $cpu = 0;
        $memory = [
            'total' => '0 B',
            'free' => '0 B',
            'used' => '0 B',
            'percent' => 0
        ];
        $disk = [
            'total' => '0 B',
            'free' => '0 B',
            'used' => '0 B',
            'percent' => 0
        ];

        try {
            // Linux Implementation (Container Aware)
            $load = @sys_getloadavg();
            if ($load && is_array($load) && isset($load[0])) {
                $cpu = (int) ($load[0] * 100);
                if ($cpu > 100)
                    $cpu = 100;
            }
        } catch (\Throwable $e) {
            // Ignore CPU stats failure
        }

        $memTotal = 0;
        $memUsed = 0;

        try {
            if (@file_exists('/sys/fs/cgroup/memory.current')) {
                $memUsed = (int) @file_get_contents('/sys/fs/cgroup/memory.current');
                $memMax = trim(@file_get_contents('/sys/fs/cgroup/memory.max'));
                if ($memMax !== 'max')
                    $memTotal = (int) $memMax;
            } else if (@file_exists('/sys/fs/cgroup/memory/memory.usage_in_bytes')) {
                $memUsed = (int) @file_get_contents('/sys/fs/cgroup/memory/memory.usage_in_bytes');
                if (@file_exists('/sys/fs/cgroup/memory/memory.limit_in_bytes')) {
                    $memValid = @file_get_contents('/sys/fs/cgroup/memory/memory.limit_in_bytes');
                    if ((float) $memValid < 1e15)
                        $memTotal = (int) $memValid;
                }
            }

            if ($memTotal <= 0 && @is_readable('/proc/meminfo')) {
                $memInfo = @file_get_contents('/proc/meminfo');
                if ($memInfo && preg_match('/MemTotal:\s+(\d+) kB/', $memInfo, $matches))
                    $memTotal = (int) $matches[1] * 1024;
                if ($memUsed <= 0 && $memInfo && preg_match('/MemAvailable:\s+(\d+) kB/', $memInfo, $matches)) {
                    $memAvailable = (int) $matches[1] * 1024;
                    $memUsed = $memTotal - $memAvailable;
                }
            }
        } catch (\Throwable $e) {
            // Ignore Memory stats failure
        }

        $memPercent = ($memTotal > 0) ? round(($memUsed / $memTotal) * 100) : 0;
        $memory = [
            'total' => $this->formatBytes($memTotal),
            'free' => $this->formatBytes(max(0, $memTotal - $memUsed)),
            'used' => $this->formatBytes(max(0, $memUsed)),
            'percent' => $memPercent
        ];

        try {
            $totalDisk = @disk_total_space('/');
            $freeDisk = @disk_free_space('/');

            if ($totalDisk === false)
                $totalDisk = 0;
            if ($freeDisk === false)
                $freeDisk = 0;

            $usedDisk = $totalDisk - $freeDisk;
            $diskPercent = ($totalDisk > 0) ? round(($usedDisk / $totalDisk) * 100) : 0;
            $disk = [
                'total' => $this->formatBytes($totalDisk),
                'free' => $this->formatBytes($freeDisk),
                'used' => $this->formatBytes($usedDisk),
                'percent' => $diskPercent
            ];
        } catch (\Throwable $e) {
            // Ignore disk stats failure
        }

        return ['cpu' => $cpu, 'memory' => $memory, 'disk' => $disk];
    }

    private function parseBytes(string $str): int
    {
        $str = trim($str);
        if (empty($str) || $str === '∞')
            return 0;

        preg_match('/^([\d.]+)\s*(\w*)$/', $str, $m);
        $num = (float) ($m[1] ?? 0);
        $unit = strtoupper($m[2] ?? 'B');

        $multipliers = [
            'B' => 1,
            'K' => 1024,
            'KB' => 1024,
            'M' => 1048576,
            'MB' => 1048576,
            'G' => 1073741824,
            'GB' => 1073741824,
            'T' => 1099511627776,
            'TB' => 1099511627776
        ];
        return (int) ($num * ($multipliers[$unit] ?? 1));
    }

    private function formatBytes($bytes, $precision = 2)
    {
        $units = array('B', 'KB', 'MB', 'GB', 'TB');
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        $bytes /= pow(1024, $pow);
        return round($bytes, $precision) . ' ' . $units[$pow];
    }

    /**
     * Check for system updates from GitHub
     */
    public function checkUpdate(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $currentVersion = @file_get_contents(__DIR__ . '/../../../VERSION') ?: '0.0.0';
        $currentVersion = trim($currentVersion);

        try {
            $client = new GuzzleClient();
            $res = $client->request('GET', 'https://api.github.com/repos/cyber-wahid/logicpanel/releases/latest', [
                'headers' => [
                    'Accept' => 'application/vnd.github.v3+json',
                    'User-Agent' => 'LogicPanel-System'
                ],
                'timeout' => 5
            ]);

            $release = json_decode($res->getBody()->getContents(), true);
            $latestVersion = ltrim($release['tag_name'] ?? '0.0.0', 'v');

            $hasUpdate = version_compare($latestVersion, $currentVersion, '>');

            return $this->jsonResponse($response, [
                'current_version' => $currentVersion,
                'latest_version' => $latestVersion,
                'has_update' => $hasUpdate,
                'release_notes' => $release['body'] ?? '',
                'published_at' => $release['published_at'] ?? ''
            ]);

        } catch (GuzzleClientException $e) {
            // Handle 404 (No releases found)
            if ($e->getResponse()->getStatusCode() === 404) {
                return $this->jsonResponse($response, [
                    'current_version' => $currentVersion,
                    'latest_version' => $currentVersion,
                    'has_update' => false,
                    'message' => 'No official releases found. You may be on a development build.'
                ]);
            }

            return $this->jsonResponse($response, [
                'current_version' => $currentVersion,
                'error' => 'Client Error: ' . $e->getMessage()
            ], 500);
        } catch (\Exception $e) {
            return $this->jsonResponse($response, [
                'current_version' => $currentVersion,
                'error' => 'System Error: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Perform system update
     */
    public function performUpdate(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $logFile = '/var/www/html/storage/logs/update.log';

        // Clear previous log
        @file_put_contents($logFile, '');

        // Execute update script in background
        $updateScript = '/var/www/html/src/Application/Scripts/update_system.php';
        $sanitizedLogFile = escapeshellarg($logFile);
        $sanitizedScript = escapeshellarg($updateScript);

        // Run update script in background
        $cmd = "nohup php $sanitizedScript >> $sanitizedLogFile 2>&1 &";
        exec($cmd);

        return $this->jsonResponse($response, [
            'message' => 'Update process started. Check logs for progress.',
            'status' => 'updating',
            'log_file' => 'storage/logs/update.log'
        ]);
    }

    /**
     * Get update progress/logs
     */
    public function getUpdateProgress(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $logFile = '/var/www/html/storage/logs/update.log';

        if (!file_exists($logFile)) {
            return $this->jsonResponse($response, [
                'status' => 'idle',
                'logs' => 'No update in progress'
            ]);
        }

        $logs = file_get_contents($logFile);
        $isComplete = strpos($logs, 'Update completed successfully') !== false;
        $hasFailed = strpos($logs, 'Error:') !== false && $isComplete === false;

        $status = 'updating';
        if ($isComplete) {
            $status = 'completed';
        } elseif ($hasFailed) {
            $status = 'failed';
        }

        return $this->jsonResponse($response, [
            'status' => $status,
            'logs' => $logs,
            'is_complete' => $isComplete
        ]);
    }

    private function jsonResponse(ResponseInterface $response, array $data, int $status = 200): ResponseInterface
    {
        $response->getBody()->write(json_encode($data));
        return $response
            ->withStatus($status)
            ->withHeader('Content-Type', 'application/json');
    }
}
