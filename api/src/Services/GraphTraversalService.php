<?php

declare(strict_types=1);

namespace App\Services;

use InvalidArgumentException;

final class GraphTraversalService
{
    /**
     * @param array<string, array<int, string>> $graph
     * @return array{path: array<int, string>, visited: array<int, string>, algorithm: string, found: bool}
     */
    public function depthFirstSearch(array $graph, string $start, string $target): array
    {
        $execution = $this->executeDepthFirstSearch($graph, $start, $target, null);

        return $execution['result'];
    }

    /**
     * @param array<string, array<int, string>> $graph
     * @param callable(array<string, mixed>): void $onStep
     * @return array{
     *     result: array{path: array<int, string>, visited: array<int, string>, algorithm: string, found: bool},
     *     steps: int
     * }
     */
    public function streamDepthFirstSearch(array $graph, string $start, string $target, callable $onStep): array
    {
        return $this->executeDepthFirstSearch($graph, $start, $target, $onStep);
    }

    /**
     * @param array<string, array<int, string>> $graph
     * @return array{path: array<int, string>, visited: array<int, string>, algorithm: string, found: bool}
     */
    public function breadthFirstSearch(array $graph, string $start, string $target): array
    {
        $execution = $this->executeBreadthFirstSearch($graph, $start, $target, null);

        return $execution['result'];
    }

    /**
     * @param array<string, array<int, string>> $graph
     * @param callable(array<string, mixed>): void $onStep
     * @return array{
     *     result: array{path: array<int, string>, visited: array<int, string>, algorithm: string, found: bool},
     *     steps: int
     * }
     */
    public function streamBreadthFirstSearch(array $graph, string $start, string $target, callable $onStep): array
    {
        return $this->executeBreadthFirstSearch($graph, $start, $target, $onStep);
    }

    /**
     * @param array<string, array<int, string>> $graph
     * @param callable(array<string, mixed>): void|null $onStep
     * @return array{
     *     result: array{path: array<int, string>, visited: array<int, string>, algorithm: string, found: bool},
     *     steps: int
     * }
     */
    private function executeDepthFirstSearch(
        array $graph,
        string $start,
        string $target,
        ?callable $onStep
    ): array {
        $graph = $this->normaliseGraph($graph);
        $this->assertNodeExists($graph, $start);
        $this->assertNodeExists($graph, $target);

        $visitedOrder = [];
        $visited = [];
        $stack = [[$start, [$start]]];
        $step = 0;

        while ($stack !== []) {
            [$vertex, $path] = array_pop($stack);
            $step++;

            if (isset($visited[$vertex])) {
                $this->emitStep($onStep, $this->createTraversalState(
                    'dfs',
                    $step,
                    $vertex,
                    $path,
                    $visitedOrder,
                    $stack,
                    'stack',
                    false,
                    true
                ));

                continue;
            }

            $visited[$vertex] = true;
            $visitedOrder[] = $vertex;

            $isTarget = $vertex === $target;

            if (!$isTarget) {
                $neighbours = $graph[$vertex] ?? [];
                for ($index = count($neighbours) - 1; $index >= 0; $index--) {
                    $neighbour = $neighbours[$index];
                    if (!isset($visited[$neighbour])) {
                        $stack[] = [$neighbour, array_merge($path, [$neighbour])];
                    }
                }
            }

            $this->emitStep($onStep, $this->createTraversalState(
                'dfs',
                $step,
                $vertex,
                $path,
                $visitedOrder,
                $stack,
                'stack',
                $isTarget,
                false
            ));

            if ($isTarget) {
                return [
                    'result' => [
                        'path' => $path,
                        'visited' => $visitedOrder,
                        'algorithm' => 'dfs',
                        'found' => true,
                    ],
                    'steps' => $step,
                ];
            }
        }

        return [
            'result' => [
                'path' => [],
                'visited' => $visitedOrder,
                'algorithm' => 'dfs',
                'found' => false,
            ],
            'steps' => $step,
        ];
    }

    /**
     * @param array<string, array<int, string>> $graph
     * @param callable(array<string, mixed>): void|null $onStep
     * @return array{
     *     result: array{path: array<int, string>, visited: array<int, string>, algorithm: string, found: bool},
     *     steps: int
     * }
     */
    private function executeBreadthFirstSearch(
        array $graph,
        string $start,
        string $target,
        ?callable $onStep
    ): array {
        $graph = $this->normaliseGraph($graph);
        $this->assertNodeExists($graph, $start);
        $this->assertNodeExists($graph, $target);

        $queue = [[$start, [$start]]];
        $visitedFlags = [$start => true];
        $visitedOrder = [];
        $step = 0;

        while ($queue !== []) {
            [$vertex, $path] = array_shift($queue);
            $step++;

            $visitedOrder[] = $vertex;
            $isTarget = $vertex === $target;

            if (!$isTarget) {
                foreach ($graph[$vertex] ?? [] as $neighbour) {
                    if (!isset($visitedFlags[$neighbour])) {
                        $visitedFlags[$neighbour] = true;
                        $queue[] = [$neighbour, array_merge($path, [$neighbour])];
                    }
                }
            }

            $this->emitStep($onStep, $this->createTraversalState(
                'bfs',
                $step,
                $vertex,
                $path,
                $visitedOrder,
                $queue,
                'queue',
                $isTarget,
                false
            ));

            if ($isTarget) {
                return [
                    'result' => [
                        'path' => $path,
                        'visited' => $visitedOrder,
                        'algorithm' => 'bfs',
                        'found' => true,
                    ],
                    'steps' => $step,
                ];
            }
        }

        return [
            'result' => [
                'path' => [],
                'visited' => $visitedOrder,
                'algorithm' => 'bfs',
                'found' => false,
            ],
            'steps' => $step,
        ];
    }

    /**
     * @param array<string, array<int, string>> $graph
     * @return array<string, array<int, string>>
     */
    private function normaliseGraph(array $graph): array
    {
        if ($graph === []) {
            throw new InvalidArgumentException('Graph must contain at least one node.');
        }

        $normalised = [];

        foreach ($graph as $vertex => $neighbours) {
            if (!is_string($vertex) || $vertex === '') {
                throw new InvalidArgumentException('Graph keys must be non-empty strings.');
            }

            if (!is_array($neighbours)) {
                throw new InvalidArgumentException(sprintf(
                    'Adjacency list for node "%s" must be an array.',
                    $vertex
                ));
            }

            $normalised[$vertex] = [];

            foreach ($neighbours as $neighbour) {
                if (!is_string($neighbour) || $neighbour === '') {
                    throw new InvalidArgumentException(sprintf(
                        'Node "%s" has an invalid neighbour. All neighbours must be non-empty strings.',
                        $vertex
                    ));
                }

                $normalised[$vertex][] = $neighbour;
            }
        }

        foreach ($normalised as $vertex => $neighbours) {
            foreach ($neighbours as $neighbour) {
                if (!array_key_exists($neighbour, $normalised)) {
                    $normalised[$neighbour] = [];
                }
            }
        }

        return $normalised;
    }

    /**
     * @param array<string, array<int, string>> $graph
     */
    private function assertNodeExists(array $graph, string $vertex): void
    {
        if (!array_key_exists($vertex, $graph)) {
            throw new InvalidArgumentException(sprintf('Node "%s" is not present in the graph.', $vertex));
        }
    }

    /**
     * @param callable(array<string, mixed>): void|null $onStep
     * @param array<string, mixed> $state
     */
    private function emitStep(?callable $onStep, array $state): void
    {
        if ($onStep !== null) {
            $onStep($state);
        }
    }

    /**
     * @param array<int, string> $path
     * @param array<int, string> $visitedOrder
     * @param array<int, array{0: string, 1: array<int, string>}> $frontier
     * @param 'stack'|'queue' $frontierType
     * @return array{
     *     algorithm: string,
     *     step: int,
     *     current: string,
     *     path: array<int, string>,
     *     visited: array<int, string>,
     *     frontierType: 'stack'|'queue',
     *     frontier: array<int, array{node: string, path: array<int, string>}>,
     *     found: bool,
     *     skipped: bool
     * }
     */
    private function createTraversalState(
        string $algorithm,
        int $step,
        string $current,
        array $path,
        array $visitedOrder,
        array $frontier,
        string $frontierType,
        bool $found,
        bool $skipped
    ): array {
        return [
            'algorithm' => $algorithm,
            'step' => $step,
            'current' => $current,
            'path' => $path,
            'visited' => $visitedOrder,
            'frontierType' => $frontierType,
            'frontier' => array_map(
                static function (array $entry): array {
                    return [
                        'node' => $entry[0],
                        'path' => $entry[1],
                    ];
                },
                array_values($frontier)
            ),
            'found' => $found,
            'skipped' => $skipped,
        ];
    }
}
