<?php

declare(strict_types=1);

namespace LogicPanel\Application\Controllers\Master;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use LogicPanel\Domain\User\ApiKey;
use LogicPanel\Domain\User\User;

class ApiKeyController
{
    /**
     * List all API Keys (optionally filter by user)
     */
    public function index(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $currentUser = $request->getAttribute('user');
        
        $query = ApiKey::with('user')->orderBy('created_at', 'desc');
        
        // Resellers can only see their own API keys
        if ($currentUser && $currentUser->role === 'reseller') {
            $query->where('user_id', $currentUser->id);
        }
        
        $keys = $query->get();

        return $this->jsonResponse($response, ['keys' => $keys]);
    }

    /**
     * Create a new API Key for a user
     */
    public function create(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $data = $request->getParsedBody();
        $name = $data['name'] ?? 'API Key';
        $currentUser = $request->getAttribute('user');

        // Determine user_id
        // Resellers can only create keys for themselves
        // Admins can create keys for any user (if user_id provided) or themselves
        if ($currentUser->role === 'reseller') {
            $userId = $currentUser->id;
        } else {
            // Admin can specify user_id or default to themselves
            $userId = !empty($data['user_id']) ? $data['user_id'] : $currentUser->id;
        }

        $user = User::find($userId);
        if (!$user) {
            return $this->jsonResponse($response, ['error' => 'User not found'], 404);
        }

        try {
            // Generate Key: lp_ + 48 chars (24 bytes in hex)
            $keyString = 'lp_' . bin2hex(random_bytes(24));

            $apiKey = new ApiKey();
            $apiKey->user_id = $userId;
            $apiKey->name = $name;
            $apiKey->api_key = $keyString;  // Store the actual key
            $apiKey->key_hash = hash('sha256', $keyString); // Hash for secure lookup
            $apiKey->permissions = json_encode(['*']); // Full access for now
            $apiKey->status = 'active';
            $apiKey->save();

            return $this->jsonResponse($response, [
                'result' => 'success',
                'message' => 'API Key created successfully',
                'api_key' => $keyString, // Return the key ONCE (user must save it)
                'key_id' => $apiKey->id,
                'name' => $apiKey->name
            ], 201);
        } catch (\Exception $e) {
            // Log the error
            error_log("API Key Creation Error: " . $e->getMessage());
            return $this->jsonResponse($response, [
                'result' => 'error',
                'message' => 'Failed to save API key: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete an API Key
     */
    public function delete(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $id = $args['id'];
        $currentUser = $request->getAttribute('user');
        
        $key = ApiKey::find($id);

        if (!$key) {
            return $this->jsonResponse($response, ['error' => 'Key not found'], 404);
        }

        // Resellers can only delete their own keys
        if ($currentUser->role === 'reseller' && $key->user_id !== $currentUser->id) {
            return $this->jsonResponse($response, ['error' => 'Permission denied'], 403);
        }

        $key->delete();

        return $this->jsonResponse($response, ['result' => 'success', 'message' => 'API Key deleted successfully']);
    }

    private function jsonResponse(ResponseInterface $response, array $data, int $status = 200): ResponseInterface
    {
        $response->getBody()->write(json_encode($data));
        return $response
            ->withStatus($status)
            ->withHeader('Content-Type', 'application/json');
    }
}
