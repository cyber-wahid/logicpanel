<?php

declare(strict_types=1);

namespace LogicPanel\Application\Controllers\Master;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use LogicPanel\Domain\Package\Package;

class PackageController
{
    // List packages
    public function index(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        try {
            $params = $request->getQueryParams();
            $type = $params['type'] ?? null;
            $currentUser = $request->getAttribute('user');

            $query = Package::query();

            // Filter by type if specified
            if ($type) {
                $query->where('type', $type);
            }

            // Reseller-specific filtering
            if ($currentUser) {
                $userRole = is_object($currentUser) ? ($currentUser->role ?? null) : ($currentUser['role'] ?? null);
                $userId = is_object($currentUser) ? ($currentUser->id ?? null) : ($currentUser['id'] ?? null);
                
                if ($userRole === 'reseller') {
                    // Resellers can ONLY see packages THEY created
                    // They CANNOT see admin's global packages
                    $query->where('created_by', $userId);
                }
                // Admin sees everything (no filter)
            }

            $packages = $query->orderBy('type')->orderBy('name')->get();
            
            // Add creator info to response
            $packagesWithCreator = $packages->map(function($package) {
                $data = $package->toArray();
                
                // Manually load creator if created_by is set
                if ($package->created_by) {
                    try {
                        $creator = \LogicPanel\Domain\User\User::find($package->created_by);
                        if ($creator) {
                            $data['creator'] = [
                                'id' => $creator->id,
                                'username' => $creator->username,
                                'role' => $creator->role
                            ];
                        } else {
                            $data['creator'] = null;
                        }
                    } catch (\Exception $e) {
                        $data['creator'] = null;
                    }
                } else {
                    $data['creator'] = null; // Admin/Global package
                }
                
                return $data;
            });
            
            return $this->jsonResponse($response, ['packages' => $packagesWithCreator]);
        } catch (\Exception $e) {
            error_log("PackageController::index error: " . $e->getMessage());
            error_log("Stack trace: " . $e->getTraceAsString());
            return $this->jsonResponse($response, ['error' => 'Failed to load packages: ' . $e->getMessage()], 500);
        }
    }


    // Get a single package
    public function get(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $id = $args['id'];
        $package = Package::find($id);

        if (!$package) {
            return $this->jsonResponse($response, ['error' => 'Package not found'], 404);
        }

        return $this->jsonResponse($response, ['package' => $package]);
    }

    // Create package
    public function create(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $data = $request->getParsedBody();
        $currentUser = $request->getAttribute('user');

        $package = new Package();
        $package->fill($data);

        // Set creator if reseller
        if ($currentUser) {
            $userRole = is_object($currentUser) ? ($currentUser->role ?? null) : ($currentUser['role'] ?? null);
            $userId = is_object($currentUser) ? ($currentUser->id ?? null) : ($currentUser['id'] ?? null);
            
            if ($userRole === 'reseller') {
                // Resellers can ONLY create 'user' type packages
                if (isset($data['type']) && $data['type'] !== 'user') {
                    return $this->jsonResponse($response, ['error' => 'Permission denied: Resellers can only create user packages'], 403);
                }
                
                $package->type = 'user'; // Force user type
                $package->created_by = $userId;
                $package->is_global = 0; // Reseller packages are NOT global
            } else {
                // Admin packages are global by default
                $package->is_global = 1;
                $package->created_by = null;
            }
        }

        try {
            $package->save();
            return $this->jsonResponse($response, ['message' => 'Package created successfully', 'package' => $package], 201);
        } catch (\Exception $e) {
            return $this->jsonResponse($response, ['error' => $e->getMessage()], 500);
        }
    }

    // Update package
    public function update(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $id = $args['id'];
        $package = Package::find($id);

        if (!$package) {
            return $this->jsonResponse($response, ['error' => 'Package not found'], 404);
        }

        // Check permission: Resellers can only edit their own packages
        $currentUser = $request->getAttribute('user');
        if ($currentUser) {
            $userRole = is_object($currentUser) ? ($currentUser->role ?? null) : ($currentUser['role'] ?? null);
            $userId = is_object($currentUser) ? ($currentUser->id ?? null) : ($currentUser['id'] ?? null);
            
            if ($userRole === 'reseller' && $package->created_by != $userId) {
                return $this->jsonResponse($response, ['error' => 'Permission denied: You can only edit your own packages'], 403);
            }
        }

        $data = $request->getParsedBody();
        $package->fill($data);

        try {
            $package->save();
            return $this->jsonResponse($response, ['message' => 'Package updated successfully', 'package' => $package]);
        } catch (\Exception $e) {
            return $this->jsonResponse($response, ['error' => 'Update failed: ' . $e->getMessage()], 500);
        }
    }

    // Delete package
    public function delete(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $id = $args['id'];
        $package = Package::find($id);

        if (!$package) {
            return $this->jsonResponse($response, ['error' => 'Package not found'], 404);
        }

        // Check permission: Resellers can only delete their own packages
        $currentUser = $request->getAttribute('user');
        if ($currentUser) {
            $userRole = is_object($currentUser) ? ($currentUser->role ?? null) : ($currentUser['role'] ?? null);
            $userId = is_object($currentUser) ? ($currentUser->id ?? null) : ($currentUser['id'] ?? null);
            
            if ($userRole === 'reseller' && $package->created_by != $userId) {
                return $this->jsonResponse($response, ['error' => 'Permission denied: You can only delete your own packages'], 403);
            }
        }

        $package->delete();

        return $this->jsonResponse($response, ['message' => 'Package deleted successfully']);
    }

    private function jsonResponse(ResponseInterface $response, array $data, int $status = 200): ResponseInterface
    {
        $response->getBody()->write(json_encode($data));
        return $response
            ->withStatus($status)
            ->withHeader('Content-Type', 'application/json');
    }
}
