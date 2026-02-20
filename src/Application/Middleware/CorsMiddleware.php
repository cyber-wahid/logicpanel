<?php

declare(strict_types=1);

namespace LogicPanel\Application\Middleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

class CorsMiddleware implements MiddlewareInterface
{
    public function process(
        ServerRequestInterface $request,
        RequestHandlerInterface $handler
    ): ResponseInterface {
        $response = $handler->handle($request);

        // Get origin from request
        $origin = $request->getHeaderLine('Origin');

        // If no origin header, this is likely a same-origin request
        if (empty($origin)) {
            return $response;
        }

        // Build dynamic allowed origins based on the request host
        $host = $request->getUri()->getHost();
        $allowedOrigins = $this->getAllowedOrigins($host);

        // Check if origin is allowed
        if ($this->isOriginAllowed($origin, $allowedOrigins)) {
            return $response
                ->withHeader('Access-Control-Allow-Origin', $origin)
                ->withHeader('Access-Control-Allow-Headers', 'X-Requested-With, Content-Type, Accept, Origin, Authorization, X-API-Key')
                ->withHeader('Access-Control-Allow-Methods', 'GET, POST, PUT, PATCH, DELETE, OPTIONS')
                ->withHeader('Access-Control-Allow-Credentials', 'true')
                ->withHeader('Access-Control-Max-Age', '86400'); // Cache preflight for 24h
        }

        // Origin not allowed - don't add CORS headers
        return $response;
    }

    /**
     * Get list of allowed origins based on the panel hostname
     */
    private function getAllowedOrigins(string $host): array
    {
        // Load settings to get hostname
        $settingsFile = '/var/www/html/config/settings.json';
        $hostname = $host; // Default to current host

        if (file_exists($settingsFile)) {
            $settings = json_decode(file_get_contents($settingsFile), true);
            if (!empty($settings['hostname'])) {
                $hostname = $settings['hostname'];
            }
        }

        // Allow the panel itself on different ports
        $masterPort = $_ENV['MASTER_PORT'] ?? '999';
        $userPort = $_ENV['USER_PORT'] ?? '777';

        return [
            "https://{$hostname}",
            "https://{$hostname}:{$masterPort}",
            "https://{$hostname}:{$userPort}",
            "http://{$hostname}",
            "http://{$hostname}:{$masterPort}",
            "http://{$hostname}:{$userPort}",
            // Allow localhost for development
            "http://localhost",
            "http://localhost:{$masterPort}",
            "http://localhost:{$userPort}",
            "http://127.0.0.1",
            "http://127.0.0.1:{$masterPort}",
            "http://127.0.0.1:{$userPort}",
        ];
    }

    /**
     * Check if the origin is in the allowed list
     */
    private function isOriginAllowed(string $origin, array $allowedOrigins): bool
    {
        // Normalize origin (remove trailing slash)
        $origin = rtrim($origin, '/');

        foreach ($allowedOrigins as $allowed) {
            if (strcasecmp($origin, $allowed) === 0) {
                return true;
            }
        }

        return false;
    }
}
