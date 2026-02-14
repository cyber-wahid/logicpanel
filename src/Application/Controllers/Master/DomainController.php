<?php

declare(strict_types=1);

namespace LogicPanel\Application\Controllers\Master;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use LogicPanel\Domain\Domain\Domain;

class DomainController
{
    // List all domains
    public function index(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        try {
            $params = $request->getQueryParams();
            $search = $params['q'] ?? '';

            $query = Domain::with(['user', 'user.owner', 'parent']);

            $currentUser = $request->getAttribute('user');
            if ($currentUser && $currentUser->role === 'reseller') {
                $query->whereHas('user', function ($q) use ($currentUser) {
                    $q->where('owner_id', $currentUser->id);
                });
            }

            if (!empty($search)) {
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'LIKE', "%{$search}%")
                        ->orWhereHas('user', function ($u) use ($search) {
                            $u->where('username', 'LIKE', "%{$search}%")
                                ->orWhere('email', 'LIKE', "%{$search}%");
                        });
                });
            }

            $domains = $query->get();

            // Pre-fetch services to map container IDs (Avoid N+1 queries ideally, but for now simple fetch)
            // Or just do it inside map since volume is low.
            // Better: Load all services for these users? 
            // Let's do a direct lookup for now.

            $data = $domains->map(function ($domain) {
                // Resolve Container ID if it's an application domain
                $containerId = null;
                if ($domain->type === 'application' || $domain->type === 'subdomain') {
                    // Find service that has this domain
                    // Service domain column is comma-separated string.
                    // We check if service belongs to user and domain string contains this domain.
                    // This is "loose" linking but effective given current schema.
                    $service = \LogicPanel\Domain\Service\Service::where('user_id', $domain->user_id)
                        ->where('domain', 'LIKE', "%{$domain->name}%")
                        ->first();

                    if ($service) {
                        $containerId = $service->container_id;
                    }
                }

                $ownerInfo = null;
                if ($domain->user && $domain->user->owner_id && $domain->user->owner) {
                    $ownerInfo = [
                        'id' => $domain->user->owner->id,
                        'username' => $domain->user->owner->username,
                        'role' => $domain->user->owner->role
                    ];
                }

                return [
                    'id' => $domain->id,
                    'name' => $domain->name,
                    'type' => $domain->type,
                    'user' => $domain->user ? $domain->user->username : 'System',
                    'user_owner' => $ownerInfo,
                    'path' => $domain->path,
                    'parent' => $domain->parent ? $domain->parent->name : null,
                    'container_id' => $containerId, // Added field
                    'created_at' => $domain->created_at ? $domain->created_at->toIso8601String() : null,
                ];
            });

            return $this->jsonResponse($response, ['domains' => $data]);
        } catch (\Exception $e) {
            return $this->jsonResponse($response, [
                'error' => 'Internal Server Error',
                'message' => $e->getMessage(),
                // 'trace' => $e->getTraceAsString() 
            ], 500);
        }
    }

    // Create a new domain
    public function create(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $data = $request->getParsedBody();
        $name = $data['name'] ?? '';
        $user_id = $data['user_id'] ?? null;
        $type = $data['type'] ?? 'primary';
        $path = $data['path'] ?? '/public_html';
        $parent_id = $data['parent_id'] ?? null;

        if (empty($name) || empty($user_id)) {
            return $this->jsonResponse($response, ['error' => 'Domain Name and User are required.'], 400);
        }

        if (Domain::where('name', $name)->exists()) {
            return $this->jsonResponse($response, ['error' => 'Domain already exists.'], 409);
        }

        // Reseller Permission Check
        $currentUser = $request->getAttribute('user');
        if ($currentUser && $currentUser->role === 'reseller') {
            $user = \LogicPanel\Domain\User\User::find($user_id);
            if (!$user || $user->owner_id !== $currentUser->id) {
                return $this->jsonResponse($response, ['error' => 'You do not have permission to assign domains to this user.'], 403);
            }
        }

        try {
            $domain = new Domain();
            $domain->name = $name;
            $domain->user_id = $user_id;
            $domain->type = $type;
            $domain->path = $path;
            if ($parent_id) {
                $domain->parent_id = $parent_id;
            }
            $domain->save();

            return $this->jsonResponse($response, ['result' => 'success', 'message' => 'Domain created successfully', 'domain' => $domain], 201);
        } catch (\Exception $e) {
            return $this->jsonResponse($response, ['result' => 'error', 'message' => $e->getMessage()], 500);
        }
    }

    // Delete a domain
    public function delete(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $id = $args['id'] ?? null;

        if (!$id) {
            return $this->jsonResponse($response, ['error' => 'Domain ID is required.'], 400);
        }

        $domain = Domain::find($id);

        if (!$domain) {
            return $this->jsonResponse($response, ['error' => 'Domain not found.'], 404);
        }

        // Reseller Permission Check
        $currentUser = $request->getAttribute('user');
        if ($currentUser && $currentUser->role === 'reseller') {
            $user = \LogicPanel\Domain\User\User::find($domain->user_id);
            if (!$user || $user->owner_id !== $currentUser->id) {
                return $this->jsonResponse($response, ['error' => 'You do not have permission to delete this domain.'], 403);
            }
        }

        try {
            $domain->delete();
            return $this->jsonResponse($response, ['result' => 'success', 'message' => 'Domain deleted successfully']);
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
