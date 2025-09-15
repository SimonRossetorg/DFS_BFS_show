<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Http\JsonResponse;
use App\Http\Request;
use App\Http\StreamResponse;
use App\Services\GraphTraversalService;
use InvalidArgumentException;

final class GraphController
{
    private GraphTraversalService $service;

    public function __construct(GraphTraversalService $service)
    {
        $this->service = $service;
    }

    public function dfs(Request $request): JsonResponse
    {
        return $this->handleTraversal($request, 'depthFirstSearch');
    }

    public function bfs(Request $request): JsonResponse
    {
        return $this->handleTraversal($request, 'breadthFirstSearch');
    }

    /**
     * @return JsonResponse|StreamResponse
     */
    public function dfsStream(Request $request)
    {
        return $this->handleStreamTraversal($request, 'streamDepthFirstSearch');
    }

    /**
     * @return JsonResponse|StreamResponse
     */
    public function bfsStream(Request $request)
    {
        return $this->handleStreamTraversal($request, 'streamBreadthFirstSearch');
    }

    /**
     * @param 'depthFirstSearch'|'breadthFirstSearch' $method
     */
    private function handleTraversal(Request $request, string $method): JsonResponse
    {
        $input = $this->extractTraversalInput($request);
        if ($input instanceof JsonResponse) {
            return $input;
        }

        ['graph' => $graph, 'start' => $start, 'target' => $target] = $input;

        try {
            $result = $this->service->{$method}($graph, $start, $target);
        } catch (InvalidArgumentException $exception) {
            return JsonResponse::error($exception->getMessage(), 400);
        }

        return new JsonResponse($result, 200);
    }

    /**
     * @param 'streamDepthFirstSearch'|'streamBreadthFirstSearch' $method
     * @return JsonResponse|StreamResponse
     */
    private function handleStreamTraversal(Request $request, string $method)
    {
        $input = $this->extractTraversalInput($request);
        if ($input instanceof JsonResponse) {
            return $input;
        }

        ['graph' => $graph, 'start' => $start, 'target' => $target] = $input;
        $algorithm = $method === 'streamDepthFirstSearch' ? 'dfs' : 'bfs';
        $service = $this->service;

        return new StreamResponse(function (StreamResponse $stream) use ($graph, $start, $target, $method, $algorithm, $service): void {
            try {
                $execution = $service->{$method}(
                    $graph,
                    $start,
                    $target,
                    static function (array $step) use ($stream): void {
                        $stream->sendEvent('step', $step);
                    }
                );

                $stream->sendEvent('complete', array_merge(
                    $execution['result'],
                    ['steps' => $execution['steps']]
                ));
            } catch (InvalidArgumentException $exception) {
                $stream->sendEvent('error', [
                    'message' => $exception->getMessage(),
                    'algorithm' => $algorithm,
                ]);
            }
        });
    }

    /**
     * @return JsonResponse|array{graph: array<string, mixed>, start: string, target: string}
     */
    private function extractTraversalInput(Request $request)
    {
        if ($request->hasJsonError()) {
            return JsonResponse::error('Invalid JSON body: ' . $request->getJsonError(), 400);
        }

        $payload = $request->getJsonBody();
        $graph = $payload['graph'] ?? null;
        $start = $payload['start'] ?? null;
        $target = $payload['target'] ?? null;

        if (!is_array($graph) || !is_string($start) || !is_string($target)) {
            return JsonResponse::error(
                'Request body must contain "graph" (object) and "start"/"target" (strings).',
                400
            );
        }

        /** @var array<string, mixed> $graph */
        return [
            'graph' => $graph,
            'start' => $start,
            'target' => $target,
        ];
    }
}
