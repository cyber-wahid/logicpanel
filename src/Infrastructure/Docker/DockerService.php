<?php

declare(strict_types=1);

namespace LogicPanel\Infrastructure\Docker;

use Symfony\Component\Process\Process;
use Symfony\Component\Process\Exception\ProcessFailedException;

class DockerService
{
    private string $network;
    private string $userAppsPath;
    private string $userAppsVolume;

    // Supported Runtime Versions
    private array $supportedNodeVersions = ['16', '18', '20', '22', '24'];
    private array $supportedPythonVersions = ['3.8', '3.9', '3.10', '3.11', '3.12'];
    private string $defaultNodeVersion = '20';
    private string $defaultPythonVersion = '3.11';

    public function __construct(array $config)
    {
        // Use the network name passed from config (which comes from .env)
        // If empty, fallback to logicpanel_internal for backward compatibility
        $this->network = !empty($config['network']) ? $config['network'] : 'logicpanel_internal';
        
        $this->userAppsPath = $config['user_apps_path'];
        // Docker Compose prepends project name to volume names
        $this->userAppsVolume = $config['user_apps_volume'] ?? 'logicpanel_logicpanel_user_apps';
    }

    /**
     * Build Docker image name from type and version
     */
    private function buildImageName(string $appType, string $version): string
    {
        // Use default if version not specified
        if (empty($version)) {
            $version = ($appType === 'nodejs') 
                ? $this->defaultNodeVersion 
                : $this->defaultPythonVersion;
        }
        
        // Validate version
        if (!$this->validateRuntimeVersion($appType, $version)) {
            throw new \InvalidArgumentException(
                "Unsupported {$appType} version: {$version}"
            );
        }
        
        // Build image name
        if ($appType === 'nodejs') {
            return "node:{$version}-bookworm-slim";
        } else {
            return "python:{$version}-slim";
        }
    }

    /**
     * Validate runtime version
     */
    private function validateRuntimeVersion(string $appType, string $version): bool
    {
        if ($appType === 'nodejs') {
            return in_array($version, $this->supportedNodeVersions);
        } else {
            return in_array($version, $this->supportedPythonVersions);
        }
    }

    public function createContainer(
        string $name,
        string $image,
        string $domain,
        array $envVars = [],
        float $cpuLimit = 0.5,
        string $memoryLimit = '512M',
        string $appType = 'nodejs',
        string $githubRepo = '',
        string $githubBranch = 'main',
        string $installCommand = '',
        string $postInstallCommand = '',
        string $buildCommand = '',
        string $startCommand = '',
        string $rootDirectory = '',
        string $diskLimit = '1G',
        bool $enableSsl = false,
        string $sslEmail = '',
        string $runtimeVersion = ''
    ): array {
        // Calculate Image based on Runtime Version
        if (empty($image) || !empty($runtimeVersion)) {
             try {
                 $image = $this->buildImageName($appType, $runtimeVersion);
             } catch (\Exception $e) {
                 // Fallback or log? For now let's just log and throw
                 throw $e;
             }
        }
        $containerName = "logicpanel_app_{$name}";
        $appPath = $this->userAppsPath . "/{$name}";

        // Initialize logging
        $this->log('INFO', "Starting container creation for {$name}", [
            'appType' => $appType,
            'runtimeVersion' => $runtimeVersion,
            'domain' => $domain
        ]);

        try {
            // Create app directory with secure permissions
            if (!is_dir($appPath)) {
                $mkdirResult = @mkdir($appPath, 0755, true);

                if (!$mkdirResult) {
                    $error = error_get_last();
                    $errorMsg = $error ? $error['message'] : 'Unknown error';
                    throw new \RuntimeException("Failed to create app directory: $appPath - $errorMsg");
                }

                // Ensure ownership is correct (Security Fix: use sudo to handle host-mounted volumes)
                // This is critical since the container will run as 1000:1000
                @exec("sudo chown -R 1000:1000 " . escapeshellarg($appPath));
                @exec("sudo chmod -R 775 " . escapeshellarg($appPath));
            }

        // Initialize App Content
        if (!empty($githubRepo)) {
            $this->cloneGitHubRepo($appPath, $githubRepo, $githubBranch);
        } else {
            // Create starter app (no longer needs port)
            $this->createStarterApp($appPath, $appType, $domain);
        }

        // Get host path from environment variable, or fallback to default relative
        $hostPath = $_ENV['USER_APPS_HOST_PATH'] ?? realpath(__DIR__ . '/../../../storage/user-apps') ?: '/var/www/html/storage/user-apps';

        // Sanitize name for Traefik router name
        $routerName = preg_replace('/[^a-zA-Z0-9-]/', '-', $name);

        // Validate and Sanitize Domains for Traefik Labels
        $domains = array_filter(array_map('trim', explode(',', $domain)));
        $validDomains = [];
        foreach ($domains as $d) {
            // Strict validation: only alphanumeric, dots, hyphens
            if (preg_match('/^[a-zA-Z0-9.-]+$/', $d)) {
                $validDomains[] = $d;
            }
        }

        if (empty($validDomains)) {
            // Fallback or throw error? For now fallback to a safe default if provided was bad
            // or just use what we have if it passed regex (it won't if empty)
            // If $domain was malicious, $validDomains is empty.
            // We should probably throw exception if no valid domain found?
            // But existing code might rely on loose validation.
            // safe fallback:
            $validDomains[] = 'localhost';
        }

        $hostRules = array_map(fn($d) => "Host(`{$d}`)", $validDomains);
        $traefikRule = implode(' || ', $hostRules);

        // App type-specific port (Node.js: 3000, Python: 5000)
        $containerPort = ($appType === 'python') ? '5000' : '3000';

        // Resource Management: Shared/Burstable Model
        // 1. Memory: Reserve package limit (Soft), but allow bursting up to 4x (Hard)
        // 2. CPU: Allow bursting up to 4 cores, but prioritize based on package limit (Shares)

        $memVal = (int) $memoryLimit;
        $memUnit = strtoupper(substr($memoryLimit, -1));
        $memBytes = $memVal * ($memUnit === 'G' ? 1024 : 1) * 1024 * 1024;

        // Hard Limit = 1.5x Package Limit (shared hosting - prevents resource abuse)
        $burstMemBytes = $memBytes * 1.5;
        $burstMemLimit = ((int) ($burstMemBytes / (1024 * 1024))) . 'M';

        // CPU Shares (Weight): 1 Core = 1024 shares. Package limit defines weight.
        // e.g., 0.5 core package = 512 shares.
        $cpuShares = (int) ($cpuLimit * 1024);

        // Get available CPU count from system (default to 2 if detection fails)
        $availableCpus = 2;
        if (is_readable('/proc/cpuinfo')) {
            $cpuinfo = file_get_contents('/proc/cpuinfo');
            $availableCpus = max(1, substr_count($cpuinfo, 'processor'));
        }
        // Ensure correct ownership for the mounted volume before docker run
        // This fixes EACCES errors for npm install and pip install when files are created by PHP/root.
        @exec("sudo chown -R 1000:1000 " . escapeshellarg($appPath));
        @exec("sudo chmod -R 775 " . escapeshellarg($appPath));

        // Max CPU Burst: 1.5x package limit, capped at available CPUs
        // Shared hosting - prevents resource abuse
        $cpuBurst = min($availableCpus, $cpuLimit * 1.5);

        $command = [
            'docker',
            'run',
            '-d',
            '--name',
            $containerName,
            '--user',
            '1000:1000', // Run as non-root user (Security Fix)
            '--network',
            $this->network,
            '-v',
            "{$hostPath}:/storage",
            '-w',
            "/storage/{$name}",

            // --- Shared Resource Limits ---
            '--memory-reservation',
            $memoryLimit,   // Soft Limit (Guaranteed)
            '--memory',
            $burstMemLimit,             // Hard Limit (Burst Max)
            '--cpus',
            (string) $cpuBurst,           // CPU Burst Max
            '--cpu-shares',
            (string) $cpuShares,    // CPU Weight/Priority
            '--restart',
            'unless-stopped',

            // Labels
            '--label',
            'traefik.enable=true',
            '--label',
            "traefik.http.routers.{$routerName}.rule={$traefikRule}",
            '--label',
            "traefik.http.routers.{$routerName}.entrypoints=websecure",
            '--label',
            "traefik.http.routers.{$routerName}.tls=true",
            '--label',
            "traefik.http.routers.{$routerName}.tls.certresolver=letsencrypt",
            '--label',
            "traefik.http.services.{$routerName}.loadbalancer.server.port={$containerPort}",
        ];

        // Add environment variables (containerPort already defined above)
        $envVars['PORT'] = $containerPort;
        $envVars['HOST'] = '0.0.0.0';  // Important for Flask apps
        $envVars['PYTHONUNBUFFERED'] = '1'; // Important for Python logging
        $envVars['FLASK_RUN_HOST'] = '0.0.0.0'; 
        $envVars['FLASK_RUN_PORT'] = $containerPort;
        $envVars['APP_DOMAIN'] = $domain;  // App's assigned domain
        $envVars['PS1'] = "root@{$containerName}:~# "; // Terminal Prompt
        
        // Inject Python-specific path overrides globally so terminal sessions work without permission issues
        if ($appType === 'python') {
            $envVars['HOME'] = '/tmp';
            $envVars['XDG_CACHE_HOME'] = '/tmp/.cache';
            $envVars['PYTHONUSERBASE'] = '/tmp/.local';
            $envVars['PATH'] = '/tmp/.local/bin:/usr/local/bin:/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/sbin:/bin';
        }

        foreach ($envVars as $key => $value) {
            $command[] = '-e';
            $command[] = "{$key}={$value}";
        }

        // Add image and start command based on type
        $command[] = $image;

        if ($appType === 'nodejs') {
            $command[] = 'sh';
            $command[] = '-c';
            // Sequential command execution with proper error handling
            // 1. Fix permissions first
            // 2. cd to root directory if specified (for monorepos)
            // 3. Run install (wait for completion)
            // 4. Run post-install if specified (migrations, etc.)
            // 5. Run build if specified (wait for completion)
            // 6. Start the app
            // Using && ensures each step must succeed before proceeding
            $install = !empty($installCommand) ? $installCommand : 'npm install --prefer-offline --no-audit --no-fund 2>&1 || echo "npm install failed, continuing anyway"';
            $postInstall = !empty($postInstallCommand) ? $postInstallCommand : '';
            $build = !empty($buildCommand) ? $buildCommand : '';
            // Smart start: npm start reads package.json, fallback to common entry points
            $start = !empty($startCommand) ? $startCommand : 'npm start 2>/dev/null || node index.js 2>/dev/null || node server.js 2>/dev/null || node app.js';

            // Build the command chain with proper sequencing
            $cmdChain = 'echo "=== Deployment Started ===" && ';
            $cmdChain .= 'chown -R 1000:1000 . 2>/dev/null; '; // Fix permissions at start (use ; not && so we continue)

            // Change to root directory if specified (for monorepos)
            if (!empty($rootDirectory) && $rootDirectory !== './' && $rootDirectory !== '.') {
                $cmdChain .= 'echo "=== Changing to root directory: ' . escapeshellarg($rootDirectory) . ' ===" && ';
                $cmdChain .= 'cd ' . escapeshellarg($rootDirectory) . ' && ';
            }

            // Use timeout for install to prevent hanging 
            $cmdChain .= 'echo "=== Running install ===" && ';
            $cmdChain .= 'timeout 1200 sh -c ' . escapeshellarg('export NPM_CONFIG_PRODUCTION=false; ' . $install) . ' || echo "Install timed out or failed, continuing"; ';

            // Post-install command
            if (!empty($postInstall)) {
                $cmdChain .= 'echo "=== Running post-install ===" && ';
                $cmdChain .= 'timeout 600 sh -c ' . escapeshellarg($postInstall) . ' || echo "Post-install failed, continuing"; ';
            }

            if (!empty($build)) {
                $cmdChain .= 'echo "=== Running build ===" && ';
                $cmdChain .= 'timeout 600 sh -c ' . escapeshellarg($build) . ' || echo "Build failed, continuing"; ';
            }

            $cmdChain .= 'echo "=== Starting app ===" && ';
            $cmdChain .= $start;

            // If the app crashes, keep container alive for debugging
            $command[] = $cmdChain . ' || (echo "=== Process failed, keeping container alive for debugging ===" && tail -f /dev/null)';
        } else {
            $command[] = 'sh';
            $command[] = '-c';
            
            // --- Improved Python Deployment Logic ---
            
            // 1. Setup Python environment and install command
            // We use /tmp/.local for PYTHONUSERBASE to avoid permission issues if the volume is mounted read-only for uid 1000
            $envSetup = 'export HOME=/tmp; export XDG_CACHE_HOME=/tmp/.cache; export PYTHONUSERBASE=/tmp/.local; export PATH=$PYTHONUSERBASE/bin:$PATH; ';
            // Install essential build tools (setuptools<70 for pkg_resources compatibility with older packages)
            $baseInstall = $envSetup . 'pip install --user --upgrade "pip<24.1" "setuptools<70" wheel 2>/dev/null; pip install --user --no-cache-dir flask gunicorn uvicorn django 2>/dev/null || echo "Base dependencies install failed"';

            
            $userInstall = !empty($installCommand) ? $installCommand : '';
            
            // Smart Install Chain
            $installChain = $baseInstall . '; ';
            
            if (!empty($userInstall)) {
                $installChain .= $envSetup . $userInstall;
            } else {
                $installChain .= $envSetup . '
                if [ -f requirements.txt ]; then pip install --user --no-cache-dir -r requirements.txt; 
                elif [ -f requirements/base.txt ]; then pip install --user --no-cache-dir -r requirements/base.txt; 
                elif [ -f Pipfile ]; then pip install --user pipenv && python -m pipenv install --system; 
                elif [ -f pyproject.toml ]; then pip install --user .; 
                fi';
            }
            
            // 2. Build Command (optional)
            $buildChain = !empty($buildCommand) ? $buildCommand : 'echo "No build command"';
            
            // 3. Robust Start Command
            if (!empty($startCommand)) {
                $startChain = $envSetup . $startCommand;
            } else {
                // Smart Detection with Gunicorn/Uvicorn
                $startChain = $envSetup . '
                # Try Django
                MANAGE_PY=$(find . -maxdepth 2 -name "manage.py" -type f | head -1);
                if [ -n "$MANAGE_PY" ]; then
                    echo "Detected Django project";
                    DIR=$(dirname "$MANAGE_PY");
                    cd "$DIR" || true;
                    python manage.py migrate --noinput || true;
                    # Gets project name from directory name or searches for wsgi.py
                    PROJ_NAME=$(basename "$PWD");
                    if [ -f "$PROJ_NAME/wsgi.py" ]; then
                        gunicorn "$PROJ_NAME.wsgi:application" --bind 0.0.0.0:$PORT --workers 2;
                    else
                        python manage.py runserver 0.0.0.0:$PORT;
                    fi
                # Try Flask/WSGI
                elif [ -f "wsgi.py" ]; then
                    echo "Detected WSGI app";
                    gunicorn wsgi:application --bind 0.0.0.0:$PORT --workers 2;
                elif [ -f "app.py" ]; then
                    echo "Detected Flask app (app.py)";
                    gunicorn app:app --bind 0.0.0.0:$PORT --workers 2 || python app.py;
                elif [ -f "main.py" ]; then
                     echo "Detected Python script (main.py)";
                     python main.py;
                # Try ASGI
                elif [ -f "asgi.py" ]; then
                    echo "Detected ASGI app";
                    uvicorn asgi:application --host 0.0.0.0 --port $PORT;
                else
                    echo "No standard entry point found. Serving simple HTTP server...";
                    python -m http.server $PORT;
                fi';
            }
            
            // Assemble Command Chain
            $cmdChain = 'echo "=== Python Deployment Started ===" && ';
            $cmdChain .= 'chown -R 1000:1000 . 2>/dev/null; '; // Fix permissions (use ; not && to continue on failure)
            
            if (!empty($rootDirectory) && $rootDirectory !== './' && $rootDirectory !== '.') {
                 $cmdChain .= 'cd ' . escapeshellarg($rootDirectory) . ' && ';
            }
            
            if (!empty($githubRepo)) {
                // For GitHub repos, run the full chain
                $cmdChain .= 'echo "=== Installing Dependencies ===" && ';
                $cmdChain .= 'timeout 1200 sh -c ' . escapeshellarg($installChain) . ' || echo "Install failed/timed out"; ';
                
                if (!empty($postInstallCommand)) {
                    $cmdChain .= 'echo "=== Running Post-Install ===" && ';
                    $cmdChain .= 'timeout 600 sh -c ' . escapeshellarg($postInstallCommand) . ' || echo "Post-Install failed"; ';
                }
                
                $cmdChain .= 'echo "=== Starting App ===" && ';
                $cmdChain .= $startChain;
            } else {
                 // For starter apps or direct uploads - still need to install dependencies
                 $cmdChain .= 'echo "=== Installing Dependencies ===" && ';
                 $cmdChain .= 'timeout 1200 sh -c ' . escapeshellarg($installChain) . ' || echo "Install failed/timed out"; ';
                 $cmdChain .= 'echo "=== Starting App ===" && ';
                 $cmdChain .= $startChain;
            }
            
            // Keep alive on failure
            $command[] = $cmdChain . ' || (echo "=== App Failed ===" && tail -f /dev/null)';
        }

        $process = new Process($command);
        $process->setTimeout(1800); // 30 mins max for large apps pulling images + installing dependencies
        $process->run();

        if (!$process->isSuccessful()) {
            throw new ProcessFailedException($process);
        }

        $containerId = trim($process->getOutput());
        $this->log('INFO', "Container created: {$containerId} ({$containerName})");

        // Generate Traefik config file for this app
        $this->generateTraefikConfig($routerName, $domain, $containerName, $containerPort);
        
        $this->log('INFO', "Deployment successful for {$name}");

        return [
            'container_id' => $containerId,
            'container_name' => $containerName,
            'domain' => $domain,
            'app_path' => $appPath,
            'runtime_version' => $runtimeVersion
        ];
        
        } catch (\Exception $e) {
            $this->log('ERROR', "Deployment failed for {$name}: " . $e->getMessage());
            
            // Cleanup on failure
            $this->cleanupFailedDeployment($name, $containerName, $appPath);
            
            throw $e;
        }
    }

    /**
     * Generate Traefik dynamic config file for a user app
     * Supports multiple comma-separated domains
     */
    private function generateTraefikConfig(string $routerName, string $domain, string $containerName, string $port = '3000'): void
    {
        $configDir = __DIR__ . '/../../../docker/traefik/apps';

        // Ensure directory exists
        if (!is_dir($configDir)) {
            mkdir($configDir, 0755, true);
        }

        // Handle multiple comma-separated domains
        $domains = array_filter(array_map('trim', explode(',', $domain)));

        if (empty($domains)) {
            // No domains, remove config if exists
            @unlink("{$configDir}/{$routerName}.yml");
            return;
        }

        // Build Traefik rule for multiple domains: Host(`a.com`) || Host(`b.com`)
        $hostRules = array_map(fn($d) => "Host(`{$d}`)", $domains);
        $traefikRule = implode(' || ', $hostRules);

        $configContent = <<<YAML
# Auto-generated Traefik config for {$routerName}
http:
  routers:
    {$routerName}:
      entryPoints:
        - websecure
      rule: "{$traefikRule}"
      service: {$routerName}
      tls:
        certResolver: letsencrypt

  services:
    {$routerName}:
      loadBalancer:
        servers:
          - url: "http://{$containerName}:{$port}"
YAML;

        file_put_contents("{$configDir}/{$routerName}.yml", $configContent);

        // Trigger Traefik to reload config by sending SIGHUP signal
        $this->reloadTraefik();
    }

    /**
     * Reload Traefik to pick up new config files
     */
    private function reloadTraefik(): void
    {
        $process = new Process(['docker', 'kill', '--signal=SIGHUP', 'logicpanel_traefik']);
        $process->setTimeout(10);
        $process->run();
        // Ignore errors - Traefik will still pick up changes on next request or restart
    }

    /**
     * Remove Traefik config file for a user app
     */
    public function removeTraefikConfig(string $name): void
    {
        $routerName = preg_replace('/[^a-zA-Z0-9-]/', '-', $name);
        $configFile = __DIR__ . "/../../../docker/traefik/apps/{$routerName}.yml";

        if (file_exists($configFile)) {
            unlink($configFile);
        }
    }

    /**
     * Create starter Hello World app
     */
    private function createStarterApp(string $appPath, string $type, string $domain): void
    {
        // Verify directory exists
        if (!is_dir($appPath)) {
            throw new \RuntimeException("App directory does not exist: $appPath");
        }

        if ($type === 'nodejs') {
            // Node.js Image Mapping
            $nodeImages = [
                'Node.js 22 LTS' => 'node:22-alpine',
                'Node.js 20 LTS' => 'node:20-alpine',
                'Node.js 18 LTS' => 'node:18-alpine',
                'Node.js 16 LTS' => 'node:16-alpine',
            ];

            // Python Image Mapping (using slim variants as requested previously)
            $pythonImages = [
                'Python 3.13 (Latest)' => 'python:3.13-slim',
                'Python 3.12' => 'python:3.12-slim',
                'Python 3.11 (Recommended)' => 'python:3.11-slim',
                'Python 3.11 (Full Build Tools)' => 'python:3.11',
                'Python 3.10' => 'python:3.10-slim',
                'Python 3.10 (Full Build Tools)' => 'python:3.10',
                'Python 3.9' => 'python:3.9-slim',
                'Python 3.8' => 'python:3.8-slim',
            ];
            // Create package.json
            $packageJson = [
                'name' => 'logicpanel-app',
                'version' => '1.0.0',
                'main' => 'server.js',
                'scripts' => [
                    'start' => 'node server.js'
                ],
                'dependencies' => new \stdClass()
            ];
            file_put_contents($appPath . '/package.json', json_encode($packageJson, JSON_PRETTY_PRINT));

            // Create server.js with branded LogicPanel design
            $serverJs = <<<'JS'
const http = require('http');
const PORT = process.env.PORT || 3000;
const DOMAIN = (process.env.APP_DOMAIN || 'localhost').split(',')[0].trim();

const server = http.createServer((req, res) => {
    res.writeHead(200, { 'Content-Type': 'text/html' });
    res.end(`
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>App Deployed - LogicPanel</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        *{margin:0;padding:0;box-sizing:border-box}
        body{font-family:'Inter',sans-serif;min-height:100vh;background:#1E2127;display:flex;align-items:center;justify-content:center;padding:20px;position:relative;overflow:hidden}
        body::before{content:'';position:absolute;width:600px;height:600px;border-radius:50%;background:radial-gradient(circle,rgba(60,135,58,0.08) 0%,transparent 70%);top:-200px;right:-200px}
        body::after{content:'';position:absolute;width:400px;height:400px;border-radius:50%;background:radial-gradient(circle,rgba(60,135,58,0.05) 0%,transparent 70%);bottom:-100px;left:-100px}
        .card{max-width:400px;width:100%;background:#282C34;border:1px solid rgba(255,255,255,0.06);border-radius:14px;padding:28px 28px;text-align:center;position:relative;z-index:1;box-shadow:0 20px 60px rgba(0,0,0,0.4)}
        .card::before{content:'';position:absolute;top:-1px;left:20%;right:20%;height:2px;background:linear-gradient(90deg,transparent,#3C873A,transparent);border-radius:2px}
        h1{color:#E5E7EB;font-size:18px;font-weight:600;margin-bottom:6px}
        .sub{color:#9CA3AF;font-size:12px;line-height:1.5;margin-bottom:18px}
        .domain{background:#1E2127;border:1px solid rgba(255,255,255,0.08);border-radius:8px;padding:10px 14px;margin-bottom:18px}
        .domain-label{color:#6B7280;font-size:10px;text-transform:uppercase;letter-spacing:1.5px;margin-bottom:6px}
        .domain-url{color:#4ADE80;font-family:'SF Mono',Monaco,'Courier New',monospace;font-size:13px;word-break:break-all}
        .domain-url a{color:inherit;text-decoration:none}
        .domain-url a:hover{text-decoration:underline}
        .steps{text-align:left;margin-bottom:18px}
        .steps-title{color:#9CA3AF;font-size:10px;text-transform:uppercase;letter-spacing:1px;margin-bottom:8px}
        .step{display:flex;align-items:flex-start;gap:8px;padding:5px 0;color:#9CA3AF;font-size:11px;line-height:1.4}
        .step-num{width:18px;height:18px;background:rgba(60,135,58,0.15);border:1px solid rgba(60,135,58,0.3);border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:9px;font-weight:600;color:#4ADE80;flex-shrink:0;margin-top:1px}
        .runtime{display:flex;gap:10px;justify-content:center;margin-bottom:18px}
        .runtime-item{background:#1E2127;border:1px solid rgba(255,255,255,0.06);border-radius:6px;padding:8px 12px;flex:1}
        .runtime-label{color:#6B7280;font-size:8px;text-transform:uppercase;letter-spacing:1px;margin-bottom:3px}
        .runtime-val{color:#E5E7EB;font-size:11px;font-weight:500}
        .brand{padding-top:14px;border-top:1px solid rgba(255,255,255,0.04);color:#6B7280;font-size:10px}
        .brand a{color:#9CA3AF;text-decoration:none}
    </style>
</head>
<body>
    <div class="card">
        <h1>Deployment Successful</h1>
        <p class="sub">Your application is deployed and running. Use File Manager to upload your code.</p>
        <div class="domain">
            <div class="domain-label">Application URL</div>
            <div class="domain-url"><a href="https://${DOMAIN}">${DOMAIN}</a></div>
        </div>
        <div class="runtime">
            <div class="runtime-item">
                <div class="runtime-label">Runtime</div>
                <div class="runtime-val">Node.js</div>
            </div>
            <div class="runtime-item">
                <div class="runtime-label">Port</div>
                <div class="runtime-val">${PORT}</div>
            </div>
        </div>
        <div class="steps">
            <div class="steps-title">Getting Started</div>
            <div class="step"><span class="step-num">1</span> Open File Manager from your LogicPanel dashboard</div>
            <div class="step"><span class="step-num">2</span> Edit or replace server.js with your application code</div>
            <div class="step"><span class="step-num">3</span> Restart the app from the panel to see your changes</div>
        </div>
        <div class="brand">Powered by <a href="#">LogicPanel</a></div>
    </div>
</body>
</html>
    `);
});

server.listen(PORT, '0.0.0.0', () => {
    console.log(`Server running at http://0.0.0.0:${PORT}/ (Domain: ${DOMAIN})`);
});
JS;
            file_put_contents($appPath . '/server.js', str_replace("\r", '', $serverJs));

        } else {
            // Python built-in HTTP server app with branded design (no dependencies)
            $appPy = <<<'PY'
import http.server
import socketserver
import os

PORT = int(os.environ.get('PORT', 3000))
DOMAIN = os.environ.get('APP_DOMAIN', 'localhost').split(',')[0].strip()

html_content = f"""<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>App Deployed - LogicPanel</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        *{{margin:0;padding:0;box-sizing:border-box}}
        body{{font-family:'Inter',sans-serif;min-height:100vh;background:#1E2127;display:flex;align-items:center;justify-content:center;padding:20px;position:relative;overflow:hidden}}
        body::before{{content:'';position:absolute;width:600px;height:600px;border-radius:50%;background:radial-gradient(circle,rgba(60,135,58,0.08) 0%,transparent 70%);top:-200px;right:-200px}}
        body::after{{content:'';position:absolute;width:400px;height:400px;border-radius:50%;background:radial-gradient(circle,rgba(60,135,58,0.05) 0%,transparent 70%);bottom:-100px;left:-100px}}
        .card{{max-width:400px;width:100%;background:#282C34;border:1px solid rgba(255,255,255,0.06);border-radius:14px;padding:28px 28px;text-align:center;position:relative;z-index:1;box-shadow:0 20px 60px rgba(0,0,0,0.4)}}
        .card::before{{content:'';position:absolute;top:-1px;left:20%;right:20%;height:2px;background:linear-gradient(90deg,transparent,#3C873A,transparent);border-radius:2px}}
        h1{{color:#E5E7EB;font-size:18px;font-weight:600;margin-bottom:6px}}
        .sub{{color:#9CA3AF;font-size:12px;line-height:1.5;margin-bottom:18px}}
        .domain{{background:#1E2127;border:1px solid rgba(255,255,255,0.08);border-radius:8px;padding:10px 14px;margin-bottom:18px}}
        .domain-label{{color:#6B7280;font-size:10px;text-transform:uppercase;letter-spacing:1.5px;margin-bottom:6px}}
        .domain-url{{color:#4ADE80;font-family:'SF Mono',Monaco,'Courier New',monospace;font-size:13px;word-break:break-all}}
        .domain-url a{{color:inherit;text-decoration:none}}
        .domain-url a:hover{{text-decoration:underline}}
        .steps{{text-align:left;margin-bottom:18px}}
        .steps-title{{color:#9CA3AF;font-size:10px;text-transform:uppercase;letter-spacing:1px;margin-bottom:8px}}
        .step{{display:flex;align-items:flex-start;gap:8px;padding:5px 0;color:#9CA3AF;font-size:11px;line-height:1.4}}
        .step-num{{width:18px;height:18px;background:rgba(60,135,58,0.15);border:1px solid rgba(60,135,58,0.3);border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:9px;font-weight:600;color:#4ADE80;flex-shrink:0;margin-top:1px}}
        .runtime{{display:flex;gap:10px;justify-content:center;margin-bottom:18px}}
        .runtime-item{{background:#1E2127;border:1px solid rgba(255,255,255,0.06);border-radius:6px;padding:8px 12px;flex:1}}
        .runtime-label{{color:#6B7280;font-size:8px;text-transform:uppercase;letter-spacing:1px;margin-bottom:3px}}
        .runtime-val{{color:#E5E7EB;font-size:11px;font-weight:500}}
        .brand{{padding-top:14px;border-top:1px solid rgba(255,255,255,0.04);color:#6B7280;font-size:10px}}
        .brand a{{color:#9CA3AF;text-decoration:none}}
    </style>
</head>
<body>
    <div class="card">
        <h1>Deployment Successful</h1>
        <p class="sub">Your application is deployed and running. Use File Manager to upload your code.</p>
        <div class="domain">
            <div class="domain-label">Application URL</div>
            <div class="domain-url"><a href="https://{DOMAIN}">{DOMAIN}</a></div>
        </div>
        <div class="runtime">
            <div class="runtime-item">
                <div class="runtime-label">Runtime</div>
                <div class="runtime-val">Python</div>
            </div>
            <div class="runtime-item">
                <div class="runtime-label">Port</div>
                <div class="runtime-val">{PORT}</div>
            </div>
        </div>
        <div class="steps">
            <div class="steps-title">Getting Started</div>
            <div class="step"><span class="step-num">1</span> Open File Manager from your LogicPanel dashboard</div>
            <div class="step"><span class="step-num">2</span> Edit or replace app.py with your application code</div>
            <div class="step"><span class="step-num">3</span> Restart the app from the panel to see your changes</div>
        </div>
        <div class="brand">Powered by <a href="#">LogicPanel</a></div>
    </div>
</body>
</html>"""

class MyRequestHandler(http.server.SimpleHTTPRequestHandler):
    def do_GET(self):
        if self.path == '/':
            self.send_response(200)
            self.send_header('Content-Type', 'text/html')
            self.end_headers()
            self.wfile.write(html_content.encode('utf-8'))
        else:
            super().do_GET()

if __name__ == '__main__':
    with socketserver.TCPServer(("0.0.0.0", PORT), MyRequestHandler) as httpd:
        print(f"Server running at http://0.0.0.0:{PORT}/ (Domain: {DOMAIN})")
        httpd.serve_forever()
PY;

            file_put_contents($appPath . '/app.py', str_replace("\r", '', $appPy));
            
            // Note: We deliberately skip creating requirements.txt 
            // so the Python app deploys instantly without pip install delays.
        }

        // Fix permissions using PHP native functions
        $this->recursiveChmod($appPath, 0777);
    }

    private function cloneGitHubRepo(string $appPath, string $repoUrl, string $branch = 'main'): void
    {
        // Ensure directory allows cloning (must be empty or we clone to temp and move)
        // Check if directory is empty
        if (is_dir($appPath) && count(scandir($appPath)) > 2) {
            // Directory not empty - clean it
            $files = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($appPath, \RecursiveDirectoryIterator::SKIP_DOTS),
                \RecursiveIteratorIterator::CHILD_FIRST
            );

            foreach ($files as $fileinfo) {
                if ($fileinfo->isDir()) {
                    rmdir($fileinfo->getRealPath());
                } else {
                    unlink($fileinfo->getRealPath());
                }
            }
        }

        $command = ['git', 'clone', '-b', $branch, $repoUrl, '.'];

        $process = new Process($command, $appPath);
        $process->setTimeout(600); // 10 minutes for clone (Increased for larger repos)
        $process->run();

        if (!$process->isSuccessful()) {
            $output = $process->getErrorOutput();
            throw new \RuntimeException("Failed to clone GitHub repository: $output");
        }

        // Remove .git directory to avoid issues? Or keep it? Keeping it enables updates later.
        // For now keep it.
        $this->recursiveChmod($appPath, 0777);
    }

    private function recursiveChmod($path, $mode)
    {
        if (!file_exists($path)) {
            return;
        }

        // Apply to base path first
        $isDir = is_dir($path);
        chmod($path, $isDir ? 0755 : 0644);
        
        // Ensure ownership is 1000:1000 (if running as root)
        if (posix_geteuid() === 0) {
            @chown($path, 1000);
            @chgrp($path, 1000);
        }

        if ($isDir) {
            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($path, \RecursiveDirectoryIterator::SKIP_DOTS),
                \RecursiveIteratorIterator::SELF_FIRST
            );

            foreach ($iterator as $item) {
                $p = $item->getPathname();
                if (is_dir($p)) {
                    @chmod($p, 0755);
                } else {
                    @chmod($p, 0644);
                }

                // Ensure ownership is 1000:1000 (if running as root)
                if (posix_geteuid() === 0) {
                    @chown($p, 1000);
                    @chgrp($p, 1000);
                }
            }
        }
    }

    public function startContainer(string $containerId): void
    {
        $this->runCommand(['docker', 'start', $containerId]);
    }

    public function stopContainer(string $containerId): void
    {
        $this->runCommand(['docker', 'stop', $containerId]);
    }

    public function restartContainer(string $containerId): void
    {
        $this->runCommand(['docker', 'restart', $containerId]);
    }

    public function removeContainer(string $containerId): void
    {
        $this->runCommand(['docker', 'rm', '-f', $containerId]);
    }

    /**
     * Completely remove the application storage directory
     */
    public function removeAppDirectory(string $name): void
    {
        $appPath = $this->userAppsPath . "/{$name}";
        
        if (!is_dir($appPath)) {
            return;
        }

        // Recursive delete using shell for speed and power (careful with inputs!)
        // Since $name is sanitized in controller, this is relatively safe, but let's be extra safe
        $sanitizedName = preg_replace('/[^a-zA-Z0-9-]/', '', $name);
        
        if (empty($sanitizedName) || $sanitizedName !== $name) {
            // Fallback to PHP recursive delete if name looks suspicious
            $this->recursiveDelete($appPath);
            return;
        }

        // Fast deletion for valid names
        // Check if path exists and is definitely within userAppsPath to prevent disasters
        $realUserAppsPath = realpath($this->userAppsPath);
        $realAppPath = realpath($appPath);

        if ($realAppPath && strpos($realAppPath, $realUserAppsPath) === 0 && strlen($realAppPath) > strlen($realUserAppsPath)) {
             // Use rm -rf
             $process = new Process(['rm', '-rf', $appPath]);
             $process->run();
        } else {
             // Fallback
             $this->recursiveDelete($appPath);
        }
    }

    private function recursiveDelete(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        
        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($files as $fileinfo) {
            if ($fileinfo->isDir()) {
                @rmdir($fileinfo->getRealPath());
            } else {
                @unlink($fileinfo->getRealPath());
            }
        }
        @rmdir($dir);
    }

    public function getContainerLogs(string $containerId, int $lines = 100): string
    {
        $process = new Process(['docker', 'logs', '--tail', (string) $lines, $containerId]);
        $process->run();

        return $process->getOutput();
    }

    public function getContainerStats(string $containerId): array
    {
        $stats = [
            'cpu' => '0%',
            'memory' => '0 / 0',
            'net' => '0 / 0', // Net IO is hard to get efficiently via exec without docker stats, skipping or leaving 0
            'disk' => 'Unknown'
        ];

        try {
            // 1. Memory Usage
            // Try Cgroup V2 first, then V1
            // Run shell command to trying both paths
            $memCmd = "cat /sys/fs/cgroup/memory.current 2>/dev/null || cat /sys/fs/cgroup/memory/memory.usage_in_bytes 2>/dev/null";
            $memProcess = new Process(['docker', 'exec', $containerId, 'sh', '-c', $memCmd]);
            $memProcess->run();

            $memUsage = 0;
            if ($memProcess->isSuccessful()) {
                $memUsage = (int) trim($memProcess->getOutput());
            }

            // Memory Limit
            $limitCmd = "cat /sys/fs/cgroup/memory.max 2>/dev/null || cat /sys/fs/cgroup/memory/memory.limit_in_bytes 2>/dev/null";
            $limitProcess = new Process(['docker', 'exec', $containerId, 'sh', '-c', $limitCmd]);
            $limitProcess->run();
            $memLimit = 0;
            if ($limitProcess->isSuccessful()) {
                $memLimit = (int) trim($limitProcess->getOutput());
            }

            // Format Memory
            $usageFmt = $this->formatBytes($memUsage);
            // If limit is incredibly huge (unlimited), just show usage
            if ($memLimit > 1000000000000) {
                $limitFmt = '∞';
            } // > 1TB
            else {
                $limitFmt = $this->formatBytes($memLimit);
            }

            $stats['memory'] = "{$usageFmt} / {$limitFmt}";

            // 2. CPU Usage
            // Using 'top' in batch mode to get a snapshot
            // Alpine/BusyBox top format: "CPU:   0% usr   0% sys..."
            $cpuProcess = new Process(['docker', 'exec', $containerId, 'top', '-b', '-n', '1']);
            $cpuProcess->run();

            if ($cpuProcess->isSuccessful()) {
                $output = $cpuProcess->getOutput();
                // Look for line starting with CPU:
                if (preg_match('/CPU:\s+([0-9.]+)%\s+usr\s+([0-9.]+)%\s+sys/', $output, $matches)) {
                    $usr = (float) $matches[1];
                    $sys = (float) $matches[2];
                    $totalCpu = $usr + $sys;
                    $stats['cpu'] = round($totalCpu, 1) . '%';
                }
            }

            // 3. Disk Usage (App Storage)
            // We check /storage as it's the volume where app files reside.
            $diskProcess = new Process(['docker', 'exec', $containerId, 'du', '-sh', '/storage']);
            $diskProcess->setTimeout(5);
            $diskProcess->run();
            if ($diskProcess->isSuccessful()) {
                $diskOut = trim($diskProcess->getOutput());
                $parts = preg_split('/\s+/', $diskOut);
                $stats['disk'] = $parts[0] ?? '0B';
            }

        } catch (\Exception $e) {
            // Log error if needed, but return zero/safe stats to prevent UI crash
        }

        return $stats;
    }

    private function formatBytes($bytes, $precision = 2)
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        $bytes /= pow(1024, $pow);
        return round($bytes, $precision) . '' . $units[$pow];
    }

    public function executeCommand(string $containerId, string $command): string
    {
        $process = new Process(['docker', 'exec', $containerId, 'sh', '-c', $command]);
        $process->setTimeout(60);
        $process->run();

        if (!$process->isSuccessful()) {
            throw new \RuntimeException($process->getErrorOutput());
        }

        return $process->getOutput();
    }

    public function isContainerRunning(string $containerId): bool
    {
        $process = new Process(['docker', 'inspect', '-f', '{{.State.Running}}', $containerId]);
        $process->run();

        return trim($process->getOutput()) === 'true';
    }

    public function getAvailablePort($start = 3000, $end = 4000): int
    {
        $logFile = sys_get_temp_dir() . '/logicpanel_docker_debug.log';

        // Get all currently used ports by running containers
        // We need to check what ports are bound on the host
        $process = new Process(['docker', 'ps', '-a', '--format', '{{.Ports}}']);
        $process->run();
        $output = $process->getOutput();

        file_put_contents($logFile, date('Y-m-d H:i:s') . " - getAvailablePort: docker ps output: $output\n", FILE_APPEND);

        $usedPorts = [];
        // Output format examples:
        // 0.0.0.0:8000->80/tcp, [::]:8000->80/tcp
        // 0.0.0.0:3000->3000/tcp

        // Match ports bound to 0.0.0.0
        preg_match_all('/0\.0\.0\.0:(\d+)/', $output, $matches);
        if (!empty($matches[1])) {
            $usedPorts = array_merge($usedPorts, array_map('intval', $matches[1]));
        }

        // Also check database for assigned ports (more reliable)
        try {
            $dbPorts = \LogicPanel\Domain\Service\Service::pluck('port')->toArray();
            $usedPorts = array_merge($usedPorts, array_map('intval', $dbPorts));
        } catch (\Exception $e) {
            file_put_contents($logFile, date('Y-m-d H:i:s') . " - getAvailablePort: DB check failed: " . $e->getMessage() . "\n", FILE_APPEND);
        }

        $usedPorts = array_unique($usedPorts);
        file_put_contents($logFile, date('Y-m-d H:i:s') . " - getAvailablePort: usedPorts: " . implode(',', $usedPorts) . "\n", FILE_APPEND);

        for ($port = $start; $port < $end; $port++) {
            if (!in_array($port, $usedPorts)) {
                file_put_contents($logFile, date('Y-m-d H:i:s') . " - getAvailablePort: returning port $port\n", FILE_APPEND);
                return $port;
            }
        }

        throw new \RuntimeException('No available ports found in range ' . $start . '-' . $end);
    }

    private function runCommand(array $command): void
    {
        $process = new Process($command);
        $process->setTimeout(30);
        $process->run();

        if (!$process->isSuccessful()) {
            throw new ProcessFailedException($process);
        }
    }

    // --- Enhanced Logging and Error Handling ---

    private function log(string $level, string $message, array $context = []): void
    {
        $logFile = '/var/log/logicpanel/docker-service.log';
        $timestamp = date('Y-m-d H:i:s');
        $contextStr = !empty($context) ? json_encode($context) : '';
        
        $logMessage = "[{$timestamp}] [{$level}] {$message} {$contextStr}\n";
        
        @file_put_contents($logFile, $logMessage, FILE_APPEND);
    }

    public function cleanupFailedDeployment(string $name, string $containerName, string $appPath): void 
    {
        try {
            $this->log('INFO', "Cleaning up failed deployment: {$name}");
            
            // Remove container if exists
            $process = new Process(['docker', 'rm', '-f', $containerName]);
            $process->run();
            
            // Remove Traefik config
            $this->removeTraefikConfig($name);
            
            // Remove app directory
            if (is_dir($appPath)) {
                $this->removeAppDirectory($name);
            }
            
        } catch (\Exception $e) {
            $this->log('WARNING', "Cleanup failed for {$name}: {$e->getMessage()}");
        }
    }

    // --- Container Health and Isolation ---

    public function checkContainerHealth(string $containerId): array
    {
        $health = [
            'status' => 'unknown',
            'running' => false,
            'details' => []
        ];
        
        try {
            $health['running'] = $this->isContainerRunning($containerId);
            
            if (!$health['running']) {
                $health['status'] = 'stopped';
                return $health;
            }
            
            $process = new Process(['docker', 'inspect', '--format', '{{json .State}}', $containerId]);
            $process->run();
            
            if ($process->isSuccessful()) {
                $state = json_decode($process->getOutput(), true);
                $health['details'] = $state;
                if (isset($state['Health'])) {
                    $health['status'] = strtolower($state['Health']['Status']);
                } else {
                    $health['status'] = $state['Running'] ? 'running' : 'stopped';
                }
            }
            
            return $health;
            
        } catch (\Exception $e) {
            $this->log('ERROR', "Health check failed for {$containerId}: " . $e->getMessage());
            $health['status'] = 'error';
            return $health;
        }
    }

    public function verifyContainerIsolation(string $containerId): array
    {
         $isolation = [
            'isolated' => false,
            'network' => null,
            'details' => []
        ];
        
        try {
            $process = new Process(['docker', 'inspect', '--format', '{{json .NetworkSettings}}', $containerId]);
            $process->run();
            
            if ($process->isSuccessful()) {
                $network = json_decode($process->getOutput(), true);
                $isolation['network'] = $network['Networks'] ?? [];
                $isolation['isolated'] = isset($network['Networks'][$this->network]);
            }
            
            return $isolation;
            
        } catch (\Exception $e) {
            $this->log('ERROR', "Isolation check failed for {$containerId}: " . $e->getMessage());
            return $isolation;
        }
    }
}
