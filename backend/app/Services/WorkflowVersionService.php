<?php

namespace App\Services;

use App\Exceptions\ApiException;
use App\Models\Node;
use App\Models\User;
use App\Models\Workflow;
use App\Models\WorkflowVersion;
use Illuminate\Support\Facades\Cache;

class WorkflowVersionService
{
    /**
     * Create a new version for the given workflow.
     *
     * @param  array{name?: string, description?: string, trigger_type?: string, trigger_config?: array, nodes: array, edges: array, viewport?: array, settings?: array, change_summary?: string}  $data
     */
    public function create(Workflow $workflow, User $creator, array $data): WorkflowVersion
    {
        if ($workflow->is_locked) {
            throw new ApiException('This workflow is locked and cannot be modified.', 423);
        }

        $nextVersion = (int) $workflow->versions()->max('version_number') + 1;

        if (isset($data['nodes']) && is_array($data['nodes'])) {
            $data['nodes'] = $this->stampNodeVersions($data['nodes']);
        }

        return $workflow->versions()->create([
            ...$data,
            'version_number' => $nextVersion,
            'created_by' => $creator->id,
        ]);
    }

    /**
     * Stamp the current node-type version into each node object in the nodes array.
     *
     * Adds a `_node_version` key to every node so we can later detect whether the
     * node's schema has been updated since this version was saved.
     *
     * @param  array<int, array<string, mixed>>  $nodes
     * @return array<int, array<string, mixed>>
     */
    public function stampNodeVersions(array $nodes): array
    {
        $typeVersionMap = $this->loadNodeTypeVersionMap();

        return array_map(function (array $node) use ($typeVersionMap) {
            $type = $node['type'] ?? null;
            if ($type !== null) {
                $node['_node_version'] = $typeVersionMap[$type] ?? 1;
            }

            return $node;
        }, $nodes);
    }

    /**
     * Return nodes whose stored _node_version is behind the current node type version.
     *
     * Useful for the UI to warn users that a node has changed since this version was saved.
     *
     * @return array<int, array{id: string, type: string, stored_version: int, current_version: int}>
     */
    public function getOutdatedNodes(WorkflowVersion $version): array
    {
        $nodes = $version->nodes ?? [];
        if (empty($nodes)) {
            return [];
        }

        $typeVersionMap = $this->loadNodeTypeVersionMap();
        $outdated = [];

        foreach ($nodes as $node) {
            $type = $node['type'] ?? null;
            $storedVersion = (int) ($node['_node_version'] ?? 0);
            $currentVersion = $typeVersionMap[$type] ?? 1;

            if ($storedVersion > 0 && $storedVersion < $currentVersion) {
                $outdated[] = [
                    'id' => $node['id'] ?? null,
                    'type' => $type,
                    'stored_version' => $storedVersion,
                    'current_version' => $currentVersion,
                ];
            }
        }

        return $outdated;
    }

    /**
     * Load the type → version map from the nodes table, cached for 5 minutes.
     *
     * @return array<string, int>
     */
    private function loadNodeTypeVersionMap(): array
    {
        return Cache::remember('node_type_version_map', 300, function () {
            return Node::query()
                ->select(['type', 'version'])
                ->pluck('version', 'type')
                ->map(fn ($v) => (int) $v)
                ->all();
        });
    }

    /**
     * Publish a version, making it the current active version of the workflow.
     */
    public function publish(WorkflowVersion $version): WorkflowVersion
    {
        $workflow = $version->workflow;

        if ($workflow->is_locked) {
            throw new ApiException('This workflow is locked and cannot be modified.', 423);
        }

        if ($version->is_published) {
            throw ApiException::conflict('This version is already published.');
        }

        $version->update([
            'is_published' => true,
            'published_at' => now(),
        ]);

        $workflow->update(['current_version_id' => $version->id]);

        return $version->refresh();
    }

    /**
     * Rollback by cloning a previous version as a new version and publishing it.
     */
    public function rollback(Workflow $workflow, WorkflowVersion $version): WorkflowVersion
    {
        if ($workflow->is_locked) {
            throw new ApiException('This workflow is locked and cannot be modified.', 423);
        }

        $nextVersion = (int) $workflow->versions()->max('version_number') + 1;

        $newVersion = $workflow->versions()->create([
            'version_number' => $nextVersion,
            'name' => $version->name,
            'description' => $version->description,
            'trigger_type' => $version->trigger_type,
            'trigger_config' => $version->trigger_config,
            'nodes' => $version->nodes,
            'edges' => $version->edges,
            'viewport' => $version->viewport,
            'settings' => $version->settings,
            'created_by' => auth()->id(),
            'change_summary' => "Rolled back to version {$version->version_number}",
            'is_published' => true,
            'published_at' => now(),
        ]);

        $workflow->update(['current_version_id' => $newVersion->id]);

        return $newVersion;
    }

    /**
     * Compute a diff summary between two versions.
     *
     * @return array{added: array, removed: array, modified: array}
     */
    public function diff(WorkflowVersion $from, WorkflowVersion $to): array
    {
        $fromNodes = collect($from->nodes)->keyBy('id');
        $toNodes = collect($to->nodes)->keyBy('id');

        $added = $toNodes->diffKeys($fromNodes)->values()->map(fn ($n) => [
            'id' => $n['id'],
            'type' => $n['type'] ?? null,
        ])->all();

        $removed = $fromNodes->diffKeys($toNodes)->values()->map(fn ($n) => [
            'id' => $n['id'],
            'type' => $n['type'] ?? null,
        ])->all();

        $modified = [];
        foreach ($toNodes->intersectByKeys($fromNodes) as $id => $toNode) {
            $fromNode = $fromNodes[$id];
            if ($toNode !== $fromNode) {
                $modified[] = [
                    'id' => $id,
                    'type' => $toNode['type'] ?? null,
                ];
            }
        }

        return [
            'from_version' => $from->version_number,
            'to_version' => $to->version_number,
            'added' => $added,
            'removed' => $removed,
            'modified' => $modified,
        ];
    }
}
