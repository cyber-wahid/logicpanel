<?php

declare(strict_types=1);

namespace LogicDock\Application\Middleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use LogicDock\Application\Services\JwtService;
use LogicDock\Application\Services\TokenBlacklistService;
use LogicDock\Application\Services\LoggingService;
use LogicDock\Domain\User\User;
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
                    $apiKey = \LogicDock\Domain\User\ApiKey::where('key_hash', $apiKeyHeader)->first();
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
}
