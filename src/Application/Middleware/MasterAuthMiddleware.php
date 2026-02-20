<?php

declare(strict_types=1);

namespace LogicPanel\Application\Middleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Slim\Psr7\Response;

class MasterAuthMiddleware implements MiddlewareInterface
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        // The previous AuthMiddleware already validated the token and added 'user' attribute
        $user = $request->getAttribute('user');

        if (!$user) {
            $response = new Response();
            $response->getBody()->write(json_encode(['error' => 'Unauthorized']));
            return $response->withStatus(401)->withHeader('Content-Type', 'application/json');
        }

        if ($user->role !== 'admin' && $user->role !== 'root' && $user->role !== 'reseller') {
            $response = new Response();
            $response->getBody()->write(json_encode(['error' => 'Forbidden: Master Panel Access Required']));
            return $response->withStatus(403)->withHeader('Content-Type', 'application/json');
        }

        return $handler->handle($request);
    }
}
