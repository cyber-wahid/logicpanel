<?php

declare(strict_types=1);

namespace LogicPanel\Infrastructure\Database;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;

class DatabaseProvisionerService
{
    private Client $client;
    private string $secret;

    public function __construct(array $config)
    {
        $this->client = new Client([
            'base_uri' => $config['url'],
            'timeout' => 30,
        ]);
        $this->secret = $config['secret'];
    }

    public function createMySQLDatabase(int $userId, int $dbId): array
    {
        return $this->request('POST', '/internal/db/mysql/create', [
            'userId' => $userId,
            'dbId' => $dbId,
        ]);
    }

    public function createPostgreSQLDatabase(int $userId, int $dbId): array
    {
        return $this->request('POST', '/internal/db/postgresql/create', [
            'userId' => $userId,
            'dbId' => $dbId,
        ]);
    }

    public function createMongoDBDatabase(int $userId, int $dbId): array
    {
        return $this->request('POST', '/internal/db/mongodb/create', [
            'userId' => $userId,
            'dbId' => $dbId,
        ]);
    }

    public function deleteMySQLDatabase(int $userId, int $dbId): void
    {
        $this->request('DELETE', "/internal/db/mysql/{$userId}/{$dbId}");
    }

    public function deletePostgreSQLDatabase(int $userId, int $dbId): void
    {
        $this->request('DELETE', "/internal/db/postgresql/{$userId}/{$dbId}");
    }

    public function deleteMongoDBDatabase(int $userId, int $dbId): void
    {
        $this->request('DELETE', "/internal/db/mongodb/{$userId}/{$dbId}");
    }

    private function request(string $method, string $uri, array $data = []): array
    {
        try {
            $options = [
                'headers' => [
                    'Authorization' => "Bearer {$this->secret}",
                    'Content-Type' => 'application/json',
                ],
            ];

            if (!empty($data)) {
                $options['json'] = $data;
            }

            $response = $this->client->request($method, $uri, $options);
            $body = (string) $response->getBody();

            return json_decode($body, true) ?: [];

        } catch (GuzzleException $e) {
            throw new \RuntimeException("Database provisioner error: " . $e->getMessage());
        }
    }
}
