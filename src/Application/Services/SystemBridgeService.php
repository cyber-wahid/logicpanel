<?php

declare(strict_types=1);

namespace LogicPanel\Application\Services;

use Exception;

/**
 * SystemBridgeService
 * 
 * Provides an interface to the privileged 'logicpanel-helper' script.
 * Acts as the bridge between the Web Application (www-data) and the System (root).
 */
class SystemBridgeService
{
    private string $helperCommand;
    private bool $isEnabled = false;

    public function __construct()
    {
        // Detect Environment and set helper path
        if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
            // Windows / XAMPP Dev Mode
            $binPath = realpath(__DIR__ . '/../../../../bin/logicpanel-helper');
            if ($binPath && file_exists($binPath)) {
                $this->helperCommand = 'php "' . $binPath . '"';
                $this->isEnabled = true;
            } else {
                // Fallback or disabled
                $this->isEnabled = false;
            }
        } else {
            // Linux / Docker Mode - No sudo needed, container runs as root
            $dockerPath = '/var/www/html/bin/logicpanel-helper';
            if (file_exists($dockerPath)) {
                $this->helperCommand = "php \"{$dockerPath}\"";
                $this->isEnabled = true;
            } else {
                // Production standalone - check if we're root or need sudo
                $helperPath = '/usr/local/bin/logicpanel-helper';
                if (file_exists($helperPath)) {
                    if (getmyuid() === 0) {
                        $this->helperCommand = $helperPath;
                    } else {
                        // Only use sudo if not running as root
                        $this->helperCommand = "sudo {$helperPath}";
                    }
                    $this->isEnabled = true;
                } else {
                    $this->isEnabled = false;
                }
            }
        }
    }

    /**
     * Create a new system user
     */
    public function createUser(string $username, string $password, string $homedir = null): bool
    {
        // Strict username validation: alphanumeric and underscores only, 3-32 chars
        if (!preg_match('/^[a-zA-Z0-9_]{3,32}$/', $username)) {
            throw new Exception("Invalid username format for system creation");
        }

        if (!$homedir) {
            $homedir = "/home/{$username}";
        }

        $output = $this->runCommand('user:create', [
            $username,
            $password,
            $homedir
        ]);

        return true;
    }

    /**
     * Delete a system user
     */
    public function deleteUser(string $username): bool
    {
        try {
            $this->runCommand('user:delete', [$username]);
        } catch (Exception $e) {
            if (strpos($e->getMessage(), 'user') !== false && strpos($e->getMessage(), 'does not exist') !== false) {
                return true; // Use doesn't exist, so technically deleted
            }
            throw $e;
        }
        return true;
    }

    /**
     * Change user password
     */
    public function changePassword(string $username, string $password): bool
    {
        try {
            $this->runCommand('user:passwd', [$username, $password]);
        } catch (Exception $e) {
            // If user implies not found, we can't change pass, allow fail or create user?
            // Whmcs might expect success. But let's let it fail for pass change as it matters.
            // Exception: "does not exist"
            if (strpos($e->getMessage(), 'does not exist') !== false) {
                // Try to create it? Or just fail. Failing is safer.
            }
            throw $e;
        }
        return true;
    }

    /**
     * Lock user account
     */
    public function lockUser(string $username): bool
    {
        try {
            $this->runCommand('user:lock', [$username]);
        } catch (Exception $e) {
            if (strpos($e->getMessage(), 'does not exist') !== false) {
                return true;
            }
            throw $e;
        }
        return true;
    }

    /**
     * Unlock user account
     */
    public function unlockUser(string $username): bool
    {
        try {
            $this->runCommand('user:unlock', [$username]);
        } catch (Exception $e) {
            if (strpos($e->getMessage(), 'does not exist') !== false) {
                return true;
            }
            throw $e;
        }
        return true;
    }

    /**
     * Suspend User (Alias for key lock)
     */
    public function suspendUser(string $username): bool
    {
        return $this->lockUser($username);
    }

    /**
     * Unsuspend User (Alias for key unlock)
     */
    public function unsuspendUser(string $username): bool
    {
        return $this->unlockUser($username);
    }

    /**
     * Restart a system service
     */
    public function restartService(string $serviceName): bool
    {
        // Strict service name validation
        if (!preg_match('/^[a-zA-Z0-9._-]{2,64}$/', $serviceName)) {
            throw new Exception("Invalid service name format");
        }

        $this->runCommand('service:restart', [$serviceName]);
        return true;
    }

    /**
     * Get Service Status
     */
    public function getServiceStatus(string $serviceName): string
    {
        $output = $this->runCommand('service:status', [$serviceName]);
        return trim(implode("\n", $output));
    }

    /**
     * Get System Stats (CPU, Load, Disk)
     */
    public function getSystemStats(): array
    {
        $output = $this->runCommand('system:stats', []);

        $stats = [
            'cpu_load' => [0, 0, 0],
            'memory_used' => 0,
            'memory_total' => 0,
            'disk_used' => 0,
            'disk_total' => 0
        ];

        foreach ($output as $line) {
            if (strpos($line, 'CPU_LOAD:') === 0) {
                $parts = explode(':', $line);
                $stats['cpu_load'] = explode(',', $parts[1]);
            }
            if (strpos($line, 'MEMORY:') === 0) {
                $parts = explode(':', $line);
                $mem = explode(' ', $parts[1]);
                $stats['memory_used'] = $mem[0] ?? 0;
                $stats['memory_total'] = $mem[1] ?? 0;
            }
        }

        return $stats;
    }

    /**
     * Execute the helper command
     */
    private function runCommand(string $action, array $args): array
    {
        if (!$this->isEnabled) {
            // If helper is not enabled/found, we simulate success for user management
            // to avoid breaking the panel in pure Docker environments.
            return [];
        }

        // Escape all arguments
        $escapedArgs = array_map('escapeshellarg', $args);
        $argsStr = implode(' ', $escapedArgs);

        $fullCommand = "{$this->helperCommand} {$action} {$argsStr}";

        // Log sensitive commands? (Be careful with passwords)
        // error_log("Running: " . preg_replace('/(pass\S+)\s+\S+/', '$1 *****', $fullCommand));

        exec($fullCommand . ' 2>&1', $output, $returnVar);

        if ($returnVar !== 0) {
            $errorMsg = implode("\n", $output);
            throw new Exception("System Operation Failed: {$errorMsg}");
        }

        return $output;
    }
}
