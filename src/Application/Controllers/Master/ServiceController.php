<?php

declare(strict_types=1);

namespace LogicPanel\Application\Controllers\Master;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use LogicPanel\Domain\Service\Service;

class ServiceController
{
    private $systemBridge;
    private $dockerService;

    public function __construct(
        \LogicPanel\Application\Services\SystemBridgeService $systemBridge,
        \LogicPanel\Infrastructure\Docker\DockerService $dockerService
    ) {
        $this->systemBridge = $systemBridge;
        $this->dockerService = $dockerService;
    }

    // List all services (containers) across all users
    public function index(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $currentUser = $request->getAttribute('user');
        $query = Service::with(['user', 'user.owner']);

        if ($currentUser && $currentUser->role === 'reseller') {
            $query->whereHas('user', function ($q) use ($currentUser) {
                $q->where('owner_id', $currentUser->id);
            });
        }

        $services = $query->get();

        $data = $services->map(function ($svc) {
            $ownerInfo = null;
            if ($svc->user && $svc->user->owner_id && $svc->user->owner) {
                $ownerInfo = [
                    'id' => $svc->user->owner->id,
                    'username' => $svc->user->owner->username,
                    'role' => $svc->user->owner->role
                ];
            }
            
            return [
                'id' => $svc->id,
                'name' => $svc->name,
                'type' => $svc->type,
                'user' => $svc->user ? $svc->user->username : 'Unknown',
                'user_owner' => $ownerInfo, // Reseller info if user is under a reseller
                'status' => $svc->status,
                'container_id' => $svc->container_id,
                'created_at' => $svc->created_at->toIso8601String(),
            ];
        });

        return $this->jsonResponse($response, ['services' => $data]);
    }

    // Start a service
    public function start(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $id = $args['id'];
        $service = Service::find($id);

        if (!$service || empty($service->container_id)) {
            return $this->jsonResponse($response, ['error' => 'Service or container not found'], 404);
        }

        try {
            $this->dockerService->startContainer($service->container_id);
            $service->status = 'running';
            $service->save();
            return $this->jsonResponse($response, ['message' => 'Service started successfully', 'status' => 'running']);
        } catch (\Exception $e) {
            return $this->jsonResponse($response, ['error' => 'Docker Error: ' . $e->getMessage()], 500);
        }
    }

    // Stop a service
    public function stop(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $id = $args['id'];
        $service = Service::find($id);

        if (!$service || empty($service->container_id)) {
            return $this->jsonResponse($response, ['error' => 'Service or container not found'], 404);
        }

        try {
            $this->dockerService->stopContainer($service->container_id);
            $service->status = 'stopped';
            $service->save();
            return $this->jsonResponse($response, ['message' => 'Service stopped successfully', 'status' => 'stopped']);
        } catch (\Exception $e) {
            return $this->jsonResponse($response, ['error' => 'Docker Error: ' . $e->getMessage()], 500);
        }
    }

    // Delete a service
    public function delete(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $id = $args['id'];
        $service = Service::find($id);

        if (!$service) {
            return $this->jsonResponse($response, ['error' => 'Service not found'], 404);
        }

        try {
            if (!empty($service->container_id)) {
                $this->dockerService->removeContainer($service->container_id);
            }
            $service->delete();
            return $this->jsonResponse($response, ['message' => 'Service deleted successfully']);
        } catch (\Exception $e) {
            return $this->jsonResponse($response, ['error' => 'Docker Error: ' . $e->getMessage()], 500);
        }
    }

    // Restart a service
    public function restart(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $id = $args['id'];
        $service = Service::find($id);

        if (!$service || empty($service->container_id)) {
            return $this->jsonResponse($response, ['error' => 'Service or container not found'], 404);
        }

        try {
            $this->dockerService->restartContainer($service->container_id);
            $service->status = 'running';
            $service->save();
            return $this->jsonResponse($response, ['message' => 'Service restarted successfully', 'status' => 'running']);
        } catch (\Exception $e) {
            return $this->jsonResponse($response, ['error' => 'Docker Error: ' . $e->getMessage()], 500);
        }
    }

    // Bulk Actions
    public function bulkAction(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $data = $request->getParsedBody();
        $ids = $data['ids'] ?? [];
        $action = $data['action'] ?? '';

        if (empty($ids) || !is_array($ids)) {
            return $this->jsonResponse($response, ['error' => 'No services selected'], 400);
        }

        $results = ['success' => 0, 'failed' => 0, 'errors' => []];

        foreach ($ids as $id) {
            $service = Service::find($id);
            if (!$service || empty($service->container_id)) {
                $results['failed']++;
                continue;
            }

            try {
                switch ($action) {
                    case 'start':
                        $this->dockerService->startContainer($service->container_id);
                        $service->status = 'running';
                        $service->save();
                        break;
                    case 'stop':
                        $this->dockerService->stopContainer($service->container_id);
                        $service->status = 'stopped';
                        $service->save();
                        break;
                    case 'restart':
                        $this->dockerService->restartContainer($service->container_id);
                        $service->status = 'running';
                        $service->save();
                        break;
                    case 'delete':
                        $this->dockerService->removeContainer($service->container_id);
                        $service->delete();
                        break;
                    default:
                        $results['failed']++;
                        continue 2;
                }
                $results['success']++;
            } catch (\Exception $e) {
                $results['failed']++;
                $results['errors'][] = "ID $id: " . $e->getMessage();
            }
        }

        return $this->jsonResponse($response, ['message' => "Bulk action '$action' completed", 'results' => $results]);
    }

    // API: List services for a specific account (WHMCS/Blesta)
    public function listForAccount(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $accountId = (int) ($args['accountId'] ?? 0);
        $user = \LogicPanel\Domain\User\User::find($accountId);

        if (!$user) {
            return $this->jsonResponse($response, ['error' => 'Account not found'], 404);
        }

        $services = Service::where('user_id', $accountId)->get();

        $data = $services->map(function ($svc) {
            return [
                'id' => $svc->id,
                'name' => $svc->name,
                'type' => $svc->type,
                'status' => $svc->status,
                'container_id' => $svc->container_id,
                'domain' => $svc->domain,
                'created_at' => $svc->created_at ? $svc->created_at->toIso8601String() : null,
            ];
        });

        return $this->jsonResponse($response, ['services' => $data]);
    }

    // API: Create a service for a specific account (WHMCS/Blesta)
    public function createForAccount(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $accountId = (int) ($args['accountId'] ?? 0);
        $user = \LogicPanel\Domain\User\User::find($accountId);

        if (!$user) {
            return $this->jsonResponse($response, ['error' => 'Account not found'], 404);
        }

        $data = $request->getParsedBody();
        $name = $data['name'] ?? '';
        $type = $data['type'] ?? 'nodejs';

        if (empty($name)) {
            return $this->jsonResponse($response, ['error' => 'Service name is required'], 400);
        }

        try {
            $service = new Service();
            $service->user_id = $accountId;
            $service->name = $name;
            $service->type = $type;
            $service->domain = $data['domain'] ?? null;
            $service->status = 'creating';
            $service->node_version = $data['node_version'] ?? '20';
            $service->python_version = $data['python_version'] ?? '3.11';
            $service->install_command = $data['install_command'] ?? 'npm install';
            $service->build_command = $data['build_command'] ?? '';
            $service->start_command = $data['start_command'] ?? 'npm start';
            $service->cpu_limit = $data['cpu_limit'] ?? 0.50;
            $service->memory_limit = $data['memory_limit'] ?? '512M';
            $service->disk_limit = $data['disk_limit'] ?? '1G';
            $service->save();

            return $this->jsonResponse($response, [
                'result' => 'success',
                'message' => 'Service created successfully',
                'service' => [
                    'id' => $service->id,
                    'name' => $service->name,
                    'type' => $service->type,
                    'status' => $service->status,
                ]
            ], 201);
        } catch (\Exception $e) {
            return $this->jsonResponse($response, ['error' => 'Failed to create service: ' . $e->getMessage()], 500);
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
