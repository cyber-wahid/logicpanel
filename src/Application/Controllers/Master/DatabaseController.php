<?php

declare(strict_types=1);

namespace LogicPanel\Application\Controllers\Master;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use LogicPanel\Domain\Database\Database;

use LogicPanel\Infrastructure\Database\DatabaseProvisionerService;

class DatabaseController
{
    private DatabaseProvisionerService $provisionerService;

    public function __construct(DatabaseProvisionerService $provisionerService)
    {
        $this->provisionerService = $provisionerService;
    }

    // List all databases across all users
    public function index(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $params = $request->getQueryParams();
        $search = $params['q'] ?? '';

        $query = Database::with(['user', 'user.owner', 'service']);

        $currentUser = $request->getAttribute('user');
        if ($currentUser && $currentUser->role === 'reseller') {
            $query->whereHas('user', function ($q) use ($currentUser) {
                $q->where('owner_id', $currentUser->id);
            });
        }

        if (!empty($search)) {
            $query->where(function ($q) use ($search) {
                $q->where('db_name', 'LIKE', "%{$search}%")
                    ->orWhere('db_user', 'LIKE', "%{$search}%")
                    ->orWhereHas('user', function ($u) use ($search) {
                        $u->where('username', 'LIKE', "%{$search}%");
                    });
            });
        }

        $databases = $query->get();

        $data = $databases->map(function ($db) {
            $containerId = match ($db->db_type) {
                'mysql' => $_ENV['MYSQL_CONTAINER'] ?? 'lp-mysql-mother',
                'postgresql' => $_ENV['POSTGRES_CONTAINER'] ?? 'lp-postgres-mother',
                'mongodb' => $_ENV['MONGO_CONTAINER'] ?? 'lp-mongo-mother',
                default => null,
            };

            $username = $db->user ? $db->user->username : 'System';

            // Fix for confused "admin" username if DB belongs to a user pattern
            if ($username === 'admin' && preg_match('/^user_(\d+)_/', $db->db_name, $matches)) {
                $username = 'user_' . $matches[1];
            }

            $ownerInfo = null;
            if ($db->user && $db->user->owner_id && $db->user->owner) {
                $ownerInfo = [
                    'id' => $db->user->owner->id,
                    'username' => $db->user->owner->username,
                    'role' => $db->user->owner->role
                ];
            }

            return [
                'id' => $db->id,
                'name' => $db->db_name,
                'type' => $db->db_type,
                'user' => $username,
                'user_owner' => $ownerInfo,
                'container_id' => $containerId,
                'created_at' => $db->created_at ? $db->created_at->toIso8601String() : null,
            ];
        });

        return $this->jsonResponse($response, ['databases' => $data]);
    }

    public function delete(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $databaseId = (int) $args['id'];

        // Find database (no user check needed for Master admin)
        $database = Database::find($databaseId);

        if (!$database) {
            return $this->jsonResponse($response, ['error' => 'Database not found'], 404);
        }

        try {
            // Call provisioner to delete database
            match ($database->db_type) {
                'mysql' => $this->provisionerService->deleteMySQLDatabase($database->user_id, $database->id, $database->db_name, $database->db_user),
                'postgresql' => $this->provisionerService->deletePostgreSQLDatabase($database->user_id, $database->id, $database->db_name, $database->db_user),
                'mongodb' => $this->provisionerService->deleteMongoDBDatabase($database->user_id, $database->id, $database->db_name, $database->db_user),
            };

            // Delete database record
            $database->delete();

            return $this->jsonResponse($response, ['message' => 'Database deleted successfully']);

        } catch (\Exception $e) {
            return $this->jsonResponse($response, [
                'error' => 'Failed to delete database',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    // API: List databases for a specific account (WHMCS/Blesta)
    public function listForAccount(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $accountId = (int) ($args['accountId'] ?? 0);
        $user = \LogicPanel\Domain\User\User::find($accountId);

        if (!$user) {
            return $this->jsonResponse($response, ['error' => 'Account not found'], 404);
        }

        $databases = Database::where('user_id', $accountId)->get();

        $data = $databases->map(function ($db) {
            return [
                'id' => $db->id,
                'name' => $db->db_name,
                'type' => $db->db_type,
                'user' => $db->db_user,
                'host' => $db->db_host,
                'port' => $db->db_port,
                'status' => $db->status,
                'created_at' => $db->created_at ? $db->created_at->toIso8601String() : null,
            ];
        });

        return $this->jsonResponse($response, ['databases' => $data]);
    }

    // API: Create a database for a specific account (WHMCS/Blesta)
    public function createForAccount(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $accountId = (int) ($args['accountId'] ?? 0);
        $user = \LogicPanel\Domain\User\User::find($accountId);

        if (!$user) {
            return $this->jsonResponse($response, ['error' => 'Account not found'], 404);
        }

        $data = $request->getParsedBody();
        $dbType = $data['db_type'] ?? 'mysql';
        $dbName = $data['db_name'] ?? '';

        if (empty($dbName)) {
            // Auto-generate name if not provided
            $dbName = 'user_' . $accountId . '_' . substr(md5((string) time()), 0, 6);
        }

        try {
            // Create via provisioner service
            $result = match ($dbType) {
                'mysql' => $this->provisionerService->createMySQLDatabase($accountId, $dbName),
                'postgresql' => $this->provisionerService->createPostgreSQLDatabase($accountId, $dbName),
                'mongodb' => $this->provisionerService->createMongoDBDatabase($accountId, $dbName),
                default => throw new \InvalidArgumentException("Unsupported database type: $dbType"),
            };

            return $this->jsonResponse($response, [
                'result' => 'success',
                'message' => 'Database created successfully',
                'database' => $result
            ], 201);
        } catch (\Exception $e) {
            return $this->jsonResponse($response, ['error' => 'Failed to create database: ' . $e->getMessage()], 500);
        }
    }

    private function jsonResponse(ResponseInterface $response, array $data, int $status = 200): ResponseInterface
    {
        $response->getBody()->write(json_encode($data));
        return $response
            ->withStatus($status)
            ->withHeader('Content-Type', 'application/json');
    }
}
