<?php

namespace App\Engine;

use App\Engine\Graph\ExpressionResolver;
use App\Engine\Graph\WorkflowGraph;

class NodeInput
{
    /**
     * @param  array<string, mixed>  $config
     * @param  array<string, mixed>  $inputData
     * @param  array<string, mixed>|null  $credentials
     * @param  array<string, mixed>  $variables
     * @param  array<string, mixed>  $executionMeta
     */
    public function __construct(
        public readonly string $nodeId,
        public readonly string $nodeType,
        public readonly string $nodeName,
        public readonly array $config,
        public readonly array $inputData,
        public readonly ?array $credentials = null,
        public readonly array $variables = [],
        public readonly array $executionMeta = [],
        public readonly ?string $nodeRunKey = null,
    ) {}

    public static function build(string $nodeId, WorkflowGraph $graph, WorkflowContext $context): self
    {
        $resolver = new ExpressionResolver;

        $node = $graph->getNode($nodeId);
        $compiledConfig = $graph->getCompiledConfig($nodeId);

        if (! empty($compiledConfig)) {
            $expressionContext = $resolver->hasExpressions($compiledConfig)
                ? $context->buildExpressionContext()
                : [];
            $resolvedConfig = $resolver->resolveConfig($compiledConfig, $expressionContext);
        } else {
            $resolvedConfig = $node['data'] ?? $node['config'] ?? [];
        }

        $operation = NodeCatalog::operation($node['type'] ?? '');
        if ($operation !== null && ! isset($resolvedConfig['operation'])) {
            $resolvedConfig['operation'] = $operation;
        }

        $inputData = $context->gatherInputData($nodeId);

        $credential = $context->getCredential($nodeId);
        $credentialData = $credential?->data;
        if (is_string($credentialData)) {
            $credentialData = json_decode($credentialData, true);
        }
        if ($credential && is_array($credentialData) && ! isset($credentialData['type'])) {
            $credentialData['type'] = $credential->type;
        }

        return new self(
            nodeId: $nodeId,
            nodeType: $node['type'] ?? 'unknown',
            nodeName: $node['name'] ?? $node['data']['name'] ?? $nodeId,
            config: $resolvedConfig,
            inputData: $inputData,
            credentials: $credentialData,
            variables: $context->getVariables(),
            executionMeta: [
                'execution_id' => $context->executionId,
                'trigger_data' => $context->getVariables()['__trigger_data'] ?? [],
            ],
            nodeRunKey: $nodeId,
        );
    }
}
