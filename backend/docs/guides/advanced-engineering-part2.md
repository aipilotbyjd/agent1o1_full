# 🚀 Advanced Engineering Guide - Part 2

**Continuation of advanced patterns for LinkFlow at scale**

---

## 11. Workflow Optimization Engine

### 11.1 Automatic Workflow Optimization

**Problem:** Users create inefficient workflows without realizing it.

**Solution:** Static analysis and automatic optimization.

```php
// app/Optimization/WorkflowOptimizer.php
class WorkflowOptimizer
{
    /**
     * Analyze and optimize workflow
     */
    public function optimize(Workflow $workflow): OptimizationResult
    {
        $definition = $workflow->definition;
        $issues = [];
        $optimizations = [];
        
        // 1. Detect sequential operations that can be parallelized
        $parallelizable = $this->detectParallelizableNodes($definition);
        if (!empty($parallelizable)) {
            $optimizations[] = new ParallelizeNodesOptimization($parallelizable);
            $issues[] = "Found {$parallelizable} nodes that can run in parallel";
        }
        
        // 2. Detect redundant HTTP calls
        $redundant = $this->detectRedundantHttpCalls($definition);
        if (!empty($redundant)) {
            $optimizations[] = new CacheHttpCallsOptimization($redundant);
            $issues[] = "Found " . count($redundant) . " redundant HTTP calls";
        }
        
        // 3. Detect expensive operations in loops
        $loopIssues = $this->detectExpensiveLoopOperations($definition);
        if (!empty($loopIssues)) {
            $optimizations[] = new MoveOutOfLoopOptimization($loopIssues);
            $issues[] = "Found expensive operations inside loops";
        }
        
        // 4. Detect unused variables
        $unused = $this->detectUnusedVariables($definition);
        if (!empty($unused)) {
            $optimizations[] = new RemoveUnusedVariablesOptimization($unused);
            $issues[] = "Found " . count($unused) . " unused variables";
        }
        
        // 5. Suggest node replacements
        $replacements = $this->suggestNodeReplacements($definition);
        if (!empty($replacements)) {
            $optimizations[] = new ReplaceNodesOptimization($replacements);
            $issues[] = "Found more efficient alternatives for " . count($replacements) . " nodes";
        }
        
        return new OptimizationResult($issues, $optimizations);
    }
    
    /**
     * Detect nodes that can run in parallel
     */
    private function detectParallelizableNodes(array $definition): array
    {
        $graph = new WorkflowGraph($definition);
        $parallelizable = [];
        
        foreach ($graph->getNodes() as $node) {
            $dependencies = $graph->getDependencies($node->id);
            $dependents = $graph->getDependents($node->id);
            
            // Check if siblings can run in parallel
            foreach ($dependents as $dependent) {
                $siblings = $graph->getSiblings($dependent);
                
                if (count($siblings) > 1) {
                    // These siblings can run in parallel
                    $parallelizable[] = [
                        'nodes' => $siblings,
                        'parent' => $node->id,
                        'estimated_speedup' => $this->estimateSpeedup($siblings),
                    ];
                }
            }
        }
        
        return $parallelizable;
    }
    
    /**
     * Detect redundant HTTP calls (same URL, method, params)
     */
    private function detectRedundantHttpCalls(array $definition): array
    {
        $httpNodes = array_filter($definition['nodes'], fn($n) => $n['type'] === 'http');
        $seen = [];
        $redundant = [];
        
        foreach ($httpNodes as $node) {
            $signature = $this->getHttpSignature($node);
            
            if (isset($seen[$signature])) {
                $redundant[] = [
                    'duplicate' => $node['id'],
                    'original' => $seen[$signature],
                    'url' => $node['config']['url'],
                ];
            } else {
                $seen[$signature] = $node['id'];
            }
        }
        
        return $redundant;
    }
    
    /**
     * Detect expensive operations inside loops
     */
    private function detectExpensiveLoopOperations(array $definition): array
    {
        $loopNodes = array_filter($definition['nodes'], fn($n) => $n['type'] === 'loop');
        $issues = [];
        
        foreach ($loopNodes as $loopNode) {
            $loopBody = $this->getLoopBody($loopNode, $definition);
            
            foreach ($loopBody as $node) {
                // Check for expensive operations
                $cost = $this->estimateNodeCost($node);
                
                if ($cost > 100) { // Expensive (>100ms)
                    $issues[] = [
                        'loop' => $loopNode['id'],
                        'node' => $node['id'],
                        'type' => $node['type'],
                        'estimated_cost_per_iteration' => $cost,
                        'suggestion' => $this->getSuggestion($node),
                    ];
                }
            }
        }
        
        return $issues;
    }
    
    /**
     * Estimate cost of running a node
     */
    private function estimateNodeCost(array $node): float
    {
        return match($node['type']) {
            'http' => 50.0,  // 50ms average
            'llm' => 500.0,  // 500ms average
            'database' => 10.0,
            'transform' => 1.0,
            default => 5.0,
        };
    }
}
```

**Apply optimizations:**

```php
// Auto-optimize on save
class SaveWorkflowHandler
{
    public function handle(SaveWorkflowCommand $command): void
    {
        $workflow = Workflow::find($command->workflowId);
        
        // Analyze workflow
        $optimizer = new WorkflowOptimizer();
        $result = $optimizer->optimize($workflow);
        
        if ($result->hasOptimizations()) {
            // Show suggestions to user
            $this->showOptimizationSuggestions($result);
            
            // Auto-apply safe optimizations
            foreach ($result->getSafeOptimizations() as $optimization) {
                $optimization->apply($workflow);
            }
        }
        
        $workflow->save();
    }
}
```

---

### 11.2 Cost Prediction & Budgeting

**Problem:** Users don't know how much a workflow will cost before running.

**Solution:** Predict cost based on historical data and node types.

```php
// app/Optimization/CostPredictor.php
class CostPredictor
{
    /**
     * Predict cost of workflow execution
     */
    public function predict(Workflow $workflow, array $inputData = []): CostPrediction
    {
        $definition = $workflow->definition;
        $predictions = [];
        $totalCost = 0;
        
        foreach ($definition['nodes'] as $node) {
            $nodeCost = $this->predictNodeCost($node, $inputData);
            
            $predictions[] = [
                'node_id' => $node['id'],
                'node_type' => $node['type'],
                'estimated_cost' => $nodeCost,
                'confidence' => $this->getConfidence($node),
            ];
            
            $totalCost += $nodeCost;
        }
        
        // Add conditional branch costs (probability-weighted)
        $branchCosts = $this->predictBranchCosts($definition);
        $totalCost += $branchCosts;
        
        // Add loop costs (estimate iterations)
        $loopCosts = $this->predictLoopCosts($definition, $inputData);
        $totalCost += $loopCosts;
        
        return new CostPrediction([
            'total_estimated_cost' => $totalCost,
            'min_cost' => $totalCost * 0.8, // -20%
            'max_cost' => $totalCost * 1.5, // +50%
            'node_breakdown' => $predictions,
            'confidence' => $this->calculateOverallConfidence($predictions),
            'historical_data_points' => $this->getHistoricalDataPoints($workflow),
        ]);
    }
    
    /**
     * Predict cost of single node
     */
    private function predictNodeCost(array $node, array $inputData): float
    {
        // Get historical avg cost for this node type
        $historicalAvg = $this->getHistoricalAvgCost($node['type']);
        
        // Adjust based on configuration
        $configMultiplier = $this->getConfigMultiplier($node);
        
        // Special handling for AI nodes
        if ($node['type'] === 'llm') {
            return $this->predictLLMCost($node, $inputData);
        }
        
        return $historicalAvg * $configMultiplier;
    }
    
    /**
     * Predict LLM API cost based on estimated tokens
     */
    private function predictLLMCost(array $node, array $inputData): float
    {
        $model = $node['config']['model'] ?? 'gpt-4-turbo';
        $prompt = $node['config']['prompt'] ?? '';
        
        // Interpolate variables to get actual prompt
        $actualPrompt = $this->interpolatePrompt($prompt, $inputData);
        
        // Estimate tokens
        $inputTokens = $this->estimateTokens($actualPrompt);
        $outputTokens = $node['config']['max_tokens'] ?? 500;
        
        // Get pricing
        $pricing = $this->getModelPricing($model);
        
        $inputCost = ($inputTokens / 1_000_000) * $pricing['input'];
        $outputCost = ($outputTokens / 1_000_000) * $pricing['output'];
        
        return $inputCost + $outputCost;
    }
    
    /**
     * Get model pricing (per 1M tokens)
     */
    private function getModelPricing(string $model): array
    {
        return match($model) {
            'gpt-4-turbo' => ['input' => 10.00, 'output' => 30.00],
            'gpt-3.5-turbo' => ['input' => 0.50, 'output' => 1.50],
            'claude-sonnet' => ['input' => 3.00, 'output' => 15.00],
            'claude-haiku' => ['input' => 0.25, 'output' => 1.25],
            default => ['input' => 5.00, 'output' => 15.00],
        };
    }
    
    /**
     * Predict loop costs based on estimated iterations
     */
    private function predictLoopCosts(array $definition, array $inputData): float
    {
        $loopNodes = array_filter($definition['nodes'], fn($n) => $n['type'] === 'loop');
        $totalLoopCost = 0;
        
        foreach ($loopNodes as $loopNode) {
            // Estimate number of iterations
            $iterations = $this->estimateIterations($loopNode, $inputData);
            
            // Get loop body nodes
            $bodyNodes = $this->getLoopBody($loopNode, $definition);
            
            // Calculate cost per iteration
            $costPerIteration = 0;
            foreach ($bodyNodes as $node) {
                $costPerIteration += $this->predictNodeCost($node, $inputData);
            }
            
            $totalLoopCost += $iterations * $costPerIteration;
        }
        
        return $totalLoopCost;
    }
}
```

**Show cost prediction to user:**

```php
// Before executing workflow
$predictor = new CostPredictor();
$prediction = $predictor->predict($workflow, $inputData);

if ($prediction->totalEstimatedCost > $user->remainingBudget) {
    return response()->json([
        'error' => 'Insufficient budget',
        'estimated_cost' => $prediction->totalEstimatedCost,
        'remaining_budget' => $user->remainingBudget,
        'breakdown' => $prediction->nodeBreakdown,
    ], 402);
}
```

---

## 12. Advanced Node Development

### 12.1 Async/Await Pattern for Nodes

**Problem:** Nodes block on I/O, wasting resources.

**Solution:** Async node execution with promises.

```php
// app/Engine/Nodes/AsyncNode.php
abstract class AsyncNode extends NodeExecutor
{
    /**
     * Execute node asynchronously
     */
    abstract public function executeAsync(array $config, array $inputs): Promise;
    
    /**
     * Synchronous wrapper
     */
    public function execute(array $config, array $inputs): array
    {
        $promise = $this->executeAsync($config, $inputs);
        
        return $promise->wait();
    }
}
```

**Example: Async HTTP Node:**

```php
// app/Engine/Nodes/Apps/AsyncHttpNode.php
class AsyncHttpNode extends AsyncNode
{
    public function executeAsync(array $config, array $inputs): Promise
    {
        return new Promise(function($resolve, $reject) use ($config, $inputs) {
            $url = $this->interpolate($config['url'], $inputs);
            
            // Non-blocking HTTP request
            Http::async()
                ->timeout($config['timeout'] ?? 30)
                ->send($config['method'], $url, $config['body'] ?? [])
                ->then(
                    function($response) use ($resolve) {
                        $resolve([
                            'status' => $response->status(),
                            'body' => $response->json(),
                            'headers' => $response->headers(),
                        ]);
                    },
                    function($error) use ($reject) {
                        $reject($error);
                    }
                );
        });
    }
}
```

**Parallel async execution:**

```php
// Execute multiple nodes in parallel
$promises = [];

foreach ($nodes as $node) {
    $promises[] = $node->executeAsync($config, $inputs);
}

// Wait for all to complete
$results = Promise::all($promises)->wait();
```

---

### 12.2 Streaming Output Nodes

**Problem:** Large responses cause memory issues and delays.

**Solution:** Stream output incrementally.

```php
// app/Engine/Nodes/StreamingNode.php
abstract class StreamingNode extends NodeExecutor
{
    /**
     * Execute and stream output
     */
    abstract public function executeStreaming(
        array $config,
        array $inputs,
        callable $onChunk
    ): void;
}
```

**Example: Streaming LLM Node:**

```php
// app/Engine/Nodes/Apps/Ai/StreamingLLMNode.php
class StreamingLLMNode extends StreamingNode
{
    public function executeStreaming(
        array $config,
        array $inputs,
        callable $onChunk
    ): void {
        $model = $config['model'];
        $prompt = $this->interpolate($config['prompt'], $inputs);
        
        // Stream from OpenAI
        OpenAI::chat()->createStreamed([
            'model' => $model,
            'messages' => [
                ['role' => 'user', 'content' => $prompt],
            ],
            'stream' => true,
        ], function($chunk) use ($onChunk) {
            $content = $chunk['choices'][0]['delta']['content'] ?? '';
            
            if ($content) {
                // Send chunk to client
                $onChunk(['text' => $content]);
            }
        });
    }
}
```

**Client receives real-time updates:**

```php
// WebSocket connection
$node->executeStreaming($config, $inputs, function($chunk) {
    // Send to client via WebSocket
    WebSocket::send($executionId, [
        'type' => 'chunk',
        'node_id' => $nodeId,
        'data' => $chunk,
    ]);
});
```

---

### 12.3 Stateful Nodes with Persistence

**Problem:** Some nodes need to maintain state across executions.

**Solution:** Persistent node state.

```php
// app/Engine/Nodes/StatefulNode.php
abstract class StatefulNode extends NodeExecutor
{
    protected string $stateKey;
    
    /**
     * Get persisted state
     */
    protected function getState(): array
    {
        return Cache::get($this->getStateKey(), []);
    }
    
    /**
     * Update persisted state
     */
    protected function setState(array $state): void
    {
        Cache::put($this->getStateKey(), $state, 3600);
    }
    
    private function getStateKey(): string
    {
        $workspace = request()->attributes->get('workspace');
        
        return "node_state:{$workspace->id}:{$this->stateKey}";
    }
}
```

**Example: Rate Limiting Node:**

```php
// app/Engine/Nodes/Flow/RateLimitNode.php
class RateLimitNode extends StatefulNode
{
    protected string $stateKey = 'rate_limit';
    
    public function execute(array $config, array $inputs): array
    {
        $limit = $config['max_requests_per_minute'] ?? 60;
        $key = $config['key'] ?? 'default';
        
        // Get current state
        $state = $this->getState();
        $requests = $state[$key] ?? [];
        
        // Remove old requests (older than 1 minute)
        $cutoff = time() - 60;
        $requests = array_filter($requests, fn($t) => $t > $cutoff);
        
        // Check limit
        if (count($requests) >= $limit) {
            $oldestRequest = min($requests);
            $waitTime = 60 - (time() - $oldestRequest);
            
            // Suspend workflow
            throw new WorkflowSuspendedException(
                "Rate limit exceeded. Resume in {$waitTime} seconds.",
                $waitTime
            );
        }
        
        // Record this request
        $requests[] = time();
        $state[$key] = $requests;
        $this->setState($state);
        
        return ['allowed' => true];
    }
}
```

---

## 13. Chaos Engineering

### 13.1 Fault Injection for Testing

**Problem:** Need to test system resilience without breaking production.

**Solution:** Controlled fault injection in staging.

```php
// app/ChaosEngineering/FaultInjector.php
class FaultInjector
{
    /**
     * Inject random failures for testing
     */
    public function maybeInjectFault(string $operation): void
    {
        if (!app()->environment('staging')) {
            return; // Only in staging
        }
        
        if (!config('chaos.enabled', false)) {
            return;
        }
        
        $faultRate = config("chaos.operations.{$operation}.rate", 0.05); // 5% default
        
        if (rand() / getrandmax() < $faultRate) {
            $this->injectFault($operation);
        }
    }
    
    private function injectFault(string $operation): void
    {
        $faultType = $this->chooseFaultType();
        
        Log::warning("Chaos: Injecting {$faultType} fault for {$operation}");
        
        match($faultType) {
            'latency' => $this->injectLatency(),
            'error' => $this->injectError(),
            'timeout' => $this->injectTimeout(),
            'partial_failure' => $this->injectPartialFailure(),
        };
    }
    
    private function injectLatency(): void
    {
        // Add 1-5 seconds delay
        $delay = rand(1000, 5000);
        usleep($delay * 1000);
    }
    
    private function injectError(): void
    {
        throw new ChaosEngineeringException('Injected error for testing');
    }
    
    private function injectTimeout(): void
    {
        // Simulate timeout
        sleep(35); // Exceeds 30s timeout
    }
}
```

**Usage:**

```php
// In HTTP node
class HttpNode extends NodeExecutor
{
    public function execute(array $config, array $inputs): array
    {
        // Maybe inject fault
        app(FaultInjector::class)->maybeInjectFault('http_call');
        
        // Normal execution
        return Http::send(...);
    }
}
```

**Chaos experiments:**

```php
// app/ChaosEngineering/ChaosExperiment.php
class ChaosExperiment
{
    /**
     * Run chaos experiment
     * 
     * Example: What happens if database is slow?
     */
    public function runExperiment(string $experimentName): ExperimentResult
    {
        $experiment = config("chaos.experiments.{$experimentName}");
        
        Log::info("Starting chaos experiment: {$experimentName}");
        
        // Record baseline metrics
        $baseline = $this->recordMetrics();
        
        // Enable fault injection
        config(['chaos.enabled' => true]);
        config(['chaos.operations' => $experiment['faults']]);
        
        // Run for specified duration
        sleep($experiment['duration']);
        
        // Record metrics under chaos
        $chaosMetrics = $this->recordMetrics();
        
        // Disable fault injection
        config(['chaos.enabled' => false]);
        
        // Wait for recovery
        sleep(60);
        
        // Record recovery metrics
        $recoveryMetrics = $this->recordMetrics();
        
        return new ExperimentResult([
            'baseline' => $baseline,
            'chaos' => $chaosMetrics,
            'recovery' => $recoveryMetrics,
            'passed' => $this->evaluateResult($baseline, $chaosMetrics, $recoveryMetrics),
        ]);
    }
}
```

---

## 14. Multi-Region Architecture

### 14.1 Active-Active Multi-Region Setup

**Problem:** Need global presence with low latency everywhere.

**Solution:** Active-active deployment across regions.

```
┌──────────────────┐      ┌──────────────────┐      ┌──────────────────┐
│   US-EAST-1      │      │    EU-WEST-1     │      │   AP-SOUTHEAST   │
│                  │      │                  │      │                  │
│  ┌────────────┐  │      │  ┌────────────┐  │      │  ┌────────────┐  │
│  │ API Servers│  │      │  │ API Servers│  │      │  │ API Servers│  │
│  └────────────┘  │      │  └────────────┘  │      │  └────────────┘  │
│                  │      │                  │      │                  │
│  ┌────────────┐  │      │  ┌────────────┐  │      │  ┌────────────┐  │
│  │ PostgreSQL │◄─┼──────┼─►│ PostgreSQL │◄─┼──────┼─►│ PostgreSQL │  │
│  │  (Primary) │  │      │  │  (Replica) │  │      │  │  (Replica) │  │
│  └────────────┘  │      │  └────────────┘  │      │  └────────────┘  │
│                  │      │                  │      │                  │
│  ┌────────────┐  │      │  ┌────────────┐  │      │  ┌────────────┐  │
│  │   Redis    │◄─┼──────┼─►│   Redis    │◄─┼──────┼─►│   Redis    │  │
│  └────────────┘  │      │  └────────────┘  │      │  └────────────┘  │
└──────────────────┘      └──────────────────┘      └──────────────────┘
         │                         │                         │
         └─────────────────────────┴─────────────────────────┘
                              AWS Route53
                         (GeoDNS Routing)
```

**Database replication:**

```php
// config/database.php
'connections' => [
    // Write to primary (US-EAST-1)
    'pgsql_primary' => [
        'driver' => 'pgsql',
        'host' => env('DB_PRIMARY_HOST'),
        'database' => 'linkflow',
        // ...
    ],
    
    // Read from local replica
    'pgsql_replica' => [
        'driver' => 'pgsql',
        'host' => env('DB_REPLICA_HOST'), // Region-specific
        'database' => 'linkflow',
        'read' => true,
        // ...
    ],
],
```

**Smart routing:**

```php
// app/Database/MultiRegionConnection.php
class MultiRegionConnection
{
    /**
     * Route writes to primary, reads to local replica
     */
    public function connection(string $operation): \Illuminate\Database\Connection
    {
        return match($operation) {
            'write' => DB::connection('pgsql_primary'),
            'read' => DB::connection('pgsql_replica'),
        };
    }
    
    /**
     * Get nearest region for user
     */
    public function getNearestRegion(string $userIp): string
    {
        // GeoIP lookup
        $country = geoip($userIp)->country;
        
        return match(true) {
            in_array($country, ['US', 'CA', 'MX']) => 'us-east-1',
            in_array($country, ['GB', 'FR', 'DE', 'ES', 'IT']) => 'eu-west-1',
            in_array($country, ['JP', 'CN', 'IN', 'AU', 'SG']) => 'ap-southeast-1',
            default => 'us-east-1',
        };
    }
}
```

---

### 14.2 Cross-Region Workflow Execution

**Problem:** Workflow starts in US, needs to call EU-only service.

**Solution:** Route execution to appropriate region.

```php
// app/Engine/MultiRegion/RegionRouter.php
class RegionRouter
{
    /**
     * Route node execution to optimal region
     */
    public function routeNode(NodeExecution $node): string
    {
        // Check if node requires specific region
        if ($requiredRegion = $this->getRequiredRegion($node)) {
            return $requiredRegion;
        }
        
        // Check if node calls regional service
        if ($preferredRegion = $this->getPreferredRegion($node)) {
            return $preferredRegion;
        }
        
        // Default to execution's home region
        return $node->execution->region;
    }
    
    /**
     * Execute node in specific region
     */
    public function executeInRegion(NodeExecution $node, string $region): array
    {
        $currentRegion = config('app.region');
        
        if ($region === $currentRegion) {
            // Execute locally
            return $this->executeLocally($node);
        }
        
        // Forward to remote region
        return $this->executeRemotely($node, $region);
    }
    
    private function executeRemotely(NodeExecution $node, string $region): array
    {
        $endpoint = config("regions.{$region}.api_url");
        
        // Call remote API
        $response = Http::timeout(60)
            ->withHeaders([
                'X-Region-Forward' => $region,
                'X-Execution-Id' => $node->execution_id,
            ])
            ->post("{$endpoint}/internal/execute-node", [
                'node_id' => $node->id,
                'config' => $node->config,
                'inputs' => $node->inputs,
            ]);
        
        return $response->json();
    }
}
```

---

## 15. Performance at Scale

### 15.1 Connection Pooling

**Problem:** Creating new DB connections is expensive.

**Solution:** Connection pooling with PgBouncer.

```ini
# /etc/pgbouncer/pgbouncer.ini
[databases]
linkflow = host=localhost port=5432 dbname=linkflow pool_size=25

[pgbouncer]
listen_addr = 127.0.0.1
listen_port = 6432
auth_type = md5
auth_file = /etc/pgbouncer/userlist.txt

# Connection pooling mode
pool_mode = transaction

# Connection limits
max_client_conn = 1000
default_pool_size = 25
reserve_pool_size = 5

# Timeouts
server_idle_timeout = 600
```

**Laravel config:**

```php
// config/database.php
'pgsql' => [
    'host' => '127.0.0.1',
    'port' => '6432', // PgBouncer port (not 5432)
    'database' => 'linkflow',
    // ...
],
```

**Benefits:**
- Reuse connections (5-10x faster)
- Support 1000+ concurrent clients with 25 DB connections
- Automatic failover

---

### 15.2 Read-Through Write-Through Cache

**Problem:** Cache invalidation is hard, often stale data.

**Solution:** Read-through and write-through cache pattern.

```php
// app/Cache/ReadWriteThroughCache.php
class ReadWriteThroughCache
{
    /**
     * Read-through: Check cache first, fetch from DB on miss
     */
    public function get(string $key, callable $fetchFromDb): mixed
    {
        // Try cache
        if ($value = Cache::get($key)) {
            return $value;
        }
        
        // Cache miss - fetch from DB
        $value = $fetchFromDb();
        
        // Store in cache
        Cache::put($key, $value, 3600);
        
        return $value;
    }
    
    /**
     * Write-through: Update cache and DB simultaneously
     */
    public function set(string $key, mixed $value, callable $writeToDb): void
    {
        // Write to cache
        Cache::put($key, $value, 3600);
        
        // Write to DB (synchronously)
        $writeToDb($value);
    }
    
    /**
     * Write-behind: Update cache immediately, DB asynchronously
     */
    public function setAsync(string $key, mixed $value, callable $writeToDb): void
    {
        // Write to cache immediately
        Cache::put($key, $value, 3600);
        
        // Write to DB asynchronously
        dispatch(function() use ($key, $value, $writeToDb) {
            $writeToDb($value);
        })->onQueue('cache-writethrough');
    }
}
```

**Usage:**

```php
// Read
$workflow = $cache->get("workflow:{$id}", function() use ($id) {
    return Workflow::find($id);
});

// Write
$cache->set("workflow:{$id}", $workflow, function($wf) {
    $wf->save();
});
```

---

### 15.3 Materialized Views for Analytics

**Problem:** Analytics queries are slow on main tables.

**Solution:** Materialized views for pre-aggregated data.

```sql
-- Create materialized view for workflow stats
CREATE MATERIALIZED VIEW workflow_stats AS
SELECT 
    workflow_id,
    COUNT(*) as total_executions,
    COUNT(*) FILTER (WHERE status = 'success') as successful_executions,
    COUNT(*) FILTER (WHERE status = 'failed') as failed_executions,
    AVG(duration_seconds) as avg_duration,
    PERCENTILE_CONT(0.95) WITHIN GROUP (ORDER BY duration_seconds) as p95_duration,
    SUM(credits_used) as total_credits_used,
    MAX(created_at) as last_execution_at
FROM executions
GROUP BY workflow_id;

-- Create unique index
CREATE UNIQUE INDEX idx_workflow_stats_workflow_id 
ON workflow_stats(workflow_id);

-- Refresh schedule (every 5 minutes)
CREATE EXTENSION IF NOT EXISTS pg_cron;

SELECT cron.schedule('refresh-workflow-stats', '*/5 * * * *', 
    'REFRESH MATERIALIZED VIEW CONCURRENTLY workflow_stats');
```

**Query from view:**

```php
// Fast query (pre-aggregated)
$stats = DB::table('workflow_stats')
    ->where('workflow_id', $workflowId)
    ->first();

// vs. slow query (aggregates on-the-fly)
$stats = DB::table('executions')
    ->where('workflow_id', $workflowId)
    ->selectRaw('
        COUNT(*) as total_executions,
        AVG(duration_seconds) as avg_duration,
        ...
    ')
    ->first();
```

**Performance:**
- Materialized view: ~1ms
- Direct aggregation: ~500ms (500x slower!)

---

### 15.4 Query Result Streaming

**Problem:** Large result sets cause memory issues and delays.

**Solution:** Stream results using generators.

```php
// app/Database/StreamingQuery.php
class StreamingQuery
{
    /**
     * Stream large result set
     */
    public function stream(string $query, array $bindings = []): \Generator
    {
        // Use cursor (server-side cursor)
        $cursor = DB::connection()->cursor($query, $bindings);
        
        foreach ($cursor as $row) {
            yield $row;
        }
    }
}
```

**Usage:**

```php
// Stream 1M executions without loading all into memory
$stream = $streamingQuery->stream(
    'SELECT * FROM executions WHERE workspace_id = ?',
    [$workspaceId]
);

foreach ($stream as $execution) {
    // Process one at a time
    $this->processExecution($execution);
    
    // Memory usage stays constant
}
```

---

### 15.5 Horizontal Pod Autoscaling (Kubernetes)

**Problem:** Need to scale workers based on queue depth.

**Solution:** HPA with custom metrics.

```yaml
# k8s/hpa-horizon.yaml
apiVersion: autoscaling/v2
kind: HorizontalPodAutoscaler
metadata:
  name: horizon-workers
spec:
  scaleTargetRef:
    apiVersion: apps/v1
    kind: Deployment
    name: horizon
  minReplicas: 3
  maxReplicas: 50
  metrics:
  # Scale based on queue depth
  - type: External
    external:
      metric:
        name: redis_queue_depth
        selector:
          matchLabels:
            queue: workflows
      target:
        type: AverageValue
        averageValue: "100" # 100 jobs per pod
  
  # Scale based on CPU
  - type: Resource
    resource:
      name: cpu
      target:
        type: Utilization
        averageUtilization: 70
  
  behavior:
    scaleDown:
      stabilizationWindowSeconds: 300
      policies:
      - type: Percent
        value: 50
        periodSeconds: 60
    scaleUp:
      stabilizationWindowSeconds: 60
      policies:
      - type: Percent
        value: 100
        periodSeconds: 30
```

**Custom metrics exporter:**

```php
// app/Metrics/QueueDepthExporter.php
class QueueDepthExporter
{
    public function export(): void
    {
        $depth = Redis::llen('queue:workflows');
        
        // Export to Prometheus
        Prometheus::gauge('redis_queue_depth')
            ->set($depth, ['queue' => 'workflows']);
    }
}
```

---

## 🎓 Summary

This advanced guide covered:

1. **Advanced Workflow Patterns** - Streaming, lazy evaluation, speculative execution, JIT compilation
2. **Distributed Execution** - Work stealing, circuit breakers, saga pattern
3. **Advanced Caching** - Multi-level cache, predictive warming, stampede prevention
4. **Database Optimization** - Sharding, partitioning, materialized views
5. **Event-Driven Architecture** - Event sourcing, CQRS
6. **Observability** - Distributed tracing, structured logging, real-time metrics
7. **Plugin System** - Custom nodes, webhook extensions
8. **Multi-Tenancy** - Isolation, per-tenant configuration
9. **Security** - Zero-trust, secrets vault
10. **Real-Time Collaboration** - Operational transform
11. **Workflow Optimization** - Auto-optimization, cost prediction
12. **Advanced Nodes** - Async/await, streaming, stateful nodes
13. **Chaos Engineering** - Fault injection, resilience testing
14. **Multi-Region** - Active-active deployment, cross-region execution
15. **Performance** - Connection pooling, caching patterns, horizontal scaling

---

## 📚 Further Reading

- [Laravel Performance](https://laravel.com/docs/performance)
- [PostgreSQL Performance Tuning](https://wiki.postgresql.org/wiki/Performance_Optimization)
- [Redis Best Practices](https://redis.io/docs/manual/patterns/)
- [Microservices Patterns](https://microservices.io/patterns/)
- [Site Reliability Engineering (SRE)](https://sre.google/books/)

---

*Last Updated: December 2024*
*For production use at 1M+ executions/day*
