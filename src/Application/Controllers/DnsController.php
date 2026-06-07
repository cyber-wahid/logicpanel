<?php

declare(strict_types=1);

namespace LogicPanel\Application\Controllers;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use LogicPanel\Domain\Dns\DnsDomain;
use LogicPanel\Domain\Dns\DnsRecord;
use LogicPanel\Infrastructure\Dns\CoreDnsService;

class DnsController
{
    private CoreDnsService $dnsService;

    public function __construct(CoreDnsService $dnsService)
    {
        $this->dnsService = $dnsService;
    }

    public function index(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $userId = $request->getAttribute('userId');
        
        // Auto-sync domains with active services before returning the list
        $this->dnsService->syncUserDomains($userId);
        
        $domains = DnsDomain::with('records')->where('user_id', $userId)->get();

        $data = $domains->map(function ($domain) {
            return [
                'id' => $domain->id,
                'domain_name' => $domain->domain_name,
                'status' => $domain->status,
                'records_count' => $domain->records->count(),
                'created_at' => $domain->created_at->toIso8601String(),
            ];
        });

        return $this->jsonResponse($response, ['domains' => $data]);
    }

    public function show(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $userId = $request->getAttribute('userId');
        $domainId = (int) $args['id'];

        $domain = DnsDomain::with('records')
            ->where('id', $domainId)
            ->where('user_id', $userId)
            ->first();

        if (!$domain) {
            return $this->jsonResponse($response, ['error' => 'Domain not found'], 404);
        }

        return $this->jsonResponse($response, ['domain' => $domain]);
    }

    // createDomain and deleteDomain endpoints have been removed.
    // Domains are now managed automatically via ServiceController synchronization.

    public function createRecord(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $userId = $request->getAttribute('userId');
        $domainId = (int) $args['id'];
        $data = $request->getParsedBody();

        $domain = DnsDomain::where('id', $domainId)
            ->where('user_id', $userId)
            ->first();

        if (!$domain) {
            return $this->jsonResponse($response, ['error' => 'Domain not found'], 404);
        }

        if (empty($data['type']) || empty($data['name']) || empty($data['content'])) {
            return $this->jsonResponse($response, ['error' => 'Type, name, and content are required'], 400);
        }

        $record = DnsRecord::create([
            'domain_id' => $domain->id,
            'type' => strtoupper(trim($data['type'])),
            'name' => trim($data['name']),
            'content' => trim($data['content']),
            'ttl' => isset($data['ttl']) ? (int) $data['ttl'] : 3600,
            'prio' => isset($data['prio']) ? (int) $data['prio'] : 0,
        ]);

        $this->dnsService->generateZoneFile($domain);

        return $this->jsonResponse($response, ['message' => 'Record added successfully', 'record' => $record], 201);
    }

    public function deleteRecord(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $userId = $request->getAttribute('userId');
        $domainId = (int) $args['id'];
        $recordId = (int) $args['record_id'];

        $domain = DnsDomain::where('id', $domainId)
            ->where('user_id', $userId)
            ->first();

        if (!$domain) {
            return $this->jsonResponse($response, ['error' => 'Domain not found'], 404);
        }

        $record = DnsRecord::where('id', $recordId)->where('domain_id', $domain->id)->first();

        if (!$record) {
            return $this->jsonResponse($response, ['error' => 'Record not found'], 404);
        }

        $record->delete();
        $this->dnsService->generateZoneFile($domain);

        return $this->jsonResponse($response, ['message' => 'Record deleted successfully']);
    }

    private function jsonResponse(ResponseInterface $response, array $data, int $status = 200): ResponseInterface
    {
        $response->getBody()->write(json_encode($data));
        return $response
            ->withStatus($status)
            ->withHeader('Content-Type', 'application/json');
    }
}
