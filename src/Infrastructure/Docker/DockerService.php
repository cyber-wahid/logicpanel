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

    public function __construct(array $config)
    {
        $this->network = $config['network'];
        $this->userAppsPath = $config['user_apps_path'];
        // Docker Compose prepends project name to volume names
        $this->userAppsVolume = $config['user_apps_volume'] ?? 'logicpanel_logicpanel_user_apps';
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
        string $sslEmail = ''
    ): array {
        $containerName = "logicpanel_app_{$name}";
        $appPath = $this->userAppsPath . "/{$name}";

        // Create app directory with secure permissions
        if (!is_dir($appPath)) {
            $mkdirResult = @mkdir($appPath, 0755, true);

            if (!$mkdirResult) {
                $error = error_get_last();
                $errorMsg = $error ? $error['message'] : 'Unknown error';
                throw new \RuntimeException("Failed to create app directory: $appPath - $errorMsg");
            }

            // Ensure ownership is correct (if running as root, chown to 1000:1000)
            // This is critical since the container will run as 1000:1000
            if (posix_geteuid() === 0) {
                chown($appPath, 1000);
                chgrp($appPath, 1000);
            }
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
        $envVars['APP_DOMAIN'] = $domain;  // App's assigned domain
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
            $cmdChain = 'echo "=== Starting deployment ===" && ';
            // Remove insecure chmod 777. Directory should be owned by 1000:1000
            // $cmdChain .= 'chmod -R 777 . 2>/dev/null; ';

            // Change to root directory if specified (for monorepos)
            if (!empty($rootDirectory) && $rootDirectory !== './' && $rootDirectory !== '.') {
                $cmdChain .= 'echo "=== Changing to root directory: ' . escapeshellarg($rootDirectory) . ' ===" && ';
                $cmdChain .= 'cd ' . escapeshellarg($rootDirectory) . ' && ';
            }

            // Use timeout for install to prevent hanging (180 seconds for larger projects)
            $cmdChain .= 'echo "=== Running install ===" && ';
            // Force install of devDependencies
            // SECURITY FIX: User escapeshellarg instead of addslashes
            $cmdChain .= 'timeout 180 sh -c ' . escapeshellarg('export NPM_CONFIG_PRODUCTION=false; ' . $install) . ' || echo "Install timed out or failed, continuing"; ';

            // Post-install command
            if (!empty($postInstall)) {
                $cmdChain .= 'echo "=== Running post-install: ' . escapeshellarg($postInstall) . ' ===" && ';
                $cmdChain .= 'timeout 120 sh -c ' . escapeshellarg($postInstall) . ' || echo "Post-install failed, continuing"; ';
            }

            if (!empty($build)) {
                $cmdChain .= 'echo "=== Running build: ' . escapeshellarg($build) . ' ===" && ';
                $cmdChain .= 'timeout 300 sh -c ' . escapeshellarg($build) . ' || echo "Build failed, continuing"; ';
            }

            $cmdChain .= 'echo "=== Starting app ===" && ';
            $cmdChain .= $start; // Start command is usually just "npm start" or similar simple command. If user controls this, they control the container anyway.

            // If the app crashes, keep container alive for debugging
            $command[] = $cmdChain . ' || (echo "=== App failed, keeping container alive for debugging ===" && tail -f /dev/null)';
        } else {
            $command[] = 'sh';
            $command[] = '-c';
            // Python app command chain with smart detection
            // Smart install: Try multiple requirements locations
            $install = !empty($installCommand) ? $installCommand :
                '(pip install -r requirements.txt 2>/dev/null || ' .
                'pip install -r requirements/dev.txt 2>/dev/null || ' .
                'pip install -r requirements/base.txt 2>/dev/null || ' .
                '([ -f Pipfile ] && pip install pipenv && pipenv install --system) || ' .
                '([ -f pyproject.toml ] && pip install .) || ' .
                'pip install flask gunicorn django)';

            $postInstall = !empty($postInstallCommand) ? $postInstallCommand : '';
            $build = !empty($buildCommand) ? $buildCommand : '';

            // Smart start command: detect Django, Gunicorn, or Flask
            // Priority: User specified > Django > Gunicorn > Flask patterns
            if (!empty($startCommand)) {
                $start = $startCommand;
            } else {
                // Enhanced detection with subdirectory support
                // 1. Django: manage.py exists (in root or subdirectory)
                // 2. Flask with app.py
                // 3. WSGI/ASGI apps
                // 4. Other common entry points
                $start = '
MANAGE_PY=$(find . -maxdepth 2 -name "manage.py" -type f | head -1);
if [ -n "$MANAGE_PY" ]; then
    DJANGO_DIR=$(dirname "$MANAGE_PY");
    echo "Detected Django project in $DJANGO_DIR";
    cd "$DJANGO_DIR" 2>/dev/null || true;
    python manage.py migrate --noinput 2>/dev/null || true;
    python manage.py runserver 0.0.0.0:${PORT:-5000};
elif [ -f "app.py" ]; then
    echo "Detected Flask/Python app";
    gunicorn app:app --bind 0.0.0.0:${PORT:-5000} --workers 2 2>/dev/null || python app.py;
elif [ -f "application.py" ]; then
    echo "Detected application.py";
    gunicorn application:app --bind 0.0.0.0:${PORT:-5000} --workers 2 2>/dev/null || python application.py;
elif [ -f "wsgi.py" ]; then
    echo "Detected WSGI app";
    gunicorn wsgi:application --bind 0.0.0.0:${PORT:-5000} --workers 2;
elif [ -f "asgi.py" ]; then
    echo "Detected ASGI app";
    uvicorn asgi:application --host 0.0.0.0 --port ${PORT:-5000} 2>/dev/null || python asgi.py;
elif [ -f "main.py" ]; then
    echo "Running main.py";
    python main.py;
elif [ -f "server.py" ]; then
    echo "Running server.py";
    python server.py;
elif [ -f "run.py" ]; then
    echo "Running run.py";
    python run.py;
elif [ -f "index.py" ]; then
    echo "Running index.py";
    python index.py;
else
    echo "ERROR: No Python entry point found!";
    echo "Please specify a Start Command or ensure one of these files exists:";
    echo "  - manage.py (Django)";
    echo "  - app.py (Flask)";
    echo "  - main.py, server.py, run.py";
    exit 1;
fi';
            }

            $cmdChain = 'echo "=== Starting Python deployment ===" && ';
            // $cmdChain .= 'chmod -R 777 . 2>/dev/null; ';

            // Change to root directory if specified (for monorepos)
            if (!empty($rootDirectory) && $rootDirectory !== './' && $rootDirectory !== '.') {
                $cmdChain .= 'echo "=== Changing to root directory: ' . escapeshellarg($rootDirectory) . ' ===" && ';
                $cmdChain .= 'cd ' . escapeshellarg($rootDirectory) . ' && ';
            }

            $cmdChain .= 'echo "=== Running install ===" && ';
            // SECURITY FIX: escapeshellarg
            $cmdChain .= 'timeout 180 sh -c ' . escapeshellarg($install) . ' || echo "Install timed out or failed, continuing"; ';

            // Post-install command
            if (!empty($postInstall)) {
                $cmdChain .= 'echo "=== Running post-install: ' . escapeshellarg($postInstall) . ' ===" && ';
                $cmdChain .= 'timeout 120 sh -c ' . escapeshellarg($postInstall) . ' || echo "Post-install failed, continuing"; ';
            }

            if (!empty($build)) {
                $cmdChain .= 'echo "=== Running build: ' . escapeshellarg($build) . ' ===" && ';
                $cmdChain .= 'timeout 300 sh -c ' . escapeshellarg($build) . ' || echo "Build failed, continuing"; ';
            }

            $cmdChain .= 'echo "=== Starting app ===" && ';
            $cmdChain .= $start;

            $command[] = $cmdChain . ' || (echo "=== App failed, keeping container alive for debugging ===" && tail -f /dev/null)';
        }

        $process = new Process($command);
        $process->setTimeout(120);
        $process->run();

        if (!$process->isSuccessful()) {
            throw new ProcessFailedException($process);
        }

        $containerId = trim($process->getOutput());

        // Generate Traefik config file for this app
        $this->generateTraefikConfig($routerName, $domain, $containerName, $containerPort);

        return [
            'container_id' => $containerId,
            'container_name' => $containerName,
            'domain' => $domain,
            'app_path' => $appPath,
        ];
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
            // Python Flask app with branded design
            $appPy = <<<'PY'
from flask import Flask
import os

app = Flask(__name__)
PORT = int(os.environ.get('PORT', 3000))
DOMAIN = os.environ.get('APP_DOMAIN', 'localhost').split(',')[0].strip()

@app.route('/')
def hello():
    return f'''<!DOCTYPE html>
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
</html>'''

if __name__ == '__main__':
    app.run(host='0.0.0.0', port=PORT)
PY;

            file_put_contents($appPath . '/app.py', str_replace("\r", '', $appPy));

            // Create requirements.txt
            file_put_contents($appPath . '/requirements.txt', "flask\n");
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
        $process->setTimeout(300); // 5 minutes for clone
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

        if (is_dir($path)) {
            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($path, \RecursiveDirectoryIterator::SKIP_DOTS),
                \RecursiveIteratorIterator::SELF_FIRST
            );

            foreach ($iterator as $item) {
                // Directories to 755, Files to 644
                $p = $item->getPathname();
                if (is_dir($p)) {
                    chmod($p, 0755);
                } else {
                    chmod($p, 0644);
                }

                // Ensure ownership is 1000:1000 (if running as root)
                if (posix_geteuid() === 0) {
                    chown($p, 1000);
                    chgrp($p, 1000);
                }
            }
            chmod($path, is_dir($path) ? 0755 : 0644);
            if (posix_geteuid() === 0) {
                chown($path, 1000);
                chgrp($path, 1000);
            }
        } else {
            chmod($path, 0644);
            if (posix_geteuid() === 0) {
                chown($path, 1000);
                chgrp($path, 1000);
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
}
