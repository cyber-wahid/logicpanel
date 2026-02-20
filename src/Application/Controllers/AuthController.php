<?php

declare(strict_types=1);

namespace LogicPanel\Application\Controllers;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use LogicPanel\Application\Services\JwtService;
use LogicPanel\Application\Services\TokenBlacklistService;
use LogicPanel\Domain\User\User;

class AuthController
{
    private JwtService $jwtService;
    private TokenBlacklistService $blacklistService;

    public function __construct(JwtService $jwtService, TokenBlacklistService $blacklistService)
    {
        $this->jwtService = $jwtService;
        $this->blacklistService = $blacklistService;
    }

    public function login(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $data = $request->getParsedBody();

        $username = $data['username'] ?? '';
        $password = $data['password'] ?? '';

        if (empty($username) || empty($password)) {
            return $this->jsonResponse($response, [
                'error' => 'Username and password are required'
            ], 400);
        }

        $user = User::where('username', $username)
            ->orWhere('email', $username)
            ->first();

        if (!$user) {
            return $this->jsonResponse($response, [
                'error' => 'Invalid credentials'
            ], 401);
        }

        // User found, proceed with password verification

        // Check if account is locked
        if ($user->locked_until && strtotime($user->locked_until) > time()) {
            $remainingMinutes = ceil((strtotime($user->locked_until) - time()) / 60);
            return $this->jsonResponse($response, [
                'error' => "Account locked due to too many failed attempts. Try again in {$remainingMinutes} minutes.",
                'locked_until' => $user->locked_until
            ], 403);
        }

        if (!$user->verifyPassword($password)) {
            // Increment failed attempts
            $failedAttempts = ($user->failed_login_attempts ?? 0) + 1;
            $user->failed_login_attempts = $failedAttempts;

            // Lock account after 5 failed attempts for 30 minutes
            if ($failedAttempts >= 5) {
                $user->locked_until = date('Y-m-d H:i:s', time() + 1800); // 30 minutes
                $user->save();

                return $this->jsonResponse($response, [
                    'error' => 'Account locked due to too many failed attempts. Try again in 30 minutes.'
                ], 403);
            }

            $user->save();

            $attemptsRemaining = 5 - $failedAttempts;
            return $this->jsonResponse($response, [
                'error' => "Invalid credentials. {$attemptsRemaining} attempts remaining."
            ], 401);
        }

        // Reset failed attempts on successful login
        $user->failed_login_attempts = 0;
        $user->locked_until = null;
        $user->save();

        if (!$user->isActive()) {
            return $this->jsonResponse($response, [
                'error' => 'Account is not active'
            ], 403);
        }

        $token = $this->jwtService->generateToken($user);
        $refreshToken = $this->jwtService->generateRefreshToken($user);

        return $this->jsonResponse($response, [
            'token' => $token,
            'refresh_token' => $refreshToken,
            'expires_in' => 3600,
            'user' => [
                'id' => $user->id,
                'username' => $user->username,
                'email' => $user->email,
                'role' => $user->role,
            ],
        ]);
    }

    public function register(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $data = $request->getParsedBody();
        $username = $data['username'] ?? '';
        $email = $data['email'] ?? '';
        $password = $data['password'] ?? '';

        if (empty($username) || empty($email) || empty($password)) {
            return $this->jsonResponse($response, [
                'error' => 'Username, email, and password are required'
            ], 400);
        }

        // Password complexity validation
        $passwordError = $this->validatePasswordComplexity($password);
        if ($passwordError) {
            return $this->jsonResponse($response, ['error' => $passwordError], 400);
        }

        // Check if user exists
        if (User::where('email', $email)->exists()) {
            return $this->jsonResponse($response, [
                'error' => 'Email already exists'
            ], 409);
        }

        if (User::where('username', $username)->exists()) {
            return $this->jsonResponse($response, [
                'error' => 'Username already exists'
            ], 409);
        }

        // Create user
        $user = new User();
        $user->username = $username;
        $user->email = $email;
        $user->setPassword($password);
        $user->role = 'user';
        $user->status = 'active';
        $user->save();

        $token = $this->jwtService->generateToken($user);
        $refreshToken = $this->jwtService->generateRefreshToken($user);

        return $this->jsonResponse($response, [
            'message' => 'User registered successfully',
            'token' => $token,
            'refresh_token' => $refreshToken,
            'user' => [
                'id' => $user->id,
                'username' => $user->username,
                'email' => $user->email,
                'role' => $user->role,
            ],
        ], 201);
    }

    public function refresh(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $data = $request->getParsedBody();
        $refreshToken = $data['refresh_token'] ?? '';

        if (empty($refreshToken)) {
            return $this->jsonResponse($response, [
                'error' => 'Refresh token is required'
            ], 400);
        }

        $decoded = $this->jwtService->verifyToken($refreshToken);

        if (!$decoded || ($decoded->type ?? '') !== 'refresh') {
            return $this->jsonResponse($response, [
                'error' => 'Invalid refresh token'
            ], 401);
        }

        $user = User::find($decoded->sub);

        if (!$user || !$user->isActive()) {
            return $this->jsonResponse($response, [
                'error' => 'User not found or inactive'
            ], 401);
        }

        $token = $this->jwtService->generateToken($user);

        return $this->jsonResponse($response, [
            'token' => $token,
            'expires_in' => 3600,
        ]);
    }

    public function logout(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $token = $request->getAttribute('token_string');
        $decoded = $request->getAttribute('token_decoded');

        if ($token && $decoded && isset($decoded->exp)) {
            $expiresIn = $decoded->exp - time();
            if ($expiresIn > 0) {
                $this->blacklistService->blacklist($token, $expiresIn);
            }
        }

        return $this->jsonResponse($response, [
            'message' => 'Logged out successfully'
        ]);
    }

    public function me(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $user = $request->getAttribute('user');
        $authHeader = $request->getHeaderLine('Authorization');
        $token = null;
        if (preg_match('/Bearer\s+(.*)$/i', $authHeader, $matches)) {
            $token = $matches[1];
        }

        $responseData = [
            'user' => [
                'id' => $user->id,
                'username' => $user->username,
                'email' => $user->email,
                'role' => $user->role,
                'status' => $user->status,
                'created_at' => $user->created_at->toIso8601String(),
            ],
        ];

        // Check if current token is an impersonation token
        if ($token) {
            try {
                $decoded = $this->jwtService->verifyToken($token);
                if (isset($decoded->type) && $decoded->type === 'impersonation') {
                    // One-Click Login redemption: issue a fresh standard token
                    $newToken = $this->jwtService->generateToken($user);
                    $responseData['new_token'] = $newToken;
                }
            } catch (\Exception $e) {
                // Ignore errors here, just don't issue a new token
            }
        }

        return $this->jsonResponse($response, $responseData);
    }

    public function getSettings(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $configFile = __DIR__ . '/../../../../config/settings.json';
        $settings = [];
        if (file_exists($configFile)) {
            $settings = json_decode(file_get_contents($configFile), true) ?? [];
        }

        // Return only safe/public settings
        $public = [
            'company_name' => $settings['company_name'] ?? 'LogicPanel',
            'shared_domain' => $settings['shared_domain'] ?? '',
            'hostname' => $settings['hostname'] ?? '',
        ];

        return $this->jsonResponse($response, $public);
    }

    public function updateProfile(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $user = $request->getAttribute('user');
        $data = $request->getParsedBody();

        $username = $data['username'] ?? $user->username;
        $email = $data['email'] ?? $user->email;
        $password = $data['password'] ?? '';

        // Check if email taken by another user
        if ($email !== $user->email && User::where('email', $email)->exists()) {
            return $this->jsonResponse($response, ['error' => 'Email already exists'], 409);
        }

        // Check if username taken by another user
        if ($username !== $user->username && User::where('username', $username)->exists()) {
            return $this->jsonResponse($response, ['error' => 'Username already exists'], 409);
        }

        $user->username = $username;
        $user->email = $email;

        if (!empty($password)) {
            $user->setPassword($password);
        }

        $user->save();

        return $this->jsonResponse($response, [
            'message' => 'Profile updated successfully',
            'user' => [
                'id' => $user->id,
                'username' => $user->username,
                'email' => $user->email,
                'role' => $user->role,
            ]
        ]);
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

    private function jsonResponse(ResponseInterface $response, array $data, int $status = 200): ResponseInterface
    {
        $response->getBody()->write(json_encode($data));
        return $response
            ->withStatus($status)
            ->withHeader('Content-Type', 'application/json');
    }

    // WHMCS Integration methods (placeholder)
    public function createAccount(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        return $this->jsonResponse($response, ['message' => 'WHMCS create account - To be implemented']);
    }

    public function suspendAccount(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        return $this->jsonResponse($response, ['message' => 'WHMCS suspend account - To be implemented']);
    }

    public function terminateAccount(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        return $this->jsonResponse($response, ['message' => 'WHMCS terminate account - To be implemented']);
    }

    public function changePassword(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $user = $request->getAttribute('user');
        $data = $request->getParsedBody();

        $currentPassword = $data['current_password'] ?? '';
        $newPassword = $data['new_password'] ?? '';
        $confirmPassword = $data['new_password_confirmation'] ?? '';

        if (empty($currentPassword) || empty($newPassword)) {
            return $this->jsonResponse($response, ['error' => 'Current and new passwords are required'], 400);
        }

        if ($newPassword !== $confirmPassword) {
            return $this->jsonResponse($response, ['error' => 'New passwords do not match'], 400);
        }

        if (!$user->verifyPassword($currentPassword)) {
            return $this->jsonResponse($response, ['error' => 'Incorrect current password'], 401);
        }

        $user->setPassword($newPassword);
        $user->save();

        return $this->jsonResponse($response, [
            'message' => 'Password updated successfully'
        ]);
    }

    public function accountStatus(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        return $this->jsonResponse($response, ['message' => 'WHMCS account status - To be implemented']);
    }
}
