<?php

declare(strict_types=1);

namespace LogicPanel\Application\Middleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use LogicPanel\Application\Services\JwtService;
use LogicPanel\Application\Services\TokenBlacklistService;
use LogicPanel\Application\Services\LoggingService;
use LogicPanel\Domain\User\User;
use Slim\Psr7\Response;

class AuthMiddleware implements MiddlewareInterface
{
    private JwtService $jwtService;
    private TokenBlacklistService $blacklistService;
    private ?LoggingService $loggingService;

    public function __construct(
        JwtService $jwtService, 
        TokenBlacklistService $blacklistService,
        ?LoggingService $loggingService = null
    ) {
        $this->jwtService = $jwtService;
        $this->blacklistService = $blacklistService;
        $this->loggingService = $loggingService;
    }

    public function process(
        ServerRequestInterface $request,
        RequestHandlerInterface $handler
    ): ResponseInterface {
        // IP-based Rate Limiting for Auth Endpoints
        if (!$this->isRateSafe($request)) {
            $response = new Response();
            $response->getBody()->write(json_encode([
                'error' => 'Too Many Requests',
                'message' => 'Rate limit exceeded. Please try again later.'
            ]));
            return $response->withStatus(429)->withHeader('Content-Type', 'application/json');
        }

        $authHeader = $request->getHeaderLine('Authorization');
        $token = null;

        // Try Authorization header first
        if (!empty($authHeader) && preg_match('/Bearer\s+(.*)$/i', $authHeader, $matches)) {
            $token = $matches[1];
        }

        // Allow query parameter token ONLY for download routes (browser can't add headers to URL navigation)
        if (empty($token)) {
            $path = $request->getUri()->getPath();
            $queryParams = $request->getQueryParams();
            if (
                isset($queryParams['token']) && (
                    strpos($path, '/download') !== false ||
                    strpos($path, '/backups/download') !== false
                )
            ) {
                $token = $queryParams['token'];
            }
        }

        // --- NEW: API KEY SUPPORT ---
        // If no JWT, try API Key from header
        if (empty($token)) {
            $apiKeyHeader = $request->getHeaderLine('X-API-Key');
            if (!empty($apiKeyHeader)) {
                try {
                    $apiKey = \LogicPanel\Domain\User\ApiKey::where('key_hash', $apiKeyHeader)->first();
                    if ($apiKey) {
                        // Update Last Used
                        $apiKey->last_used_at = date('Y-m-d H:i:s');
                        $apiKey->save();

                        $user = $apiKey->user;
                        if ($user && $user->isActive()) {
                            if ($this->loggingService) {
                                $this->loggingService->info("Successful API key authentication", [
                                    'user_id' => $user->id,
                                    'api_key_id' => $apiKey->id,
                                    'ip' => $this->getClientIP($request)
                                ]);
                            }
                            
                            $request = $request->withAttribute('user', $user);
                            $request = $request->withAttribute('userId', $user->id);
                            $request = $request->withAttribute('auth_method', 'api_key');
                            return $handler->handle($request);
                        }
                    }
                } catch (\Exception $e) {
                    error_log("API Key auth error: " . $e->getMessage());
                }
                
                // Invalid API key
                if ($this->loggingService) {
                    $this->loggingService->warning("Invalid API key attempted", [
                        'api_key_header' => $apiKeyHeader,
                        'ip' => $this->getClientIP($request),
                        'uri' => $request->getUri()->getPath()
                    ]);
                }
                
                return $this->unauthorized('Invalid API Key');
            }
        }

        if (empty($token)) {
            if ($this->loggingService) {
                $this->loggingService->info("Missing authorization header", [
                    'ip' => $this->getClientIP($request),
                    'uri' => $request->getUri()->getPath()
                ]);
            }
            
            return $this->unauthorized('Missing authorization header');
        }

        // Check if token is blacklisted
        if ($this->blacklistService->isBlacklisted($token)) {
            if ($this->loggingService) {
                $this->loggingService->warning("Blacklisted token attempted", [
                    'token_hash' => hash('sha256', $token),
                    'ip' => $this->getClientIP($request),
                    'uri' => $request->getUri()->getPath()
                ]);
            }
            
            return $this->unauthorized('Token has been revoked');
        }

        try {
            $decoded = $this->jwtService->verifyToken($token);
        } catch (\Exception $e) {
            if ($this->loggingService) {
                $this->loggingService->error("JWT verification exception", [
                    'error' => $e->getMessage(),
                    'token_hash' => hash('sha256', $token),
                    'ip' => $this->getClientIP($request),
                    'uri' => $request->getUri()->getPath()
                ]);
            }
            
            return $this->unauthorized('Invalid token: ' . $e->getMessage());
        }

        if (!$decoded) {
            if ($this->loggingService) {
                $this->loggingService->warning("Invalid or expired JWT token", [
                    'token_hash' => hash('sha256', $token),
                    'ip' => $this->getClientIP($request),
                    'uri' => $request->getUri()->getPath()
                ]);
            }
            
            return $this->unauthorized('Invalid or expired token');
        }

        // Load user
        try {
            $user = User::find($decoded->sub);
        } catch (\Exception $e) {
            if ($this->loggingService) {
                $this->loggingService->error("User lookup failed", [
                    'user_id' => $decoded->sub ?? 'unknown',
                    'token_hash' => hash('sha256', $token),
                    'error' => $e->getMessage(),
                    'ip' => $this->getClientIP($request),
                    'uri' => $request->getUri()->getPath()
                ]);
            }
            
            return $this->unauthorized('User lookup failed');
        }

        if (!$user || !$user->isActive()) {
            if ($this->loggingService) {
                $this->loggingService->warning("Inactive or non-existent user attempted access", [
                    'user_id' => $decoded->sub ?? 'unknown',
                    'user_status' => $user ? $user->status : 'not_found',
                    'token_hash' => hash('sha256', $token),
                    'ip' => $this->getClientIP($request),
                    'uri' => $request->getUri()->getPath()
                ]);
            }
            
            return $this->unauthorized('User not found or inactive');
        }

        // Successful authentication
        if ($this->loggingService) {
            $this->loggingService->info("Successful authentication", [
                'user_id' => $user->id,
                'username' => $user->username,
                'ip' => $this->getClientIP($request),
                'uri' => $request->getUri()->getPath()
            ]);
        }

        // Add user to request attributes
        $request = $request->withAttribute('user', $user);
        $request = $request->withAttribute('userId', $user->id);
        $request = $request->withAttribute('token_decoded', $decoded);
        $request = $request->withAttribute('token_string', $token);

        return $handler->handle($request);
    }

    private function getClientIP(ServerRequestInterface $request): string
    {
        $ip = $request->getServerParams()['REMOTE_ADDR'] ?? 'unknown';
        
        // Check for forwarded IP headers (proxy/load balancer)
        $forwarded = $request->getHeaderLine('X-Forwarded-For');
        if (!empty($forwarded)) {
            $ips = explode(',', $forwarded);
            $ip = trim($ips[0]);
        }
        
        return $ip;
    }

    private function unauthorized(string $message): ResponseInterface
    {
        $response = new Response();
        $response->getBody()->write(json_encode([
            'error' => 'Unauthorized',
            'message' => $message,
        ]));

        return $response
            ->withStatus(401)
            ->withHeader('Content-Type', 'application/json');
    }

    private function isRateSafe(ServerRequestInterface $request): bool
    {
        $path = $request->getUri()->getPath();
        // Only rate limit Auth endpoints
        if (strpos($path, '/login') === false && strpos($path, '/register') === false) {
            return true;
        }

        $ip = $this->getClientIP($request);
        $cacheDir = sys_get_temp_dir() . '/logicpanel_rate_limit';
        
        if (!is_dir($cacheDir)) {
            @mkdir($cacheDir, 0777, true);
        }

        $file = $cacheDir . '/' . md5($ip . '_rate');
        $now = time();
        $limit = 10; // Max 10 attempts
        $window = 60; // Per 60 seconds

        $data = ['count' => 0, 'start' => $now];
        if (file_exists($file)) {
            $content = file_get_contents($file);
            $decoded = json_decode($content, true);
            if ($decoded) {
                $data = $decoded;
            }
        }

        if ($now - $data['start'] > $window) {
            $data = ['count' => 1, 'start' => $now];
        } else {
            $data['count']++;
        }

        @file_put_contents($file, json_encode($data));

        return $data['count'] <= $limit;
    }
}
