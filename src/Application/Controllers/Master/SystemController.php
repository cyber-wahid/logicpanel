<?php

declare(strict_types=1);

namespace LogicPanel\Application\Controllers\Master;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use LogicPanel\Application\Services\SystemBridgeService;

class SystemController
{
    private $systemBridge;

    public function __construct(SystemBridgeService $systemBridge)
    {
        $this->systemBridge = $systemBridge;
    }

    /**
     * Get status of critical system services
     */
    public function getServicesStatus(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $services = ['nginx', 'mysql', 'docker', 'php8.1-fpm', 'cron'];
        $data = [];

        foreach ($services as $svc) {
            try {
                $status = $this->systemBridge->getServiceStatus($svc);
                $data[] = [
                    'name' => $svc,
                    'status' => $status,
                    'is_system' => true
                ];
            } catch (\Exception $e) {
                // If service doesn't exist or error
                $data[] = [
                    'name' => $svc,
                    'status' => 'unknown',
                    'error' => $e->getMessage()
                ];
            }
        }

        return $this->jsonResponse($response, ['services' => $data]);
    }

    /**
     * Restart a system service
     */
    public function restartService(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $data = $request->getParsedBody();
        $service = $data['service'] ?? '';

        if (empty($service)) {
            return $this->jsonResponse($response, ['error' => 'Service name required'], 400);
        }

        try {
            $this->systemBridge->restartService($service);
            return $this->jsonResponse($response, [
                'result' => 'success',
                'message' => "Service '$service' restarted successfully."
            ]);
        } catch (\Exception $e) {
            return $this->jsonResponse($response, ['result' => 'error', 'message' => $e->getMessage()], 500);
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
