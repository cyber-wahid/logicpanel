<?php

declare(strict_types=1);

namespace LogicPanel\Application\Middleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Slim\Psr7\Response;

class RateLimitMiddleware implements MiddlewareInterface
{
    private int $limit = 60; // requests
    private int $window = 60; // seconds
    private string $storagePath;

    public function __construct()
    {
        $this->storagePath = dirname(__DIR__, 2) . '/storage/framework/ratelimit';
        if (!is_dir($this->storagePath)) {
            @mkdir($this->storagePath, 0777, true);
        }
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
        $key = md5($ip);
        $file = $this->storagePath . '/' . $key;

        $now = time();
        $requests = [];

        if (file_exists($file)) {
            $data = json_decode(file_get_contents($file), true) ?? [];
            // Filter out old requests
            $requests = array_filter($data, fn($t) => $t > ($now - $this->window));
        }

        if (count($requests) >= $this->limit) {
            $response = new Response();
            $response->getBody()->write(json_encode([
                'error' => 'Rate limit exceeded. Please try again later.',
                'retry_after' => $this->window - ($now - min($requests))
            ]));
            return $response
                ->withStatus(429)
                ->withHeader('Content-Type', 'application/json');
        }

        $requests[] = $now;
        file_put_contents($file, json_encode(array_values($requests)));

        return $handler->handle($request);
    }
}
