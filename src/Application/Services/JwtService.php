<?php

declare(strict_types=1);

namespace LogicPanel\Application\Services;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use LogicPanel\Domain\User\User;

class JwtService
{
    private string $secret;
    private int $expiry;
    private int $refreshExpiry;
    private LoggingService $loggingService;

    public function __construct(array $config, LoggingService $loggingService)
    {
        $this->secret = $config['secret'];
        $this->expiry = $config['expiry'];
        $this->refreshExpiry = $config['refresh_expiry'];
        $this->loggingService = $loggingService;
    }

    public function generateToken(User $user): string
    {
        $payload = [
            'iss' => 'logicpanel',
            'sub' => $user->id,
            'iat' => time(),
            'exp' => time() + $this->expiry,
            'user' => [
                'id' => $user->id,
                'username' => $user->username,
                'email' => $user->email,
                'role' => $user->role,
            ],
        ];

        return JWT::encode($payload, $this->secret, 'HS256');
    }

    /**
     * Generate a very short-lived token for one-click login (impersonation)
     */
    public function generateOneTimeToken(User $user): string
    {
        $payload = [
            'iss' => 'logicpanel',
            'sub' => $user->id,
            'iat' => time(),
            'exp' => time() + 60, // 60 seconds expiry
            'type' => 'impersonation',
            'jti' => bin2hex(random_bytes(16)),
            'user' => [
                'id' => $user->id,
                'username' => $user->username,
                'email' => $user->email,
                'role' => $user->role,
            ],
        ];

        return JWT::encode($payload, $this->secret, 'HS256');
    }

    public function generateRefreshToken(User $user): string
    {
        $payload = [
            'iss' => 'logicpanel',
            'sub' => $user->id,
            'iat' => time(),
            'exp' => time() + $this->refreshExpiry,
            'type' => 'refresh',
        ];

        return JWT::encode($payload, $this->secret, 'HS256');
    }

    public function verifyToken(string $token): ?object
    {
        try {
            return JWT::decode($token, new Key($this->secret, 'HS256'));
        } catch (\Exception $e) {
            // Log the error using proper logging service
            $this->loggingService->error("JWT Verification Error", [
                'error' => $e->getMessage(),
                'token' => $token,
                'timestamp' => time()
            ]);
            return null;
        }
    }

    public function getUserIdFromToken(string $token): ?int
    {
        $decoded = $this->verifyToken($token);
        return $decoded ? (int) $decoded->sub : null;
    }
}
