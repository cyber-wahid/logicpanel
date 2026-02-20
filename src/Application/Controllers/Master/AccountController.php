<?php

declare(strict_types=1);

namespace LogicPanel\Application\Controllers\Master;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use LogicPanel\Domain\User\User;
use LogicPanel\Domain\Package\Package;

class AccountController
{
    private $systemBridge;
    private $dockerService;
    private $jwtService;

    public function __construct(
        \LogicPanel\Application\Services\SystemBridgeService $systemBridge,
        \LogicPanel\Infrastructure\Docker\DockerService $dockerService,
        \LogicPanel\Application\Services\JwtService $jwtService
    ) {
        $this->systemBridge = $systemBridge;
        $this->dockerService = $dockerService;
        $this->jwtService = $jwtService;
    }

    // List all accounts
    public function index(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        try {
            // Get current logged-in user (Admin or Reseller)
            $currentUser = $request->getAttribute('user');

            // Query builder
            $query = User::with(['services', 'owner', 'package'])->where('role', '!=', 'admin')->where('role', '!=', 'root');

            // If Reseller, only show their users
            if ($currentUser && $currentUser->role === 'reseller') {
                $query->where('owner_id', $currentUser->id);
            }

            // Filter by role if provided (e.g. ?role=reseller)
            $queryParams = $request->getQueryParams();
            if (isset($queryParams['role']) && !empty($queryParams['role'])) {
                $query->where('role', $queryParams['role']);
            }

            // Filter by status if provided (e.g. ?status=locked)
            if (isset($queryParams['status']) && $queryParams['status'] === 'locked') {
                // Get locked accounts: locked_until is not null OR failed_login_attempts >= 5
                $query->where(function ($q) {
                    $q->whereNotNull('locked_until')
                        ->orWhere('failed_login_attempts', '>=', 5);
                });
            }

            $users = $query->get();

            $data = $users->map(function ($user) {
                $ownerInfo = null;
                if ($user->owner_id && $user->owner) {
                    $ownerInfo = [
                        'id' => $user->owner->id,
                        'username' => $user->owner->username,
                        'role' => $user->owner->role
                    ];
                }

                return [
                    'id' => $user->id,
                    'username' => $user->username,
                    'email' => $user->email,
                    'role' => $user->role,
                    'domain' => $user->domain ?? 'N/A',
                    'package' => $user->package ? $user->package->name : 'Default',
                    'ip' => $_ENV['SERVER_IP'] ?? $_SERVER['SERVER_ADDR'] ?? gethostbyname(gethostname()) ?: 'N/A',
                    'created_at' => $user->created_at ? $user->created_at->toIso8601String() : date('c'),
                    'status' => $user->status ?? 'unknown',
                    'services_count' => $user->services->count(),
                    'locked_at' => $user->locked_until,
                    'failed_attempts' => $user->failed_login_attempts ?? 0,
                    'owner' => $ownerInfo // Reseller info if user is under a reseller
                ];
            });

            return $this->jsonResponse($response, ['accounts' => $data]);
        } catch (\Throwable $e) {
            return $this->jsonResponse($response, ['error' => $e->getMessage()], 500);
        }
    }

    // Get single account by ID
    public function show(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        try {
            $id = $args['id'];
            $currentUser = $request->getAttribute('user');

            // Query builder
            $query = User::with(['services', 'owner', 'package'])->where('id', $id);

            // If Reseller, only show their users
            if ($currentUser && $currentUser->role === 'reseller') {
                $query->where('owner_id', $currentUser->id);
            }

            $user = $query->first();

            if (!$user) {
                return $this->jsonResponse($response, ['error' => 'Account not found'], 404);
            }

            $ownerInfo = null;
            if ($user->owner_id && $user->owner) {
                $ownerInfo = [
                    'id' => $user->owner->id,
                    'username' => $user->owner->username,
                    'role' => $user->owner->role
                ];
            }

            $data = [
                'id' => $user->id,
                'username' => $user->username,
                'email' => $user->email,
                'role' => $user->role,
                'domain' => $user->domain ?? 'N/A',
                'package_id' => $user->package_id,
                'package' => $user->package ? $user->package->name : 'Default',
                'ip' => $_ENV['SERVER_IP'] ?? $_SERVER['SERVER_ADDR'] ?? gethostbyname(gethostname()) ?: 'N/A',
                'created_at' => $user->created_at ? $user->created_at->toIso8601String() : date('c'),
                'status' => $user->status ?? 'unknown',
                'services_count' => $user->services->count(),
                'locked_at' => $user->locked_until,
                'failed_attempts' => $user->failed_login_attempts ?? 0,
                'owner' => $ownerInfo
            ];

            return $this->jsonResponse($response, ['account' => $data]);
        } catch (\Throwable $e) {
            return $this->jsonResponse($response, ['error' => $e->getMessage()], 500);
        }
    }


    // Get single account details
    public function get(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $id = $args['id'];
        $currentUser = $request->getAttribute('user');
        $user = $this->findUserWithPermission($id, $currentUser);

        if (!$user) {
            return $this->jsonResponse($response, ['error' => 'User not found'], 404);
        }

        $userData = [
            'id' => $user->id,
            'username' => $user->username,
            'email' => $user->email,
            'role' => $user->role,
            'domain' => $user->domain ?? '',
            'package_id' => $user->package_id,
            'status' => $user->status,
            'created_at' => $user->created_at->toIso8601String(),
            'services_count' => $user->services()->count(),
            'databases_count' => $user->databases()->count(),
            'domains_count' => $user->domains()->count(),
        ];

        return $this->jsonResponse($response, ['account' => $userData]);
    }

    // Update account details
    public function update(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $id = $args['id'];
        $data = $request->getParsedBody();
        $currentUser = $request->getAttribute('user');
        $user = $this->findUserWithPermission($id, $currentUser);

        if (!$user) {
            return $this->jsonResponse($response, ['error' => 'User not found'], 404);
        }

        try {
            // Update Password if provided
            if (!empty($data['password'])) {
                $password = $data['password'];
                $passwordError = $this->validatePasswordComplexity($password);
                if ($passwordError) {
                    return $this->jsonResponse($response, ['error' => $passwordError], 400);
                }

                // System Password Change
                try {
                    $this->systemBridge->changePassword($user->username, $password);
                } catch (\Exception $e) {
                    // Start of Selection
                    // Allow continue if system user doesn't exist (Docker mode)
                }

                $user->setPassword($password);
            }

            // Update Email
            if (!empty($data['email'])) {
                $user->email = $data['email'];
            }

            // Update Package
            if (!empty($data['package_id'])) {
                $user->package_id = $data['package_id'];
            }

            // Update Status
            if (!empty($data['status'])) {
                $user->status = $data['status'];
            }

            $user->save();

            return $this->jsonResponse($response, ['result' => 'success', 'message' => 'Account updated successfully']);
        } catch (\Exception $e) {
            return $this->jsonResponse($response, ['error' => 'Failed to update account: ' . $e->getMessage()], 500);
        }
    }

    // Login as User (Impersonation)
    public function loginAsUser(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $id = $args['id'];
        $user = User::find($id);

        if (!$user) {
            return $this->jsonResponse($response, ['error' => 'User not found'], 404);
        }

        try {
            // Generate a short-lived one-time token for this user
            $token = $this->jwtService->generateOneTimeToken($user);

            // Determine User Panel URL: Use current host but dynamic port
            $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
            $domain = parse_url('http://' . $host, PHP_URL_HOST);
            $userPort = $_ENV['USER_PORT'] ?? 767;
            $userPanelUrlHost = $domain . ':' . $userPort;

            $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https')) ? 'https' : 'http';
            $userPanelUrl = $protocol . '://' . $userPanelUrlHost;

            return $this->jsonResponse($response, [
                'result' => 'success',
                'token' => $token,
                'redirect_url' => rtrim($userPanelUrl, '/') . '/?token=' . $token,
                'message' => "Logged in as {$user->username}"
            ]);
        } catch (\Exception $e) {
            return $this->jsonResponse($response, ['error' => 'Failed to generate token'], 500);
        }
    }

    // Create a new account
    public function create(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $data = $request->getParsedBody();
        $username = $data['username'] ?? '';
        $email = $data['email'] ?? '';
        $password = $data['password'] ?? '';
        $domain = $data['domain'] ?? '';
        $package = $data['package'] ?? 'default';
        $role = $data['role'] ?? 'user'; // Allow role selection

        // Validate role - only admin can create resellers
        $currentUser = $request->getAttribute('user');

        // Resellers can ONLY create 'user' accounts, NOT 'reseller' accounts
        if ($currentUser && $currentUser->role === 'reseller') {
            if ($role !== 'user') {
                return $this->jsonResponse($response, ['error' => 'Permission denied: Resellers can only create user accounts'], 403);
            }
        }

        // Only admin/root can create reseller accounts
        $allowedRoles = ['user'];
        if ($currentUser && ($currentUser->role === 'admin' || $currentUser->role === 'root')) {
            $allowedRoles = ['user', 'reseller'];
        }
        if (!in_array($role, $allowedRoles)) {
            $role = 'user'; // Fallback to user if invalid role
        }

        if (empty($username) || empty($email) || empty($password)) {
            return $this->jsonResponse($response, ['error' => 'Username, Email, and Password are required.'], 400);
        }

        // Password complexity validation
        $passwordError = $this->validatePasswordComplexity($password);
        if ($passwordError) {
            return $this->jsonResponse($response, ['error' => $passwordError], 400);
        }

        // Check if user exists in DB
        if (User::where('username', $username)->exists() || User::where('email', $email)->exists()) {
            return $this->jsonResponse($response, ['error' => 'User already exists.'], 409);
        }

        // Reseller Limit Check
        $currentUser = $request->getAttribute('user');
        if ($currentUser && $currentUser->role === 'reseller') {
            // Get Reseller's Package Limits
            $resellerPackage = Package::find($currentUser->package_id);
            if (!$resellerPackage) {
                // If no package assigned, assume zero limits or unlimited? Let's be strict.
                return $this->jsonResponse($response, ['error' => 'No package assigned to your reseller account.'], 403);
            }

            // Check User Limit
            if ($resellerPackage->limit_users > 0) {
                $currentUsersCount = User::where('owner_id', $currentUser->id)->count();
                if ($currentUsersCount >= $resellerPackage->limit_users) {
                    return $this->jsonResponse($response, ['error' => 'User limit reached for your reseller account.'], 403);
                }
            }

            // Check Disk Limit (Total) - This is tricky, we need to sum up allocated storage of all users
            // For now, let's just check if the NEW package fits
            // Note: This logic assumes we credit the *Package Limit* against the Reseller's *Total Limit*
            // Real usage might be different but allocation tracking is safer for limits.

            // To do this, we need the package the reseller is trying to assign
        }

        try {
            // Resolve Package
            $packageId = $data['package_id'] ?? null;
            $packageName = $data['package_name'] ?? null;
            $package = null;

            if ($packageId) {
                $package = Package::find($packageId);
            } elseif ($packageName) {
                $package = Package::where('name', $packageName)->first();
            }

            // Fallback to first package if not found (or handle error)
            if (!$package) {
                // If reseller, they MUST select a valid package
                if ($currentUser && $currentUser->role === 'reseller') {
                    return $this->jsonResponse($response, ['error' => 'Invalid package selected.'], 400);
                }
                $package = Package::first();
            }

            // Additional Reseller Checks after resolving package
            if ($currentUser && $currentUser->role === 'reseller') {
                $resellerPackage = Package::find($currentUser->package_id);

                // 1. Reseller cannot assign a package of type 'reseller'
                if ($package->type === 'reseller') {
                    return $this->jsonResponse($response, ['error' => 'Resellers cannot create other resellers.'], 403);
                }

                // 2. Check Disk Allocation Limit
                if ($resellerPackage->limit_disk_total > 0) {
                    $totalAllocatedDisk = User::join('packages', 'users.package_id', '=', 'packages.id')
                        ->where('users.owner_id', $currentUser->id)
                        ->sum('packages.storage_limit');

                    if (($totalAllocatedDisk + $package->storage_limit) > $resellerPackage->limit_disk_total) {
                        return $this->jsonResponse($response, ['error' => 'Disk allocation limit reached.'], 403);
                    }
                }

                // 3. Check Bandwidth Allocation Limit
                if ($resellerPackage->limit_bandwidth_total > 0) {
                    $totalAllocatedBandwidth = User::join('packages', 'users.package_id', '=', 'packages.id')
                        ->where('users.owner_id', $currentUser->id)
                        ->sum('packages.bandwidth_limit');

                    if (($totalAllocatedBandwidth + $package->bandwidth_limit) > $resellerPackage->limit_bandwidth_total) {
                        return $this->jsonResponse($response, ['error' => 'Bandwidth allocation limit reached.'], 403);
                    }
                }
            }

            // Note: In Docker-based hosting, we don't need Linux system users.
            // Each user's apps run in their own containers.
            // We only need the database record.

            // Create User Entity in DB
            $user = new User();
            $user->username = $username;
            $user->email = $email;
            $user->setPassword($password);
            $user->role = $role;
            $user->status = 'active';
            if ($package) {
                $user->package_id = $package->id;
            }

            $currentUser = $request->getAttribute('user');
            if ($currentUser && $currentUser->role === 'reseller') {
                $user->owner_id = $currentUser->id;
            }

            $user->save();

            return $this->jsonResponse($response, [
                'result' => 'success', // WHMCS-friendly
                'message' => 'Account created successfully',
                'account' => [
                    'id' => $user->id,
                    'username' => $user->username,
                    'domain' => $domain
                ]
            ], 201);
        } catch (\Exception $e) {
            return $this->jsonResponse($response, ['result' => 'error', 'message' => 'Failed to create account: ' . $e->getMessage()], 500);
        }
    }

    // Suspend an account
    public function suspend(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        // Support both ID in URL (Panel) and username in Body (WHMCS)
        $id = $args['id'] ?? null;
        $data = $request->getParsedBody();
        $username = $data['username'] ?? '';

        $currentUser = $request->getAttribute('user');
        $target = $id ?: $username;
        $user = $this->findUserWithPermission($target, $currentUser);

        if (!$user) {
            return $this->jsonResponse($response, ['result' => 'error', 'message' => 'User not found'], 404);
        }

        try {
            // System Lock
            $this->systemBridge->lockUser($user->username);

            // Stop Containers
            $services = $user->services;
            foreach ($services as $service) {
                if ($service->container_id) {
                    try {
                        $this->dockerService->stopContainer($service->container_id);
                    } catch (\Exception $e) {
                        // Log error but continue suspending other services
                    }
                }
            }

            $user->status = 'suspended';
            $user->save();

            return $this->jsonResponse($response, ['result' => 'success', 'message' => 'Account suspended and services stopped']);
        } catch (\Exception $e) {
            return $this->jsonResponse($response, ['result' => 'error', 'message' => 'Failed to suspend account: ' . $e->getMessage()], 500);
        }
    }

    // Unsuspend an account
    public function unsuspend(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $id = $args['id'] ?? null;
        $data = $request->getParsedBody();
        $username = $data['username'] ?? '';

        $currentUser = $request->getAttribute('user');
        $target = $id ?: $username;
        $user = $this->findUserWithPermission($target, $currentUser);

        if (!$user) {
            return $this->jsonResponse($response, ['result' => 'error', 'message' => 'User not found'], 404);
        }

        try {
            // System Unlock
            $this->systemBridge->unlockUser($user->username);

            // Start Containers
            // Let's start them.
            $services = $user->services;
            foreach ($services as $service) {
                if ($service->container_id) {
                    try {
                        $this->dockerService->startContainer($service->container_id);
                    } catch (\Exception $e) {
                        // Log error
                    }
                }
            }

            $user->status = 'active';
            $user->save();

            return $this->jsonResponse($response, ['result' => 'success', 'message' => 'Account unsuspended and services started']);
        } catch (\Exception $e) {
            return $this->jsonResponse($response, ['result' => 'error', 'message' => 'Failed to unsuspend account: ' . $e->getMessage()], 500);
        }
    }

    // Unlock a locked account (failed login attempts)
    public function unlock(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $id = $args['id'] ?? null;

        if (!$id) {
            return $this->jsonResponse($response, ['error' => 'Account ID is required'], 400);
        }

        $user = User::find($id);

        if (!$user) {
            return $this->jsonResponse($response, ['error' => 'Account not found'], 404);
        }

        // Reseller permission check
        $currentUser = $request->getAttribute('user');
        if ($currentUser && $currentUser->role === 'reseller') {
            if ($user->owner_id !== $currentUser->id) {
                return $this->jsonResponse($response, ['error' => 'Permission denied'], 403);
            }
        }

        try {
            // Reset failed login attempts and unlock
            $user->failed_login_attempts = 0;
            $user->locked_until = null;
            $user->save();

            return $this->jsonResponse($response, [
                'result' => 'success',
                'message' => 'Account unlocked successfully',
                'account' => [
                    'id' => $user->id,
                    'username' => $user->username,
                    'email' => $user->email,
                    'locked_until' => null,
                    'failed_attempts' => 0
                ]
            ]);
        } catch (\Exception $e) {
            return $this->jsonResponse($response, [
                'error' => 'Failed to unlock account: ' . $e->getMessage()
            ], 500);
        }
    }

    // Terminate (Delete) an account
    public function terminate(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $id = $args['id'] ?? null;
        $data = $request->getParsedBody();
        $username = $data['username'] ?? '';

        $currentUser = $request->getAttribute('user');
        $target = $id ?: $username;
        $user = $this->findUserWithPermission($target, $currentUser);

        if (!$user) {
            return $this->jsonResponse($response, ['result' => 'error', 'message' => 'User not found'], 404);
        }

        try {
            // 1. Delete Services (Containers)
            $services = $user->services;
            foreach ($services as $service) {
                if ($service->container_id) {
                    try {
                        $this->dockerService->removeContainer($service->container_id);
                    } catch (\Exception $e) {
                        // Ignore if already deleted
                    }
                }
                $service->delete();
            }

            // 2. System Delete
            // Warning: This deletes home directory too!
            $this->systemBridge->deleteUser($user->username);

            // 3. Delete Databases
            $user->databases()->delete();
            $user->domains()->delete();

            $user->delete();

            return $this->jsonResponse($response, ['result' => 'success', 'message' => 'Account terminated']);
        } catch (\Exception $e) {
            return $this->jsonResponse($response, ['result' => 'error', 'message' => 'Failed to terminate account: ' . $e->getMessage()], 500);
        }
    }

    // Change Password
    public function changePassword(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $data = $request->getParsedBody();
        $username = $data['username'] ?? '';
        $password = $data['password'] ?? '';

        $user = User::where('username', $username)->first();
        if (!$user) {
            return $this->jsonResponse($response, ['result' => 'error', 'message' => 'User not found'], 404);
        }

        try {
            // System Password Change
            $this->systemBridge->changePassword($username, $password);

            $user->setPassword($password);
            $user->save();

            return $this->jsonResponse($response, ['result' => 'success', 'message' => 'Password changed successfully']);
        } catch (\Exception $e) {
            return $this->jsonResponse($response, ['result' => 'error', 'message' => 'Failed to change password: ' . $e->getMessage()], 500);
        }
    }

    // WHMCS: Change Package
    // WHMCS: Change Package
    public function changePackage(ServerRequestInterface $request, ResponseInterface $response, array $args = []): ResponseInterface
    {
        // Support both ID in URL and username in body
        $id = $args['id'] ?? null;
        $data = $request->getParsedBody();
        $username = $data['username'] ?? null;
        $packageId = $data['package_id'] ?? null;
        $packageName = $data['package_name'] ?? null;

        if (!$packageId && !$packageName) {
            return $this->jsonResponse($response, ['result' => 'error', 'message' => 'Package ID or name is required'], 400);
        }

        $currentUser = $request->getAttribute('user');
        $target = $id ?: $username;

        if (!$target) {
            return $this->jsonResponse($response, ['result' => 'error', 'message' => 'User ID or username is required'], 400);
        }

        $user = $this->findUserWithPermission($target, $currentUser);

        if (!$user) {
            return $this->jsonResponse($response, ['result' => 'error', 'message' => 'User not found'], 404);
        }

        // Find package
        if ($packageId) {
            $package = Package::find($packageId);
        } else {
            $package = Package::where('name', $packageName)->first();
        }

        if (!$package) {
            return $this->jsonResponse($response, ['result' => 'error', 'message' => 'Package not found'], 404);
        }

        try {
            $user->package_id = $package->id;
            $user->save();

            return $this->jsonResponse($response, ['result' => 'success', 'message' => 'Package changed successfully']);
        } catch (\Exception $e) {
            return $this->jsonResponse($response, ['result' => 'error', 'message' => 'Failed to change package: ' . $e->getMessage()], 500);
        }
    }

    // API: Change Password by Account ID (for WHMCS/Blesta)
    public function changePasswordById(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $id = $args['id'] ?? null;
        $data = $request->getParsedBody();
        $password = $data['password'] ?? '';

        if (empty($password)) {
            return $this->jsonResponse($response, ['result' => 'error', 'message' => 'Password is required'], 400);
        }

        $passwordError = $this->validatePasswordComplexity($password);
        if ($passwordError) {
            return $this->jsonResponse($response, ['result' => 'error', 'message' => $passwordError], 400);
        }

        $currentUser = $request->getAttribute('user');
        $user = $this->findUserWithPermission($id, $currentUser);

        if (!$user) {
            return $this->jsonResponse($response, ['result' => 'error', 'message' => 'User not found'], 404);
        }

        try {
            // System Password Change
            try {
                $this->systemBridge->changePassword($user->username, $password);
            } catch (\Exception $e) {
                // Allow continue if system user doesn't exist (Docker mode)
            }

            $user->setPassword($password);
            $user->save();

            return $this->jsonResponse($response, ['result' => 'success', 'message' => 'Password changed successfully']);
        } catch (\Exception $e) {
            return $this->jsonResponse($response, ['result' => 'error', 'message' => 'Failed to change password: ' . $e->getMessage()], 500);
        }
    }


    private function jsonResponse(ResponseInterface $response, array $data, int $status = 200): ResponseInterface
    {
        $response->getBody()->write(json_encode($data));
        return $response
            ->withStatus($status)
            ->withHeader('Content-Type', 'application/json');
    }

    private function findUserWithPermission($idOrUsername, $currentUser)
    {
        if (is_numeric($idOrUsername)) {
            $user = User::find($idOrUsername);
        } else {
            $user = User::where('username', $idOrUsername)->first();
        }

        if (!$user) {
            return null;
        }

        // Admin/root can manage anyone
        if ($currentUser && in_array($currentUser->role, ['admin', 'root'])) {
            return $user;
        }

        // Reseller can manage their own users (where owner_id = reseller_id)
        if ($currentUser && $currentUser->role === 'reseller') {
            // Reseller can manage users they own
            if ($user->owner_id === $currentUser->id) {
                return $user;
            }
            // Reseller CANNOT manage other resellers or admins
            if (in_array($user->role, ['reseller', 'admin', 'root'])) {
                return null;
            }
            // Reseller cannot manage users owned by others
            return null;
        }

        return $user;
    }

    /**
     * Validate password complexity requirements
     * Returns error message if invalid, null if valid
     */
    private function validatePasswordComplexity(string $password): ?string
    {
        if (strlen($password) < 8) {
            return 'Password must be at least 8 characters';
        }

        if (!preg_match('/[A-Z]/', $password)) {
            return 'Password must contain at least one uppercase letter';
        }

        if (!preg_match('/[a-z]/', $password)) {
            return 'Password must contain at least one lowercase letter';
        }

        if (!preg_match('/[0-9]/', $password)) {
            return 'Password must contain at least one number';
        }

        if (!preg_match('/[^A-Za-z0-9]/', $password)) {
            return 'Password must contain at least one special character';
        }

        return null;
    }

}
