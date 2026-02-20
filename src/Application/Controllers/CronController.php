<?php

declare(strict_types=1);

namespace LogicPanel\Application\Controllers;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use LogicPanel\Domain\Cron\CronJob;
use LogicPanel\Domain\Service\Service;
use LogicPanel\Infrastructure\Docker\DockerService;

class CronController
{
    private DockerService $dockerService;

    public function __construct(DockerService $dockerService)
    {
        $this->dockerService = $dockerService;
    }

    public function index(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $userId = $request->getAttribute('userId');

        // Get all services for this user to filter CronJobs
        $serviceIds = Service::where('user_id', $userId)->pluck('id');

        $jobs = CronJob::whereIn('service_id', $serviceIds)
            ->with('service')
            ->orderBy('id', 'desc')
            ->get();

        return $this->jsonResponse($response, ['jobs' => $jobs]);
    }

    public function create(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $userId = $request->getAttribute('userId');
        $data = $request->getParsedBody();

        $serviceId = $data['service_id'] ?? null;
        $schedule = $data['schedule'] ?? null;
        $command = $data['command'] ?? null;

        if (!$serviceId || !$schedule || !$command) {
            return $this->jsonResponse($response, ['error' => 'Missing required fields'], 400);
        }

        // Validate Service ownership
        $service = Service::where('id', $serviceId)->where('user_id', $userId)->first();
        if (!$service) {
            return $this->jsonResponse($response, ['error' => 'Service not found or access denied'], 403);
        }

        // Validate Cron Expression (Basic check)
        // A robust regex or library is better, but basic count for now
        $parts = explode(' ', trim($schedule));
        if (count($parts) < 5) {
            return $this->jsonResponse($response, ['error' => 'Invalid cron schedule format'], 400);
        }

        $job = CronJob::create([
            'service_id' => $serviceId,
            'schedule' => $schedule,
            'command' => $command,
            'is_active' => true
        ]);

        return $this->jsonResponse($response, ['job' => $job], 201);
    }

    public function delete(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $userId = $request->getAttribute('userId');
        $id = $args['id'];

        $job = CronJob::find($id);
        if (!$job) {
            return $this->jsonResponse($response, ['error' => 'Job not found'], 404);
        }

        // Check ownership via Service
        $service = Service::where('id', $job->service_id)->where('user_id', $userId)->first();
        if (!$service) {
            return $this->jsonResponse($response, ['error' => 'Access denied'], 403);
        }

        $job->delete();

        return $this->jsonResponse($response, ['success' => true]);
    }

    public function toggle(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $userId = $request->getAttribute('userId');
        $id = $args['id'];
        $data = $request->getParsedBody();

        $job = CronJob::find($id);
        if (!$job) {
            return $this->jsonResponse($response, ['error' => 'Job not found'], 404);
        }

        $service = Service::where('id', $job->service_id)->where('user_id', $userId)->first();
        if (!$service) {
            return $this->jsonResponse($response, ['error' => 'Access denied'], 403);
        }

        $job->is_active = (bool) ($data['active'] ?? true);
        $job->save();

        return $this->jsonResponse($response, ['job' => $job]);
    }

    public function run(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $userId = $request->getAttribute('userId');
        $id = $args['id'];

        $job = CronJob::find($id);
        if (!$job) {
            return $this->jsonResponse($response, ['error' => 'Job not found'], 404);
        }

        $service = Service::where('id', $job->service_id)->where('user_id', $userId)->first();
        if (!$service) {
            return $this->jsonResponse($response, ['error' => 'Access denied'], 403);
        }

        if (!$service->container_id) {
            return $this->jsonResponse($response, ['error' => 'Service is not running'], 400);
        }

        // Run the command manually via Docker Exec
        // We trigger it asynchronously or synchronously? 
        // Synchronously for "Run Now" feedback is best.

        try {
            // Using logic similar to DockerService::execCommand but we might need to craft it directly or expose a new method.
            // Assuming executeCommand exists or we use raw exec.
            // Let's use executeCommand from DockerService if available.

            $output = $this->dockerService->executeCommand($service->container_id, $job->command);

            $job->last_run = new \DateTime();
            $job->last_result = $output;
            $job->save();

            return $this->jsonResponse($response, ['output' => $output]);

        } catch (\Exception $e) {
            return $this->jsonResponse($response, ['error' => $e->getMessage()], 500);
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
