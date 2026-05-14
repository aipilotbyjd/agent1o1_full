<?php

namespace App\Engine\Graph;

use App\Enums\NodeType;
use App\Exceptions\CycleDetectedException;

/**
 * Compiles and represents an immutable workflow graph.
 *
 * Call WorkflowGraph::compile() to build from raw nodes/edges.
 * The resulting instance is cached per workflow_version_id.
 */
class WorkflowGraph
{
    /**
     * @param  array<string, array<string, mixed>>  $nodeMap
     * @param  array<string, list<string>>  $successors
     * @param  array<string, list<string>>  $predecessors
     * @param  array<string, int>  $inDegree
     * @param  list<string>  $startNodes
     * @param  array<string, array<string, mixed>>  $compiledExpressions
     * @param  array<string, list<string>>  $downstreamConsumers
     * @param  array<string, list<array<string, string>>>  $edgeMap
     */
    public function __construct(
        public readonly array $nodeMap,
        public readonly array $successors,
        public readonly array $predecessors,
        public readonly array $inDegree,
        public readonly array $startNodes,
        public readonly array $compiledExpressions,
        public readonly array $downstreamConsumers,
        public readonly array $edgeMap,
    ) {}

    /**
     * Compile raw nodes and edges into a WorkflowGraph.
     *
     * @param  list<array<string, mixed>>  $nodes
     * @param  list<array<string, mixed>>  $edges
     *
     * @throws CycleDetectedException
     */
    public static function compile(array $nodes, array $edges): self
    {
        $resolver = new ExpressionResolver;

        $nodeMap = self::buildNodeMap($nodes);
        [$successors, $predecessors, $edgeMap] = self::buildAdjacencyLists($nodeMap, $edges);
        $inDegree = self::computeInDegree($nodeMap, $predecessors);

        self::detectCycles($nodeMap, $successors, $inDegree);

        $startNodes = self::findStartNodes($inDegree);
        $compiledExpressions = self::compileExpressions($nodeMap, $resolver);
        $downstreamConsumers = self::buildDownstreamConsumers($nodeMap, $successors);

        return new self(
            nodeMap: $nodeMap,
            successors: $successors,
            predecessors: $predecessors,
            inDegree: $inDegree,
            startNodes: $startNodes,
            compiledExpressions: $compiledExpressions,
            downstreamConsumers: $downstreamConsumers,
            edgeMap: $edgeMap,
        );
    }

    // ── Query methods ─────────────────────────────────────────────────────

    public function getNode(string $nodeId): ?array
    {
        return $this->nodeMap[$nodeId] ?? null;
    }

    public function getNodeType(string $nodeId): ?NodeType
    {
        $node = $this->getNode($nodeId);

        return $node ? NodeType::resolve($node['type'] ?? '') : null;
    }

    public function getSuccessors(string $nodeId): array
    {
        return $this->successors[$nodeId] ?? [];
    }

    public function getPredecessors(string $nodeId): array
    {
        return $this->predecessors[$nodeId] ?? [];
    }

    public function getCompiledConfig(string $nodeId): array
    {
        return $this->compiledExpressions[$nodeId] ?? [];
    }

    public function getEdgesFrom(string $nodeId, ?string $sourceHandle = null): array
    {
        $edges = $this->edgeMap[$nodeId] ?? [];

        if ($sourceHandle === null) {
            return $edges;
        }

        return array_values(array_filter(
            $edges,
            fn (array $edge) => ($edge['sourceHandle'] ?? 'output') === $sourceHandle,
        ));
    }

    public function nodeCount(): int
    {
        return count($this->nodeMap);
    }

    // ── Compilation helpers ───────────────────────────────────────────────

    private static function buildNodeMap(array $nodes): array
    {
        $map = [];

        foreach ($nodes as $node) {
            $id = $node['id'] ?? null;
            if ($id !== null) {
                $map[$id] = $node;
            }
        }

        return $map;
    }

    private static function buildAdjacencyLists(array $nodeMap, array $edges): array
    {
        $successors = [];
        $predecessors = [];
        $edgeMap = [];

        foreach ($nodeMap as $id => $node) {
            $successors[$id] = [];
            $predecessors[$id] = [];
            $edgeMap[$id] = [];
        }

        foreach ($edges as $edge) {
            $source = $edge['source'] ?? null;
            $target = $edge['target'] ?? null;

            if ($source === null || $target === null) {
                continue;
            }

            if (! isset($nodeMap[$source]) || ! isset($nodeMap[$target])) {
                continue;
            }

            $successors[$source][] = $target;
            $predecessors[$target][] = $source;
            $edgeMap[$source][] = [
                'target' => $target,
                'sourceHandle' => $edge['sourceHandle'] ?? 'output',
                'targetHandle' => $edge['targetHandle'] ?? 'input',
            ];
        }

        foreach ($successors as $id => $list) {
            $successors[$id] = array_values(array_unique($list));
        }

        foreach ($predecessors as $id => $list) {
            $predecessors[$id] = array_values(array_unique($list));
        }

        return [$successors, $predecessors, $edgeMap];
    }

    private static function computeInDegree(array $nodeMap, array $predecessors): array
    {
        $inDegree = [];

        foreach ($nodeMap as $id => $node) {
            $inDegree[$id] = count($predecessors[$id] ?? []);
        }

        return $inDegree;
    }

    private static function detectCycles(array $nodeMap, array $successors, array $inDegree): void
    {
        $remaining = $inDegree;
        $queue = [];

        foreach ($remaining as $id => $degree) {
            if ($degree === 0) {
                $queue[] = $id;
            }
        }

        $processed = 0;

        while (! empty($queue)) {
            $current = array_shift($queue);
            $processed++;

            foreach ($successors[$current] ?? [] as $successor) {
                $remaining[$successor]--;
                if ($remaining[$successor] === 0) {
                    $queue[] = $successor;
                }
            }
        }

        if ($processed < count($nodeMap)) {
            $cycleNodes = array_keys(array_filter($remaining, fn ($d) => $d > 0));
            throw new CycleDetectedException($cycleNodes);
        }
    }

    private static function findStartNodes(array $inDegree): array
    {
        return array_values(array_keys(array_filter($inDegree, fn ($d) => $d === 0)));
    }

    private static function compileExpressions(array $nodeMap, ExpressionResolver $resolver): array
    {
        $compiled = [];

        foreach ($nodeMap as $id => $node) {
            $config = $node['data'] ?? $node['config'] ?? [];
            $compiled[$id] = (is_array($config) && ! empty($config))
                ? $resolver->compileConfig($config)
                : [];
        }

        return $compiled;
    }

    private static function buildDownstreamConsumers(array $nodeMap, array $successors): array
    {
        $consumers = [];

        foreach ($nodeMap as $id => $node) {
            $consumers[$id] = $successors[$id];
        }

        return $consumers;
    }
}
