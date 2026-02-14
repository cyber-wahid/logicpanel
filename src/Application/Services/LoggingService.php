<?php

declare(strict_types=1);

namespace LogicPanel\Application\Services;

use Monolog\Logger;
use Monolog\Handler\StreamHandler;
use Monolog\Handler\RotatingFileHandler;
use Monolog\Level;

class LoggingService
{
    private Logger $logger;

    public function __construct(string $channel = 'LogicPanel', string $path = 'php://stderr', string $level = 'debug')
    {
        $this->logger = new Logger($channel);

        // Map string level to Monolog Level enum (Monolog 3.x)
        $monologLevel = match (strtolower($level)) {
            'debug' => Level::Debug,
            'info' => Level::Info,
            'notice' => Level::Notice,
            'warning' => Level::Warning,
            'error' => Level::Error,
            'critical' => Level::Critical,
            'alert' => Level::Alert,
            'emergency' => Level::Emergency,
            default => Level::Debug,
        };

        try {
            if ($path === 'php://stderr' || $path === 'php://stdout') {
                $handler = new StreamHandler($path, $monologLevel);
            } else {
                // Ensure directory exists for file logging
                $logDir = dirname($path);
                if (!is_dir($logDir)) {
                    @mkdir($logDir, 0755, true);
                }
                $handler = new RotatingFileHandler($path, 0, $monologLevel);
            }

            $this->logger->pushHandler($handler);
        } catch (\Throwable $e) {
            // Fallback to stderr if handler creation fails
            error_log("Failed to initialize logger: " . $e->getMessage());
        }
    }

    public function log(string $level, string $message, array $context = []): void
    {
        // Map string level to method call
        $method = strtolower($level);
        if (method_exists($this->logger, $method)) {
            $this->logger->$method($message, $context);
        } else {
            $this->logger->log($level, $message, $context);
        }
    }

    public function info(string $message, array $context = []): void
    {
        $this->logger->info($message, $context);
    }

    public function error(string $message, array $context = []): void
    {
        $this->logger->error($message, $context);
    }

    public function warning(string $message, array $context = []): void
    {
        $this->logger->warning($message, $context);
    }

    public function debug(string $message, array $context = []): void
    {
        $this->logger->debug($message, $context);
    }
}
