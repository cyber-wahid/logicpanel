<?php

declare(strict_types=1);

namespace LogicPanel\Application\Middleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use LogicPanel\Domain\User\ApiKey;
use LogicPanel\Domain\User\User;

class ApiTokenMiddleware implements MiddlewareInterface
{
    public function process(
        ServerRequestInterface $request,
        RequestHandlerInterface $handler
    ): ResponseInterface {
        // Get API token from header (supports both Bearer and X-API-Key)
        $authHeader = $request->getHeaderLine('Authorization');
        $apiToken = null;

        if (preg_match('/Bearer\s+(.*)$/i', $authHeader, $matches)) {
            $apiToken = $matches[1];
        } elseif ($request->hasHeader('X-API-Key')) {
            $apiToken = $request->getHeaderLine('X-API-Key');
        }

        if (!$apiToken) {
            return $this->unauthorizedResponse('API key required. Use X-API-Key header or Bearer token.');
        }

        // Validate API key — use correct column name 'api_key'
        $key = ApiKey::where('api_key', $apiToken)
            ->where('status', 'active')
            ->first();

        if (!$key) {
            return $this->unauthorizedResponse('Invalid API key');
        }

        // Check expiration
        if ($key->expires_at && $key->expires_at < date('Y-m-d H:i:s')) {
            return $this->unauthorizedResponse('API key expired');
        }

        // Load the associated user — controllers read $request->getAttribute('user')
        $user = User::find($key->user_id);
        if (!$user) {
            return $this->unauthorizedResponse('API key owner not found');
        }

        // Block suspended/terminated users from using API
        if ($user->status !== 'active') {
            return $this->unauthorizedResponse('Account is ' . $user->status . '. API access denied.');
        }

        // Track usage
        $key->last_used_at = date('Y-m-d H:i:s');
        $key->last_used_ip = $_SERVER['REMOTE_ADDR'] ?? null;
        $key->usage_count = ($key->usage_count ?? 0) + 1;
        $key->save();

        // Set request attributes — 'user' is what all controllers expect
        $request = $request->withAttribute('user', $user);
        $request = $request->withAttribute('api_key_id', $key->id);
        $request = $request->withAttribute('user_id', $key->user_id);

        return $handler->handle($request);
    }

    private function unauthorizedResponse(string $message): ResponseInterface
    {
        $response = new \Slim\Psr7\Response();
        $response->getBody()->write(json_encode([
            'result' => 'error',
            'error' => 'Unauthorized',
            'message' => $message
        ]));
        
        return $response
            ->withStatus(401)
            ->withHeader('Content-Type', 'application/json');
    }
}
