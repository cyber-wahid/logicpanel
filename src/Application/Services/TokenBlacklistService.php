<?php

declare(strict_types=1);

namespace LogicPanel\Application\Services;

use Redis;

class TokenBlacklistService
{
    private Redis $redis;
    private LoggingService $loggingService;
    private bool $connected = false;

    public function __construct(LoggingService $loggingService)
    {
        $this->loggingService = $loggingService;
        
        try {
            $this->redis = new Redis();
            // Connect to Redis container
            $this->connected = $this->redis->connect('logicpanel_redis', 6379, 1.0);
            
            if (!$this->connected) {
                $this->loggingService->error("Failed to connect to Redis for token blacklist service");
            }
        } catch (\Exception $e) {
            $this->connected = false;
            $this->loggingService->error("Exception connecting to Redis for token blacklist service", [
                'exception' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        }
    }

    /**
     * Add a token to the blacklist
     * @param string $token The JWT token string
     * @param int $expiresIn Seconds until the token would naturally expire
     */
    public function blacklist(string $token, int $expiresIn): void
    {
        if (!$this->connected || $expiresIn <= 0) {
            return;
        }

        // We store the hash of the token for privacy and performance
        $tokenHash = hash('sha256', $token);

        // Add to Redis with a TTL matching the token's remaining life
        $result = $this->redis->setex("blacklist:{$tokenHash}", $expiresIn, '1');
        
        if (!$result) {
            $this->loggingService->error("Failed to blacklist token", [
                'token_hash' => $tokenHash,
                'expires_in' => $expiresIn
            ]);
        }
    }

    /**
     * Check if a token is in the blacklist
     */
    public function isBlacklisted(string $token): bool
    {
        if (!$this->connected) {
            $this->loggingService->warning("Redis not connected, cannot check blacklist", [
                'token' => hash('sha256', $token)
            ]);
            return false;
        }

        $tokenHash = hash('sha256', $token);
        $exists = (bool) $this->redis->exists("blacklist:{$tokenHash}");
        
        if ($exists) {
            $this->loggingService->debug("Token found in blacklist", [
                'token_hash' => $tokenHash
            ]);
        }
        
        return $exists;
    }
}
