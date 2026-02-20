<?php

declare(strict_types=1);

namespace LogicPanel\Application\Middleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use LogicPanel\Domain\User\ApiKey;

class ApiTokenMiddleware implements MiddlewareInterface
{
    public function process(
        ServerRequestInterface $request,
        RequestHandlerInterface $handler
    ): ResponseInterface {
        // Get API token from header
        $authHeader = $request->getHeaderLine('Authorization');
        $apiKey = null;

        if (preg_match('/Bearer\s+(.*)$/i', $authHeader, $matches)) {
            $apiKey = $matches[1];
        } elseif ($request->hasHeader('X-API-Key')) {
            $apiKey = $request->getHeaderLine('X-API-Key');
        }

        if (!$apiKey) {
            return $this->unauthorizedResponse('API key required');
        }

        // Validate API key
        $key = ApiKey::where('key', $apiKey)
            ->where('status', 'active')
            ->first();

        if (!$key) {
            return $this->unauthorizedResponse('Invalid API key');
        }

        // Check expiration
        if ($key->expires_at && $key->expires_at < date('Y-m-d H:i:s')) {
            return $this->unauthorizedResponse('API key expired');
        }

        // Update last used
        $key->last_used_at = date('Y-m-d H:i:s');
        $key->save();

        // Add user info to request
        $request = $request->withAttribute('api_key_id', $key->id);
        $request = $request->withAttribute('user_id', $key->user_id);
        $request = $request->withAttribute('api_user', $key->user);

        return $handler->handle($request);
    }

    private function unauthorizedResponse(string $message): ResponseInterface
    {
        $response = new \Slim\Psr7\Response();
        $response->getBody()->write(json_encode([
            'error' => 'Unauthorized',
            'message' => $message
        ]));
        
        return $response
            ->withStatus(401)
            ->withHeader('Content-Type', 'application/json');
    }
}
