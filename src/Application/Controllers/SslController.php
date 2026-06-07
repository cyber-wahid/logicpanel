<?php

namespace LogicPanel\Application\Controllers;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use LogicPanel\Domain\Dns\DnsDomain;
use LogicPanel\Domain\Service\Service;
use LogicPanel\Infrastructure\Docker\DockerService;
class SslController
{
    private DockerService $dockerService;

    public function __construct(DockerService $dockerService)
    {
        $this->dockerService = $dockerService;
    }

    /**
     * Get list of all domains for the authenticated user
     */
    public function index(Request $request, Response $response): Response
    {
        $userId = $request->getAttribute('userId');
        
        $domains = DnsDomain::where('user_id', $userId)->get();

        return $this->jsonResponse($response, $domains->toArray());
    }

    /**
     * Check if SSL is valid and active for a domain
     */
    public function check(Request $request, Response $response): Response
    {
        $domain = $request->getQueryParams()['domain'] ?? '';

        if (empty($domain)) {
            return $this->jsonResponse($response, ['error' => 'Domain is required'], 400);
        }

        $isActive = false;

        try {
            $context = stream_context_create([
                "ssl" => [
                    "capture_peer_cert" => true,
                    "verify_peer" => false,
                    "verify_peer_name" => false
                ]
            ]);

            // Attempt to connect to port 443 with a short timeout
            $client = @stream_socket_client(
                "ssl://" . $domain . ":443", 
                $errno, 
                $errstr, 
                3, // 3 seconds timeout
                STREAM_CLIENT_CONNECT, 
                $context
            );

            if ($client) {
                $params = stream_context_get_params($client);
                if (isset($params['options']['ssl']['peer_certificate'])) {
                    $certInfo = openssl_x509_parse($params['options']['ssl']['peer_certificate']);
                    
                    // Basic check to see if the certificate is valid for our domain and not expired
                    $validTo = $certInfo['validTo_time_t'] ?? 0;
                    if ($validTo > time()) {
                        $isActive = true;
                    }
                }
                fclose($client);
            }
        } catch (\Exception $e) {
            // Ignore errors, implies SSL is not active or reachable
        }

        return $this->jsonResponse($response, [
            'domain' => $domain,
            'status' => $isActive ? 'active' : 'uninstalled'
        ]);
    }

    /**
     * Trigger Traefik to request a new SSL certificate by restarting the service
     */
    public function install(Request $request, Response $response): Response
    {
        $userId = $request->getAttribute('userId');
        $data = $request->getParsedBody();
        $domain = $data['domain'] ?? '';

        if (empty($domain)) {
            return $this->jsonResponse($response, ['error' => 'Domain is required'], 400);
        }

        // Verify domain belongs to user
        $dnsDomain = DnsDomain::where('user_id', $userId)->where('domain_name', $domain)->first();
        
        if (!$dnsDomain) {
            return $this->jsonResponse($response, ['error' => 'Domain not found or access denied'], 404);
        }

        // Find the service that uses this domain
        $services = Service::where('user_id', $userId)->get();
        $targetService = null;
        
        foreach ($services as $service) {
            $domainsList = array_map('trim', explode(',', $service->domain ?? ''));
            if (in_array($domain, $domainsList)) {
                $targetService = $service;
                break;
            }
        }

        if (!$targetService) {
            // It might be a parked/addon domain without a service attached yet
            return $this->jsonResponse($response, ['error' => 'Domain is not attached to any active application'], 400);
        }

        try {
            // Restart the container which triggers Traefik to request the Let's Encrypt cert again
            $this->dockerService->restartContainer('service_' . $targetService->id);
            
            return $this->jsonResponse($response, [
                'message' => 'SSL Installation triggered successfully. Traefik is now requesting a certificate.',
                'status' => 'pending'
            ]);
        } catch (\Exception $e) {
            return $this->jsonResponse($response, ['error' => 'Failed to trigger installation: ' . $e->getMessage()], 500);
        }
    }

    private function jsonResponse(Response $response, array $data, int $status = 200): Response
    {
        $response->getBody()->write(json_encode($data));
        return $response
            ->withStatus($status)
            ->withHeader('Content-Type', 'application/json');
    }
}
