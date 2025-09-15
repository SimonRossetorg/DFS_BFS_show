<?php

declare(strict_types=1);

use App\Controllers\GraphController;
use App\Controllers\HealthController;
use App\Http\Request;
use App\Router;
use App\Services\GraphTraversalService;

require __DIR__ . '/../src/bootstrap.php';

$request = Request::fromGlobals();

$router = new Router();
$graphService = new GraphTraversalService();
$graphController = new GraphController($graphService);
$healthController = new HealthController();

$router->addRoute('GET', '/health', [$healthController, 'status']);
$router->addRoute('POST', '/graph/traverse/dfs', [$graphController, 'dfs']);
$router->addRoute('POST', '/graph/traverse/bfs', [$graphController, 'bfs']);
$router->addRoute('POST', '/graph/traverse/dfs/stream', [$graphController, 'dfsStream']);
$router->addRoute('POST', '/graph/traverse/bfs/stream', [$graphController, 'bfsStream']);

$router->dispatch($request);
