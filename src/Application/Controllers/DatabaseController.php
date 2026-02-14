<?php

declare(strict_types=1);

namespace LogicPanel\Application\Controllers;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use LogicPanel\Domain\Database\Database;
use LogicPanel\Domain\Service\Service;
use LogicPanel\Infrastructure\Database\DatabaseProvisionerService;

class DatabaseController
{
    private DatabaseProvisionerService $provisionerService;

    public function __construct(DatabaseProvisionerService $provisionerService)
    {
        $this->provisionerService = $provisionerService;
    }

    public function index(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $userId = $request->getAttribute('userId');

        $query = Database::where('user_id', $userId);

        // Filter by service if provided
        if (isset($args['serviceId'])) {
            $serviceId = (int) $args['serviceId'];
            $query->where('service_id', $serviceId);
        }

        $queryParams = $request->getQueryParams();
        $page = (int) ($queryParams['page'] ?? 1);
        $perPage = (int) ($queryParams['per_page'] ?? 15);
        if ($perPage > 100)
            $perPage = 100;

        $total = $query->count();
        $databases = $query->skip(($page - 1) * $perPage)
            ->take($perPage)
            ->get();

        return $this->jsonResponse($response, [
            'databases' => $databases->map(function ($db) {
                return [
                    'id' => $db->id,
                    'service_id' => $db->service_id,
                    'type' => $db->db_type,
                    'name' => $db->db_name,
                    'user' => $db->db_user,
                    'password' => $this->decryptPassword($db->db_password),
                    'host' => $db->db_host,
                    'port' => $db->db_port,
                    'created_at' => $db->created_at->toIso8601String(),
                ];
            }),
            'pagination' => [
                'total' => $total,
                'current_page' => $page,
                'per_page' => $perPage,
                'total_pages' => ceil($total / $perPage)
            ]
        ]);
    }

    public function create(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $userId = $request->getAttribute('userId');
        $serviceId = isset($args['serviceId']) ? (int) $args['serviceId'] : null;
        $data = $request->getParsedBody();

        $dbType = $data['type'] ?? 'mysql';

        if (!in_array($dbType, ['mysql', 'postgresql', 'mongodb'])) {
            return $this->jsonResponse($response, ['error' => 'Invalid database type'], 400);
        }

        // Verify service ownership if serviceId is provided
        if ($serviceId) {
            $service = Service::where('id', $serviceId)
                ->where('user_id', $userId)
                ->first();

            if (!$service) {
                return $this->jsonResponse($response, ['error' => 'Service not found'], 404);
            }
        }

        try {
            // Create database record first to get ID
            $database = new Database();
            $database->service_id = $serviceId; // Can be null
            $database->user_id = $userId;
            $database->db_type = $dbType;
            // Set temporary placeholders to satisfy NOT NULL constraints
            $database->db_name = 'pending_' . uniqid();
            $database->db_user = 'pending';
            $database->db_password = ''; // Placeholder
            $database->db_host = 'localhost';
            $database->db_port = 3306; // Default port constraint
            $database->save();

            // Call provisioner service
            $provisionerResponse = match ($dbType) {
                'mysql' => $this->provisionerService->createMySQLDatabase($userId, $database->id),
                'postgresql' => $this->provisionerService->createPostgreSQLDatabase($userId, $database->id),
                'mongodb' => $this->provisionerService->createMongoDBDatabase($userId, $database->id),
            };

            if (!isset($provisionerResponse['database'])) {
                throw new \RuntimeException('Invalid response from provisioner');
            }

            $dbInfo = $provisionerResponse['database'];

            // Update database record with credentials
            $database->db_name = $dbInfo['name'];
            $database->db_user = $dbInfo['user'];
            $database->db_password = $this->encryptPassword($dbInfo['password']);
            $database->db_host = $dbInfo['host'];
            $database->db_port = $dbInfo['port'];
            $database->save();

            return $this->jsonResponse($response, [
                'message' => 'Database created successfully',
                'database' => [
                    'id' => $database->id,
                    'type' => $database->db_type,
                    'name' => $database->db_name,
                    'user' => $database->db_user,
                    'password' => $dbInfo['password'], // Return plain password only on creation
                    'host' => $database->db_host,
                    'port' => $database->db_port,
                    'connection_string' => $database->getConnectionString(),
                ],
            ], 201);

        } catch (\Exception $e) {
            // Cleanup on error
            if (isset($database) && $database->id) {
                $database->delete();
            }

            return $this->jsonResponse($response, [
                'error' => 'Failed to create database',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    public function show(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $userId = $request->getAttribute('userId');
        $databaseId = (int) $args['id'];

        $database = Database::where('id', $databaseId)
            ->where('user_id', $userId)
            ->first();

        if (!$database) {
            return $this->jsonResponse($response, ['error' => 'Database not found'], 404);
        }

        return $this->jsonResponse($response, [
            'database' => [
                'id' => $database->id,
                'type' => $database->db_type,
                'name' => $database->db_name,
                'user' => $database->db_user,
                'host' => $database->db_host,
                'port' => $database->db_port,
                'connection_string' => $database->getConnectionString(),
                'created_at' => $database->created_at->toIso8601String(),
            ],
        ]);
    }

    public function delete(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $userId = $request->getAttribute('userId');
        $databaseId = (int) $args['id'];

        $database = Database::where('id', $databaseId)
            ->where('user_id', $userId)
            ->first();

        if (!$database) {
            return $this->jsonResponse($response, ['error' => 'Database not found'], 404);
        }

        try {
            // Call provisioner to delete database
            match ($database->db_type) {
                'mysql' => $this->provisionerService->deleteMySQLDatabase($userId, $database->id),
                'postgresql' => $this->provisionerService->deletePostgreSQLDatabase($userId, $database->id),
                'mongodb' => $this->provisionerService->deleteMongoDBDatabase($userId, $database->id),
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

    /**
     * Encrypt password using libsodium
     * @throws \RuntimeException if encryption key is missing or invalid
     */
    private function encryptPassword(string $password): string
    {
        $key = $this->getEncryptionKey();

        // Generate random nonce
        $nonce = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);

        // Encrypt the password
        $ciphertext = sodium_crypto_secretbox($password, $nonce, $key);

        // Clear the key from memory
        sodium_memzero($key);

        // Return nonce + ciphertext, base64 encoded for storage
        return base64_encode($nonce . $ciphertext);
    }

    /**
     * Decrypt password using libsodium
     * @throws \RuntimeException if decryption fails
     */
    private function decryptPassword(string $encrypted): string
    {
        $key = $this->getEncryptionKey();
        $decoded = base64_decode($encrypted);

        if ($decoded === false || strlen($decoded) < SODIUM_CRYPTO_SECRETBOX_NONCEBYTES) {
            // Fallback for legacy base64-only passwords
            sodium_memzero($key);
            $legacy = base64_decode($encrypted);
            return $legacy !== false ? $legacy : $encrypted;
        }

        // Extract nonce and ciphertext
        $nonce = substr($decoded, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        $ciphertext = substr($decoded, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);

        // Decrypt
        $plaintext = sodium_crypto_secretbox_open($ciphertext, $nonce, $key);

        // Clear the key from memory
        sodium_memzero($key);

        if ($plaintext === false) {
            // Fallback for legacy base64-only passwords
            $legacy = base64_decode($encrypted);
            return $legacy !== false ? $legacy : $encrypted;
        }

        return $plaintext;
    }

    /**
     * Get the encryption key from environment
     * @throws \RuntimeException if key is missing or invalid
     */
    private function getEncryptionKey(): string
    {
        $keyBase64 = $_ENV['ENCRYPTION_KEY'] ?? getenv('ENCRYPTION_KEY');

        if (empty($keyBase64)) {
            throw new \RuntimeException('ENCRYPTION_KEY environment variable not set');
        }

        $key = base64_decode($keyBase64);

        if ($key === false || strlen($key) !== SODIUM_CRYPTO_SECRETBOX_KEYBYTES) {
            throw new \RuntimeException('Invalid ENCRYPTION_KEY: must be ' . SODIUM_CRYPTO_SECRETBOX_KEYBYTES . ' bytes, base64 encoded');
        }

        return $key;
    }

    private function jsonResponse(ResponseInterface $response, array $data, int $status = 200): ResponseInterface
    {
        $response->getBody()->write(json_encode($data));
        return $response
            ->withStatus($status)
            ->withHeader('Content-Type', 'application/json');
    }
}
