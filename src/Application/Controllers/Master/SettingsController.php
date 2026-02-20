<?php

declare(strict_types=1);

namespace LogicPanel\Application\Controllers\Master;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use LogicPanel\Domain\Setting\Setting;
use Firebase\JWT\JWT;

class SettingsController
{
    private $configFile = __DIR__ . '/../../../../config/settings.json';

    public function get(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $settings = $this->loadSettings();
        return $this->jsonResponse($response, $settings);
    }

    public function update(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $data = $request->getParsedBody();
        $current = $this->loadSettings();
        
        // Get user role from request attributes (set by AuthMiddleware)
        $user = $request->getAttribute('user');
        $userRole = $user['role'] ?? 'user';

        // Define allowed keys based on role
        $allowed = [];
        
        if ($userRole === 'admin' || $userRole === 'root') {
            // Admin can edit all fields
            $allowed = [
                'company_name',
                'hostname',
                'server_ip',
                'contact_email',
                'default_language',
                'timezone',
                'ns1',
                'ns2',
                'allow_registration',
                'shared_domain',
                'enable_ssl',
                'letsencrypt_email'
            ];
        } elseif ($userRole === 'reseller') {
            // Reseller can only edit these fields
            $allowed = [
                'shared_domain',
                'ns1',
                'ns2',
                'timezone',
                'allow_registration'
            ];
            // Note: hostname and server_ip are admin-only (resellers cannot edit)
        } else {
            // Regular users cannot edit settings
            return $this->jsonResponse($response, ['error' => 'Insufficient permissions'], 403);
        }
        
        foreach ($allowed as $key) {
            if (isset($data[$key])) {
                $val = trim((string) $data[$key]);

                // Basic Validation
                if ($key === 'hostname' && !empty($val)) {
                    if (!preg_match('/^[a-zA-Z0-9.-]+$/', $val)) {
                        return $this->jsonResponse($response, ['error' => 'Invalid hostname format'], 400);
                    }
                }

                if ($key === 'server_ip' && !empty($val)) {
                    if (!filter_var($val, FILTER_VALIDATE_IP)) {
                        return $this->jsonResponse($response, ['error' => 'Invalid IP address format'], 400);
                    }
                }

                $current[$key] = $val;
            }
        }

        file_put_contents($this->configFile, json_encode($current, JSON_PRETTY_PRINT));

        return $this->jsonResponse($response, ['message' => 'Settings updated successfully', 'settings' => $current]);
    }

    private function updateEnvFile(array $updates)
    {
        $envPath = '/var/www/html/.env';
        if (!file_exists($envPath)) {
            $envPath = dirname(__DIR__, 4) . '/.env';
            if (!file_exists($envPath))
                return;
        }

        $content = file_get_contents($envPath);

        foreach ($updates as $key => $value) {
            $keyEscaped = preg_quote($key, '/');
            // Support both standard and quoted values if they exist
            $pattern = "/^({$keyEscaped}=).*$/m";

            if (preg_match($pattern, $content)) {
                $content = preg_replace($pattern, "{$key}={$value}", $content);
            } else {
                $content = rtrim($content) . "\n{$key}={$value}\n";
            }
        }

        file_put_contents($envPath, $content);
    }

    public function detectIp(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $providers = [
            "https://api.ipify.org",
            "https://ifconfig.me/ip",
            "https://ipinfo.io/ip",
            "https://checkip.amazonaws.com"
        ];

        $ip = '';
        $success = false;

        // Try external IP detection services
        foreach ($providers as $url) {
            try {
                $ch = curl_init();
                curl_setopt($ch, CURLOPT_URL, $url);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
                curl_setopt($ch, CURLOPT_TIMEOUT, 5);
                curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 3);
                curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
                curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
                $result = curl_exec($ch);
                $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                curl_close($ch);

                if ($result && $httpCode === 200 && filter_var(trim($result), FILTER_VALIDATE_IP)) {
                    $ip = trim($result);
                    $success = true;
                    break;
                }
            } catch (\Exception $e) {
                continue;
            }
        }

        // Fallback: Try to resolve hostname to IP
        if (!$success && !empty($_ENV['VIRTUAL_HOST'])) {
            $hostname = $_ENV['VIRTUAL_HOST'];
            $resolvedIp = gethostbyname($hostname);
            if ($resolvedIp !== $hostname && filter_var($resolvedIp, FILTER_VALIDATE_IP)) {
                $ip = $resolvedIp;
                $success = true;
            }
        }

        return $this->jsonResponse($response, [
            'ip' => $ip ?: '0.0.0.0',
            'success' => $success
        ]);
    }

    public function getRootTerminalToken(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        // Generate Short-lived JWT for Terminal Gateway
        $payload = [
            'iss' => 'logicpanel-backend',
            'aud' => 'logicpanel-gateway',
            'iat' => time(),
            'exp' => time() + 60,
            'sub' => 'root',
            'mode' => 'root',
            'container_id' => 'GATEWAY_LOCAL'
        ];

        $secret = $_ENV['JWT_SECRET'] ?? 'secret';
        $token = JWT::encode($payload, $secret, 'HS256');

        // Build gateway URL - uses Apache proxy at /ws/terminal path
        $settings = $this->loadSettings();
        $hostname = $settings['hostname'] ?? $_ENV['APP_DOMAIN'] ?? 'localhost';
        $masterPort = $settings['master_port'] ?? $_ENV['MASTER_PORT'] ?? '999';

        // For localhost, use ws:// with gateway port. For production, use wss:// through Apache proxy
        if (in_array($hostname, ['localhost', '127.0.0.1'])) {
            $gatewayPort = $_ENV['GATEWAY_PORT'] ?? '3002';
            $gatewayUrl = "ws://{$hostname}:{$gatewayPort}";
        } else {
            // Use /ws/terminal path through Apache proxy - no extra DNS needed!
            $gatewayUrl = "wss://{$hostname}:{$masterPort}/ws/terminal";
        }

        return $this->jsonResponse($response, [
            'token' => $token,
            'gateway_url' => $gatewayUrl
        ]);
    }

    private function loadSettings(): array
    {
        if (!file_exists($this->configFile)) {
            // Use environment variables or empty defaults - no hardcoded values
            return [
                'company_name' => '',
                'hostname' => $_ENV['VIRTUAL_HOST'] ?? '',
                'server_ip' => '', // Will be auto-detected, not hardcoded
                'master_port' => (int) ($_ENV['MASTER_PORT'] ?? 999),
                'user_port' => (int) ($_ENV['USER_PORT'] ?? 777),
                'contact_email' => '',
                'default_language' => 'en',
                'timezone' => 'UTC',
                'ns1' => '',
                'ns2' => '',
                'allow_registration' => true,
                'shared_domain' => '',
                'enable_ssl' => false,
                'letsencrypt_email' => ''
            ];
        }
        return json_decode(file_get_contents($this->configFile), true) ?? [];
    }

    private function jsonResponse(ResponseInterface $response, array $data, int $status = 200): ResponseInterface
    {
        $response->getBody()->write(json_encode($data));
        return $response
            ->withStatus($status)
            ->withHeader('Content-Type', 'application/json');
    }
}
