<?php

namespace App\Engine\Nodes\Apps\Data;

use App\Engine\Nodes\Apps\AppNode;
use App\Engine\Execution\NodePayload;
use App\Models\Variable;
use App\Models\Workspace;

/**
 * Variable Node
 * 
 * Store and retrieve variables across workflow executions.
 * Supports workflow, execution, and workspace scopes.
 */
class VariableNode extends AppNode
{
    protected function errorCode(): string
    {
        return 'VARIABLE_ERROR';
    }

    protected function operations(): array
    {
        return [
            'set' => $this->set(...),
            'get' => $this->get(...),
            'increment' => $this->increment(...),
            'decrement' => $this->decrement(...),
            'append' => $this->append(...),
            'delete' => $this->delete(...),
        ];
    }

    /**
     * Set variable value
     */
    private function set(NodePayload $payload): array
    {
        $key = $payload->config['key'] ?? '';
        $value = $payload->config['value'] ?? $payload->inputData['value'] ?? null;
        $scope = $payload->config['scope'] ?? 'workflow'; // workflow | execution | workspace
        $ttl = $payload->config['ttl'] ?? null; // Time to live in seconds

        if (empty($key)) {
            throw new \InvalidArgumentException('Variable key is required');
        }

        $workspaceId = $payload->executionMeta['workspace_id'] ?? null;
        $workflowId = $payload->executionMeta['workflow_id'] ?? null;
        $executionId = $payload->executionMeta['execution_id'] ?? null;

        // Store in database or cache based on scope
        $this->storeVariable($key, $value, $scope, $workspaceId, $workflowId, $executionId, $ttl);

        return [
            'key' => $key,
            'value' => $value,
            'scope' => $scope,
            'success' => true,
        ];
    }

    /**
     * Get variable value
     */
    private function get(NodePayload $payload): array
    {
        $key = $payload->config['key'] ?? '';
        $scope = $payload->config['scope'] ?? 'workflow';
        $defaultValue = $payload->config['default'] ?? null;

        if (empty($key)) {
            throw new \InvalidArgumentException('Variable key is required');
        }

        $workspaceId = $payload->executionMeta['workspace_id'] ?? null;
        $workflowId = $payload->executionMeta['workflow_id'] ?? null;
        $executionId = $payload->executionMeta['execution_id'] ?? null;

        $value = $this->retrieveVariable($key, $scope, $workspaceId, $workflowId, $executionId);

        if ($value === null) {
            $value = $defaultValue;
        }

        return [
            'key' => $key,
            'value' => $value,
            'scope' => $scope,
            'found' => $value !== null,
        ];
    }

    /**
     * Increment numeric variable
     */
    private function increment(NodePayload $payload): array
    {
        $key = $payload->config['key'] ?? '';
        $amount = (int) ($payload->config['amount'] ?? 1);
        $scope = $payload->config['scope'] ?? 'workflow';

        if (empty($key)) {
            throw new \InvalidArgumentException('Variable key is required');
        }

        $workspaceId = $payload->executionMeta['workspace_id'] ?? null;
        $workflowId = $payload->executionMeta['workflow_id'] ?? null;
        $executionId = $payload->executionMeta['execution_id'] ?? null;

        $currentValue = $this->retrieveVariable($key, $scope, $workspaceId, $workflowId, $executionId) ?? 0;
        $newValue = (int) $currentValue + $amount;

        $this->storeVariable($key, $newValue, $scope, $workspaceId, $workflowId, $executionId);

        return [
            'key' => $key,
            'previous_value' => $currentValue,
            'new_value' => $newValue,
            'increment' => $amount,
        ];
    }

    /**
     * Decrement numeric variable
     */
    private function decrement(NodePayload $payload): array
    {
        $key = $payload->config['key'] ?? '';
        $amount = (int) ($payload->config['amount'] ?? 1);
        $scope = $payload->config['scope'] ?? 'workflow';

        if (empty($key)) {
            throw new \InvalidArgumentException('Variable key is required');
        }

        $workspaceId = $payload->executionMeta['workspace_id'] ?? null;
        $workflowId = $payload->executionMeta['workflow_id'] ?? null;
        $executionId = $payload->executionMeta['execution_id'] ?? null;

        $currentValue = $this->retrieveVariable($key, $scope, $workspaceId, $workflowId, $executionId) ?? 0;
        $newValue = (int) $currentValue - $amount;

        $this->storeVariable($key, $newValue, $scope, $workspaceId, $workflowId, $executionId);

        return [
            'key' => $key,
            'previous_value' => $currentValue,
            'new_value' => $newValue,
            'decrement' => $amount,
        ];
    }

    /**
     * Append to array variable
     */
    private function append(NodePayload $payload): array
    {
        $key = $payload->config['key'] ?? '';
        $value = $payload->config['value'] ?? $payload->inputData['value'] ?? null;
        $scope = $payload->config['scope'] ?? 'workflow';

        if (empty($key)) {
            throw new \InvalidArgumentException('Variable key is required');
        }

        $workspaceId = $payload->executionMeta['workspace_id'] ?? null;
        $workflowId = $payload->executionMeta['workflow_id'] ?? null;
        $executionId = $payload->executionMeta['execution_id'] ?? null;

        $currentValue = $this->retrieveVariable($key, $scope, $workspaceId, $workflowId, $executionId) ?? [];

        if (! is_array($currentValue)) {
            $currentValue = [$currentValue];
        }

        $currentValue[] = $value;

        $this->storeVariable($key, $currentValue, $scope, $workspaceId, $workflowId, $executionId);

        return [
            'key' => $key,
            'appended_value' => $value,
            'array_size' => count($currentValue),
        ];
    }

    /**
     * Delete variable
     */
    private function delete(NodePayload $payload): array
    {
        $key = $payload->config['key'] ?? '';
        $scope = $payload->config['scope'] ?? 'workflow';

        if (empty($key)) {
            throw new \InvalidArgumentException('Variable key is required');
        }

        $workspaceId = $payload->executionMeta['workspace_id'] ?? null;
        $workflowId = $payload->executionMeta['workflow_id'] ?? null;
        $executionId = $payload->executionMeta['execution_id'] ?? null;

        $this->deleteVariable($key, $scope, $workspaceId, $workflowId, $executionId);

        return [
            'key' => $key,
            'scope' => $scope,
            'deleted' => true,
        ];
    }

    /**
     * Store variable (implementation depends on your storage strategy)
     */
    private function storeVariable(
        string $key,
        $value,
        string $scope,
        ?string $workspaceId,
        ?string $workflowId,
        ?string $executionId,
        ?int $ttl = null
    ): void {
        // Use cache for temporary storage
        $cacheKey = $this->buildCacheKey($key, $scope, $workspaceId, $workflowId, $executionId);

        if ($ttl) {
            cache()->put($cacheKey, $value, $ttl);
        } else {
            cache()->forever($cacheKey, $value);
        }
    }

    /**
     * Retrieve variable
     */
    private function retrieveVariable(
        string $key,
        string $scope,
        ?string $workspaceId,
        ?string $workflowId,
        ?string $executionId
    ) {
        $cacheKey = $this->buildCacheKey($key, $scope, $workspaceId, $workflowId, $executionId);

        return cache()->get($cacheKey);
    }

    /**
     * Delete variable
     */
    private function deleteVariable(
        string $key,
        string $scope,
        ?string $workspaceId,
        ?string $workflowId,
        ?string $executionId
    ): void {
        $cacheKey = $this->buildCacheKey($key, $scope, $workspaceId, $workflowId, $executionId);
        cache()->forget($cacheKey);
    }

    /**
     * Build cache key based on scope
     */
    private function buildCacheKey(
        string $key,
        string $scope,
        ?string $workspaceId,
        ?string $workflowId,
        ?string $executionId
    ): string {
        return match ($scope) {
            'workspace' => "var:workspace:{$workspaceId}:{$key}",
            'workflow' => "var:workflow:{$workflowId}:{$key}",
            'execution' => "var:execution:{$executionId}:{$key}",
            default => "var:global:{$key}",
        };
    }
}
