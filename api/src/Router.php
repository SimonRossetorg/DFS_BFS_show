<?php

declare(strict_types=1);

namespace App;

use App\Http\JsonResponse;
use App\Http\Request;
use App\Http\ResponseInterface;
use RuntimeException;

final class Router
{
    /**
     * @var array<string, array<string, callable>>
     */
    private array $routes = [];

    public function addRoute(string $method, string $path, callable $handler): void
    {
        $method = strtoupper($method);
        $normalisedPath = $this->normalisePath($path);

        $this->routes[$method][$normalisedPath] = $handler;
    }

    public function dispatch(Request $request): void
    {
        $method = strtoupper($request->getMethod());
        $path = $this->normalisePath($request->getPath());

        $handler = $this->routes[$method][$path] ?? null;

        if ($handler === null) {
            JsonResponse::error('Route not found.', 404)->send();
            return;
        }

        $response = $handler($request);

        if ($response instanceof ResponseInterface) {
            $response->send();
            return;
        }

        if ($response === null) {
            return;
        }

        throw new RuntimeException(
            'Route handlers must return an instance of ' . JsonResponse::class . ' or null.'
        );
    }

    private function normalisePath(string $path): string
    {
        if ($path === '' || $path === '/') {
            return '/';
        }

        $normalised = '/' . ltrim($path, '/');
        if (substr($normalised, -1) === '/') {
            $normalised = rtrim($normalised, '/');
        }

        return $normalised;
    }
}
