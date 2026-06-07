<?php

declare(strict_types=1);

namespace LogicPanel\Application\Services;

use Monolog\Logger;
use Monolog\Handler\StreamHandler;
use Monolog\Handler\RotatingFileHandler;
use Monolog\Formatter\LineFormatter;
use Monolog\Level;

/**
 * LoggingService - Centralized logging for LogicPanel
 *
 * Writes to:
 *   storage/logs/app/app-YYYY-MM-DD.log    → App-level events (info, warning, debug)
 *   storage/logs/api/api-YYYY-MM-DD.log    → API errors & fatal issues
 *   storage/logs/php/php-YYYY-MM-DD.log    → PHP errors (set_error_handler)
 *   storage/logs/access/access-YYYY-MM-DD.log → Request access log
 *
 * All logs ALSO mirror to stderr so `docker logs logicpanel_app` still works.
 */
class LoggingService
{
    private Logger $logger;

    /** Base log directory (inside container) */
    private static string $logBase = '/var/www/html/storage/logs';

    /** Fallback base dir when running outside Docker (dev) */
    private static string $logBaseFallback = __DIR__ . '/../../../storage/logs';

    public function __construct(
        string $channel = 'app',
        string $path = 'php://stderr',
        string $level = 'debug'
    ) {
        $this->logger = new Logger($channel);

        $monologLevel = self::resolveLevel($level);
        $logDir = self::resolveLogDir();

        // ------------------------------------------------------------------
        // Handler 1: Rotating file (daily) — keeps last 14 days
        // ------------------------------------------------------------------
        try {
            $channelDir = $logDir . '/' . $channel;
            if (!is_dir($channelDir)) {
                @mkdir($channelDir, 0775, true);
            }

            $filePath = $channelDir . '/' . $channel . '.log';
            $fileHandler = new RotatingFileHandler($filePath, 14, $monologLevel);
            $fileHandler->setFormatter(self::buildFormatter());
            $this->logger->pushHandler($fileHandler);
        } catch (\Throwable $e) {
            error_log('[LoggingService] File handler failed: ' . $e->getMessage());
        }

        // ------------------------------------------------------------------
        // Handler 2: stderr — always on so `docker logs` keeps working
        // ------------------------------------------------------------------
        try {
            $stderrHandler = new StreamHandler('php://stderr', $monologLevel);
            $stderrHandler->setFormatter(self::buildFormatter(compact: true));
            $this->logger->pushHandler($stderrHandler);
        } catch (\Throwable $e) {
            error_log('[LoggingService] stderr handler failed: ' . $e->getMessage());
        }
    }

    // ------------------------------------------------------------------
    // Public logging methods
    // ------------------------------------------------------------------

    public function log(string $level, string $message, array $context = []): void
    {
        $method = strtolower($level);
        if (method_exists($this->logger, $method)) {
            $this->logger->$method($message, $context);
        } else {
            $this->logger->info($message, $context);
        }
    }

    public function debug(string $message, array $context = []): void
    {
        $this->logger->debug($message, $context);
    }

    public function info(string $message, array $context = []): void
    {
        $this->logger->info($message, $context);
    }

    public function notice(string $message, array $context = []): void
    {
        $this->logger->notice($message, $context);
    }

    public function warning(string $message, array $context = []): void
    {
        $this->logger->warning($message, $context);
    }

    public function error(string $message, array $context = []): void
    {
        $this->logger->error($message, $context);
    }

    public function critical(string $message, array $context = []): void
    {
        $this->logger->critical($message, $context);
    }

    public function alert(string $message, array $context = []): void
    {
        $this->logger->alert($message, $context);
    }

    public function emergency(string $message, array $context = []): void
    {
        $this->logger->emergency($message, $context);
    }

    // ------------------------------------------------------------------
    // Static factory helpers — create purpose-specific loggers easily
    // ------------------------------------------------------------------

    /**
     * Logger for general application events
     */
    public static function app(string $level = 'info'): self
    {
        return new self('app', 'php://stderr', $level);
    }

    /**
     * Logger for API layer errors
     */
    public static function api(string $level = 'warning'): self
    {
        return new self('api', 'php://stderr', $level);
    }

    /**
     * Logger for PHP errors (use in set_error_handler / set_exception_handler)
     */
    public static function php(string $level = 'error'): self
    {
        return new self('php', 'php://stderr', $level);
    }

    /**
     * Logger for HTTP access/request logging
     */
    public static function access(string $level = 'info'): self
    {
        return new self('access', 'php://stderr', $level);
    }

    // ------------------------------------------------------------------
    // Log directory helpers
    // ------------------------------------------------------------------

    public static function resolveLogDir(): string
    {
        // Prefer Docker path, fallback to relative for local dev
        if (is_dir('/var/www/html/storage/logs')) {
            return '/var/www/html/storage/logs';
        }

        $fallback = realpath(self::$logBaseFallback) ?: self::$logBaseFallback;
        if (!is_dir($fallback)) {
            @mkdir($fallback, 0775, true);
        }
        return $fallback;
    }

    /**
     * Return paths of all existing log files, grouped by channel.
     * Used by the log viewer endpoint.
     */
    public static function getLogFiles(): array
    {
        $base = self::resolveLogDir();
        $channels = ['app', 'api', 'php', 'access'];
        $result = [];

        foreach ($channels as $channel) {
            $dir = $base . '/' . $channel;
            if (!is_dir($dir)) continue;

            $files = glob($dir . '/*.log') ?: [];
            rsort($files); // newest first

            foreach ($files as $file) {
                $result[$channel][] = [
                    'file'     => basename($file),
                    'path'     => $file,
                    'size'     => filesize($file),
                    'modified' => filemtime($file),
                ];
            }
        }

        return $result;
    }

    /**
     * Read last N lines from a log file (safe tail).
     */
    public static function tailLog(string $channel, string $filename, int $lines = 200): array
    {
        $base = self::resolveLogDir();

        // Security: only allow known channels & sanitize filename
        $allowed = ['app', 'api', 'php', 'access'];
        if (!in_array($channel, $allowed, true)) {
            return ['error' => 'Invalid channel'];
        }

        $filename = basename($filename); // strip any path traversal
        $path     = $base . '/' . $channel . '/' . $filename;

        if (!file_exists($path)) {
            return ['error' => 'Log file not found'];
        }

        // Read last N lines efficiently
        $fp   = fopen($path, 'r');
        $buf  = [];
        $line = '';

        // Seek from end
        fseek($fp, 0, SEEK_END);
        $pos = ftell($fp);

        while ($pos > 0 && count($buf) < $lines) {
            $pos--;
            fseek($fp, $pos);
            $char = fread($fp, 1);
            if ($char === "\n" && $line !== '') {
                $buf[] = strrev($line);
                $line  = '';
            } else {
                $line .= strrev($char);
            }
        }
        if ($line) $buf[] = strrev($line);
        fclose($fp);

        return array_reverse($buf);
    }

    // ------------------------------------------------------------------
    // Private helpers
    // ------------------------------------------------------------------

    private static function resolveLevel(string $level): Level
    {
        return match (strtolower($level)) {
            'debug'     => Level::Debug,
            'info'      => Level::Info,
            'notice'    => Level::Notice,
            'warning'   => Level::Warning,
            'error'     => Level::Error,
            'critical'  => Level::Critical,
            'alert'     => Level::Alert,
            'emergency' => Level::Emergency,
            default     => Level::Debug,
        };
    }

    private static function buildFormatter(bool $compact = false): LineFormatter
    {
        if ($compact) {
            // Shorter format for stderr / docker logs
            return new LineFormatter(
                "[%datetime%] %channel%.%level_name%: %message% %context%\n",
                'Y-m-d H:i:s',
                true,
                true
            );
        }

        // Full format for log files
        return new LineFormatter(
            "[%datetime%] %channel%.%level_name%: %message% %context% %extra%\n",
            'Y-m-d H:i:s',
            true,
            true
        );
    }
}
