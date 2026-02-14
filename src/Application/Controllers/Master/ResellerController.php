<?php

declare(strict_types=1);

namespace LogicPanel\Application\Controllers\Master;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use LogicPanel\Domain\User\User;

class ResellerController
{
    public function stats(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $user = $request->getAttribute('user');
        $isReseller = ($user['role'] ?? '') === 'reseller';
        
        if ($isReseller) {
            // Reseller stats: only their own users
            $totalUsers = User::where('owner_id', $user['id'])->count();
            $activeUsers = User::where('owner_id', $user['id'])->where('status', 'active')->count();
        } else {
            // Admin stats: all resellers
            $totalUsers = User::where('role', 'reseller')->count();
            $activeUsers = User::where('role', 'reseller')->where('status', 'active')->count();
        }
        
        $stats = [
            'total_users' => $totalUsers,
            'active_users' => $activeUsers,
            'suspended_users' => $totalUsers - $activeUsers
        ];
        
        $response->getBody()->write(json_encode($stats));
        return $response->withHeader('Content-Type', 'application/json');
    }
    
    public function resourceStats(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $user = $request->getAttribute('user');
        $resellerId = $user['id'] ?? 0;
        
        // Get reseller's package limits
        $resellerUser = User::with('package')->find($resellerId);
        if (!$resellerUser || !$resellerUser->package) {
            return $this->jsonResponse($response, [
                'error' => 'Reseller package not found'
            ], 404);
        }
        
        $package = $resellerUser->package;
        
        // Count users created by this reseller
        $totalUsers = User::where('created_by', $resellerId)->where('role', 'user')->count();
        $maxUsers = $package->max_accounts ?? 0; // 0 = unlimited
        
        // Calculate total disk allocated to reseller's users
        $users = User::where('created_by', $resellerId)->where('role', 'user')->with('package')->get();
        $totalDiskAllocated = 0;
        $totalBandwidthAllocated = 0;
        
        foreach ($users as $u) {
            if ($u->package) {
                $totalDiskAllocated += $u->package->storage_limit ?? 0;
                $totalBandwidthAllocated += $u->package->bandwidth_limit ?? 0;
            }
        }
        
        // Reseller's limits
        $diskLimit = $package->storage_limit ?? 0; // in MB
        $bandwidthLimit = $package->bandwidth_limit ?? 0; // in MB
        
        $stats = [
            'users' => [
                'used' => $totalUsers,
                'limit' => $maxUsers,
                'percentage' => $maxUsers > 0 ? round(($totalUsers / $maxUsers) * 100, 1) : 0
            ],
            'disk' => [
                'used' => $this->formatSize($totalDiskAllocated),
                'limit' => $diskLimit > 0 ? $this->formatSize($diskLimit) : '∞',
                'used_mb' => $totalDiskAllocated,
                'limit_mb' => $diskLimit,
                'percentage' => $diskLimit > 0 ? round(($totalDiskAllocated / $diskLimit) * 100, 1) : 0
            ],
            'bandwidth' => [
                'used' => $this->formatSize($totalBandwidthAllocated),
                'limit' => $bandwidthLimit > 0 ? $this->formatSize($bandwidthLimit) : '∞',
                'used_mb' => $totalBandwidthAllocated,
                'limit_mb' => $bandwidthLimit,
                'percentage' => $bandwidthLimit > 0 ? round(($totalBandwidthAllocated / $bandwidthLimit) * 100, 1) : 0
            ]
        ];
        
        return $this->jsonResponse($response, $stats);
    }
    
    private function formatSize(int $mb): string
    {
        if ($mb >= 1024) {
            return round($mb / 1024, 1) . ' GB';
        }
        return $mb . ' MB';
    }
    
    public function delete(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        try {
            $id = $args['id'] ?? null;
            
            if (!$id) {
                return $this->jsonResponse($response, ['error' => 'Reseller ID required'], 400);
            }
            
            // Find the reseller
            $reseller = User::where('id', $id)->where('role', 'reseller')->first();
            
            if (!$reseller) {
                return $this->jsonResponse($response, ['error' => 'Reseller not found'], 404);
            }
            
            // Check if reseller has users
            $userCount = User::where('owner_id', $id)->count();
            
            if ($userCount > 0) {
                return $this->jsonResponse($response, [
                    'error' => "Cannot delete reseller with {$userCount} active user(s). Please delete or reassign their users first."
                ], 400);
            }
            
            // Delete the reseller
            $reseller->delete();
            
            return $this->jsonResponse($response, [
                'success' => true,
                'message' => 'Reseller deleted successfully'
            ]);
            
        } catch (\Throwable $e) {
            return $this->jsonResponse($response, [
                'error' => 'Failed to delete reseller: ' . $e->getMessage()
            ], 500);
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
