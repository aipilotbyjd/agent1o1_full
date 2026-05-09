# 🏛️ Architecture Deep Dive

**Technical Architecture of LinkFlow Workflow Automation Platform**

---

## Table of Contents

1. [System Architecture](#system-architecture)
2. [Workflow Engine Architecture](#workflow-engine-architecture)
3. [Data Flow](#data-flow)
4. [Database Architecture](#database-architecture)
5. [Queue System](#queue-system)
6. [Caching Strategy](#caching-strategy)
7. [Security Architecture](#security-architecture)
8. [Scalability Design](#scalability-design)

---

## System Architecture

### Layered Architecture

```
┌────────────────────────────────────────────────────────┐
│              PRESENTATION LAYER (React Frontend)            │
│  - React Flow Canvas                                       │
│  - React Query (State Management)                          │
│  - Axios (HTTP Client)                                     │
└──────────────────────────┬─────────────────────────────┘
                          │ REST API (JSON)
                          ↓
┌────────────────────────────────────────────────────────┐
│                  API LAYER (Laravel)                       │
│  - Controllers (HTTP Handling)                             │
│  - Middleware (Auth, CORS, Rate Limiting)                  │
│  - Form Requests (Validation)                              │
│  - API Resources (Response Transformation)                 │
└──────────────────────────┬─────────────────────────────┘
                          │
                          ↓
┌────────────────────────────────────────────────────────┐
│               BUSINESS LOGIC LAYER                          │
│  - Services (WorkflowService, ExecutionService, etc.)      │
│  - Domain Logic                                            │
│  - Business Rules                                          │
└──────────────────────────┬─────────────────────────────┘
                          │
                          ↓
┌────────────────────────────────────────────────────────┐
│             WORKFLOW ENGINE LAYER                           │
│  - WorkflowEngine (Orchestrator)                           │
│  - GraphCompiler (Parse workflow definition)               │
│  - ExecutionScheduler (Node scheduling)                    │
│  - Node Executors (Execute individual nodes)               │
│  - RunContext (Execution state)                            │
└──────────────────────────┬─────────────────────────────┘
                          │
                          ↓
┌────────────────────────────────────────────────────────┐
│                 DATA ACCESS LAYER                          │
│  - Eloquent Models (ORM)                                   │
│  - Repositories (Optional)                                 │
│  - Query Builders                                          │
└──────────────────────────┬─────────────────────────────┘
                          │
        ┌───────────────┼───────────────┐
        ↓               ↓               ↓
┌──────────────┐ ┌─────────────┐ ┌─────────────┐
│  PostgreSQL  │ │    Redis    │ │  Storage    │
│  + pgvector  │ │   (Cache)   │ │  (Files)    │
└──────────────┘ └─────────────┘ └─────────────┘
```

---

## Workflow Engine Architecture

### Engine Components

#### 1. **WorkflowEngine** (Main Orchestrator)

**Location:** `/app/Engine/WorkflowEngine.php`

**Responsibilities:**
- Orchestrates entire workflow execution
- Coordinates between compiler, scheduler, and executors
- Handles suspension and resumption
- Manages checkpoints and state persistence

**Key Methods:**
```php
class WorkflowEngine {
    public function run(Execution $execution): void
    public function resume(Execution $execution, array $resumeData): void
    public function cancel(Execution $execution): void
    
    protected function executeNode(Node $node, RunContext $context): NodeResult
    protected function suspend(RunContext $context, Suspension $suspension): void
}
```

#### 2. **GraphCompiler**

**Location:** `/app/Engine/Graph/GraphCompiler.php`

**Responsibilities:**
- Parses workflow JSON definition
- Builds directed acyclic graph (DAG)
- Validates node connections
- Identifies entry points and dependencies

**Process:**
```php
// Input: Workflow definition
{
  "nodes": [
    {"id": "node1", "type": "trigger"},
    {"id": "node2", "type": "http_request"},
    {"id": "node3", "type": "email"}
  ],
  "connections": [
    {"from": "node1", "to": "node2"},
    {"from": "node2", "to": "node3"}
  ]
}

// Output: Execution Graph
Graph {
  nodes: [
    Node{id: 'node1', dependencies: [], type: 'trigger'},
    Node{id: 'node2', dependencies: ['node1'], type: 'http_request'},
    Node{id: 'node3', dependencies: ['node2'], type: 'email'}
  ]
}
```

#### 3. **ExecutionScheduler**

**Location:** `/app/Engine/Execution/ExecutionScheduler.php`

**Responsibilities:**
- Determines which nodes can run next
- Checks dependency satisfaction
- Handles parallel execution
- Respects node execution order

**Scheduling Algorithm:**
```php
public function nextNode(RunContext $context): ?Node {
    foreach ($context->graph->nodes as $node) {
        // Skip already executed
        if ($context->isExecuted($node->id)) {
            continue;
        }
        
        // Skip nodes waiting on dependencies
        if (!$this->areDependenciesSatisfied($node, $context)) {
            continue;
        }
        
        // Found next executable node
        return $node;
    }
    
    return null; // No more nodes to execute
}

private function areDependenciesSatisfied(Node $node, RunContext $context): bool {
    foreach ($node->dependencies as $depNodeId) {
        if (!$context->isExecuted($depNodeId)) {
            return false;
        }
    }
    return true;
}
```

#### 4. **RunContext** (Execution State)

**Location:** `/app/Engine/RunContext.php`

**Responsibilities:**
- Maintains execution state
- Stores node results
- Tracks executed nodes
- Provides data to nodes

**Structure:**
```php
class RunContext {
    public Execution $execution;
    public Graph $graph;
    public array $nodeResults = []; // ['node_id' => result]
    public array $executedNodes = [];
    public array $globalVariables = [];
    
    public function getNodeOutput(string $nodeId): mixed
    public function setNodeOutput(string $nodeId, mixed $output): void
    public function isExecuted(string $nodeId): bool
}
```

#### 5. **Node Executors**

**Base Node:**
```php
abstract class BaseNode {
    abstract public function execute(array $input, RunContext $context): array;
    
    protected function getCredential(string $type): ?Credential
    protected function getVariable(string $key): mixed
    protected function log(string $message, array $data = []): void
}
```

**Example HTTP Node:**
```php
class HttpRequestNode extends BaseNode {
    public function execute(array $input, RunContext $context): array {
        $config = $this->config;
        $url = $this->interpolate($config['url'], $context);
        
        $response = Http::withHeaders($config['headers'] ?? [])
            ->timeout($config['timeout'] ?? 30)
            ->{$config['method'] ?? 'get'}($url, $config['body'] ?? []);
        
        return [
            'status_code' => $response->status(),
            'headers' => $response->headers(),
            'body' => $response->json() ?? $response->body()
        ];
    }
}
```

#### 6. **Suspension System**

**When Workflows Suspend:**
- **Wait Node** (delay or wait for webhook)
- **Approval Node** (human approval required)
- **External Trigger** (wait for external event)

**Suspension Flow:**
```php
// Node requests suspension
class WaitNode extends BaseNode {
    public function execute(array $input, RunContext $context): array {
        if ($this->config['mode'] === 'webhook') {
            // Generate unique resume URL
            $resumeToken = Str::uuid();
            
            // Store suspension state
            Suspension::create([
                'execution_id' => $context->execution->id,
                'node_id' => $this->id,
                'resume_token' => $resumeToken,
                'expires_at' => now()->addDays(7)
            ]);
            
            // Return suspension signal
            throw new WorkflowSuspendedException([
                'resume_url' => url("/api/v1/webhook-wait/{$resumeToken}")
            ]);
        }
        
        // For time-based wait, schedule resume job
        if ($this->config['mode'] === 'time') {
            $delay = $this->config['duration']; // e.g., '5 minutes'
            
            ResumeWorkflowJob::dispatch($context->execution)
                ->delay(Carbon::parse($delay));
            
            throw new WorkflowSuspendedException();
        }
    }
}

// Webhook receives resume request
class WaitWebhookController {
    public function resume(string $resumeToken) {
        $suspension = Suspension::where('resume_token', $resumeToken)->firstOrFail();
        $execution = $suspension->execution;
        
        // Resume workflow with webhook data
        ResumeWorkflowJob::dispatch($execution, request()->all());
        
        return response()->json(['success' => true]);
    }
}
```

#### 7. **Checkpoint System**

**Purpose:** Save execution state to allow resume after crash

**Implementation:**
```php
class CheckpointStore {
    public function save(RunContext $context): void {
        $context->execution->update([
            'checkpoint_data' => [
                'executed_nodes' => $context->executedNodes,
                'node_results' => $context->nodeResults,
                'current_node' => $context->currentNode,
                'timestamp' => now()
            ]
        ]);
    }
    
    public function restore(Execution $execution): RunContext {
        $checkpoint = $execution->checkpoint_data;
        
        $context = new RunContext($execution);
        $context->executedNodes = $checkpoint['executed_nodes'];
        $context->nodeResults = $checkpoint['node_results'];
        
        return $context;
    }
}
```

---

## Data Flow

### Request Flow (API)

```
1. HTTP Request
   ↓
2. Nginx/Apache
   ↓
3. public/index.php (Laravel Entry)
   ↓
4. Middleware Stack
   - CORS
   - Authentication (Passport)
   - Workspace Context
   - Rate Limiting
   ↓
5. Router (routes/api.php)
   ↓
6. Controller Method
   ↓
7. Form Request Validation
   ↓
8. Service Layer
   ↓
9. Model/Database
   ↓
10. API Resource (Response Formatting)
    ↓
11. JSON Response
```

### Workflow Execution Flow

```
1. Trigger (Webhook/Schedule/Manual)
   ↓
2. ExecutionService::trigger()
   - Create Execution record
   - Set status = 'running'
   ↓
3. Dispatch ExecuteWorkflowJob to Queue
   ↓
4. Queue Worker picks up job
   ↓
5. WorkflowEngine::run()
   ↓
6. GraphCompiler::compile()
   - Parse workflow definition
   - Build execution graph
   ↓
7. ExecutionScheduler::nextNode()
   - Find next executable node
   ↓
8. Node::execute()
   - Run node logic
   - Call external APIs if needed
   - Transform data
   ↓
9. Save Node Result to DB
   - node_executions table
   ↓
10. Repeat steps 7-9 until:
    - All nodes complete (success)
    - Node fails (error)
    - Node suspends (waiting)
    ↓
11. ExecutionFinalizer::complete()
    - Update execution status
    - Calculate credits used
    - Send notifications
    - Trigger events
```

### Data Interpolation

**Nodes can reference data from previous nodes:**

```javascript
// Node config with expressions
{
  "url": "https://api.example.com/users/{{$node.trigger.body.user_id}}",
  "body": {
    "email": "{{$node.http_request.response.email}}",
    "name": "{{$node.http_request.response.name}}"
  }
}

// Engine interpolates at runtime
$url = InterpolationEngine::interpolate(
    "{{$node.trigger.body.user_id}}",
    $context
);
// Result: "https://api.example.com/users/12345"
```

---

## Database Architecture

### Multi-Tenancy Strategy

**Approach:** Row-Level Isolation via `workspace_id`

**Every workspace-scoped table has:**
```sql
CREATE TABLE workflows (
    id UUID PRIMARY KEY,
    workspace_id UUID NOT NULL REFERENCES workspaces(id) ON DELETE CASCADE,
    name VARCHAR(255),
    definition JSONB,
    -- ...
    
    INDEX idx_workspace_workflows (workspace_id)
);
```

**Query Scoping (via Middleware):**
```php
// Middleware sets workspace context
class WorkspaceContextMiddleware {
    public function handle($request, $next) {
        $workspace = Workspace::where('slug', $request->route('workspace'))
            ->whereHas('members', fn($q) => $q->where('user_id', auth()->id()))
            ->firstOrFail();
        
        // Store in request
        $request->attributes->set('workspace', $workspace);
        
        // Global scope all queries to this workspace
        Workflow::addGlobalScope('workspace', function($query) use ($workspace) {
            $query->where('workspace_id', $workspace->id);
        });
        
        return $next($request);
    }
}
```

### Key Tables

#### workflows
```sql
CREATE TABLE workflows (
    id UUID PRIMARY KEY,
    workspace_id UUID NOT NULL,
    name VARCHAR(255) NOT NULL,
    description TEXT,
    definition JSONB NOT NULL,  -- Node graph
    is_active BOOLEAN DEFAULT false,
    settings JSONB,  -- timeout, retry, etc.
    created_at TIMESTAMP,
    updated_at TIMESTAMP,
    
    INDEX idx_workspace_active (workspace_id, is_active)
);
```

#### executions
```sql
CREATE TABLE executions (
    id UUID PRIMARY KEY,
    workflow_id UUID NOT NULL,
    workspace_id UUID NOT NULL,
    user_id UUID,
    mode VARCHAR(50),  -- webhook, manual, schedule, polling
    status VARCHAR(50),  -- running, success, failed, cancelled, waiting
    input_data JSONB,
    output_data JSONB,
    error_data JSONB,
    checkpoint_data JSONB,  -- For resume
    started_at TIMESTAMP,
    finished_at TIMESTAMP,
    duration_seconds INTEGER,
    credits_used INTEGER DEFAULT 0,
    
    INDEX idx_workflow_status (workflow_id, status),
    INDEX idx_workspace_date (workspace_id, created_at DESC)
);
```

#### node_executions
```sql
CREATE TABLE node_executions (
    id UUID PRIMARY KEY,
    execution_id UUID NOT NULL,
    node_id VARCHAR(255) NOT NULL,
    node_type VARCHAR(100),
    input_data JSONB,
    output_data JSONB,
    error_data JSONB,
    status VARCHAR(50),
    started_at TIMESTAMP,
    finished_at TIMESTAMP,
    duration_ms INTEGER,
    
    INDEX idx_execution_nodes (execution_id)
);
```

#### credentials
```sql
CREATE TABLE credentials (
    id UUID PRIMARY KEY,
    workspace_id UUID NOT NULL,
    name VARCHAR(255) NOT NULL,
    type VARCHAR(100) NOT NULL,  -- sendgrid, stripe, oauth_google, etc.
    data TEXT NOT NULL,  -- Encrypted JSON
    is_oauth BOOLEAN DEFAULT false,
    oauth_data JSONB,
    created_at TIMESTAMP,
    
    INDEX idx_workspace_type (workspace_id, type)
);
```

### Indexes Strategy

**Primary Indexes:**
- All primary keys (UUID)
- Foreign keys (workspace_id, workflow_id, etc.)

**Query Optimization Indexes:**
```sql
-- Most common queries
INDEX idx_executions_workspace_date ON executions(workspace_id, created_at DESC);
INDEX idx_executions_workflow_status ON executions(workflow_id, status);
INDEX idx_workflows_workspace_active ON workflows(workspace_id, is_active);
INDEX idx_activity_logs_workspace_date ON activity_logs(workspace_id, created_at DESC);
```

### JSONB Usage

**Why JSONB:**
- Flexible node configurations
- Workflow definitions are JSON
- Execution data varies by workflow
- Fast queries with GIN indexes

**Example Query:**
```sql
-- Find workflows using specific node type
SELECT * FROM workflows
WHERE definition @> '{"nodes": [{"type": "email"}]}'::jsonb;

-- Query node config
SELECT definition->'nodes'->0->'config' 
FROM workflows 
WHERE id = 'uuid';
```

---

## Queue System

### Queue Architecture

```
┌──────────────────────────────────────────────────┐
│                  Laravel Application                     │
│  ExecuteWorkflowJob::dispatch($execution)               │
└───────────────────────┬───────────────────────────┘
                          │ Serializes job
                          ↓
┌──────────────────────────────────────────────────┐
│                  Redis Queue                            │
│  List: queue:default                                    │
│  List: queue:long-running                               │
│  List: queue:notifications                              │
└────────────────────────┬─────────────────────────┘
                          │ Workers poll
                          ↓
┌──────────────────────────────────────────────────┐
│              Horizon (Queue Workers)                    │
│  Worker 1: Processing default queue                     │
│  Worker 2: Processing long-running queue                │
│  Worker 3: Processing notifications queue               │
└────────────────────────┬─────────────────────────┘
                          │ Executes job
                          ↓
┌──────────────────────────────────────────────────┐
│              Job Execution                              │
│  WorkflowEngine::run()                                  │
└──────────────────────────────────────────────────┘
```

### Queue Configuration

**config/queue.php:**
```php
'connections' => [
    'redis' => [
        'driver' => 'redis',
        'connection' => 'default',
        'queue' => env('REDIS_QUEUE', 'default'),
        'retry_after' => 300,  // 5 minutes
        'block_for' => null,
    ],
],

'failed' => [
    'driver' => 'database',
    'table' => 'failed_jobs',
],
```

### Queue Priority

```php
// High priority (notifications, quick tasks)
NotificationJob::dispatch()->onQueue('notifications');

// Normal priority (workflow executions)
ExecuteWorkflowJob::dispatch($execution)->onQueue('default');

// Low priority (long-running, data processing)
ProcessLargeDatasetJob::dispatch()->onQueue('long-running');
```

### Job Retry Strategy

```php
class ExecuteWorkflowJob implements ShouldQueue {
    public $tries = 3;  // Retry up to 3 times
    public $timeout = 300;  // 5 minutes
    public $backoff = [10, 30, 60];  // Retry after 10s, 30s, 60s
    
    public function handle() {
        try {
            WorkflowEngine::run($this->execution);
        } catch (TransientException $e) {
            // Retry
            $this->release(30);  // Retry in 30 seconds
        } catch (FatalException $e) {
            // Don't retry, mark as failed
            $this->fail($e);
        }
    }
    
    public function failed(Throwable $exception) {
        // Mark execution as failed
        $this->execution->update([
            'status' => ExecutionStatus::Failed,
            'error_data' => [
                'message' => $exception->getMessage(),
                'trace' => $exception->getTraceAsString()
            ]
        ]);
    }
}
```

---

## Caching Strategy

### Cache Layers

**1. Application Cache (Redis)**
```php
// Cache workspace data
$workspace = Cache::remember(
    "workspace:{$workspaceId}",
    3600,  // 1 hour
    fn() => Workspace::find($workspaceId)
);

// Cache user permissions
$permissions = Cache::remember(
    "user:{$userId}:workspace:{$workspaceId}:permissions",
    600,  // 10 minutes
    fn() => $user->getPermissionsForWorkspace($workspaceId)
);
```

**2. Query Result Cache**
```php
// Cache expensive queries
$stats = Cache::remember(
    "workspace:{$workspaceId}:stats:7d",
    300,  // 5 minutes
    fn() => DB::table('executions')
        ->where('workspace_id', $workspaceId)
        ->where('created_at', '>=', now()->subDays(7))
        ->selectRaw('COUNT(*) as total, AVG(duration_seconds) as avg_duration')
        ->first()
);
```

**3. Model Cache (via traits)**
```php
class Workspace extends Model {
    use Cacheable;
    
    protected $cacheFor = 3600;  // 1 hour
    protected $cachePrefix = 'workspace';
}
```

### Cache Invalidation

**Event-driven:**
```php
class WorkflowObserver {
    public function updated(Workflow $workflow) {
        // Invalidate workspace workflows cache
        Cache::forget("workspace:{$workflow->workspace_id}:workflows");
        Cache::forget("workflow:{$workflow->id}");
    }
}
```

---

## Security Architecture

### Authentication Flow

```
1. User sends credentials
   POST /api/v1/auth/login
   ↓
2. AuthController validates
   - Check email/password
   - Verify email is confirmed
   ↓
3. Generate tokens (Passport)
   - Access token (JWT, 1 hour expiry)
   - Refresh token (30 days)
   ↓
4. Return tokens
   {"access_token": "...", "refresh_token": "..."}
   ↓
5. Client stores tokens
   - localStorage or cookie
   ↓
6. Client sends access token in headers
   Authorization: Bearer {access_token}
   ↓
7. Middleware validates token
   - Check signature
   - Check expiry
   - Load user
   ↓
8. Request proceeds
```

### Authorization (RBAC)

**Roles:**
- Owner (full control)
- Admin (manage workspace, members)
- Editor (create, edit workflows)
- Viewer (read-only)

**Permission Check:**
```php
class WorkflowController {
    public function update(Workflow $workflow) {
        // Check permission
        if (!auth()->user()->can(Permission::WORKFLOWS_EDIT, $workflow->workspace)) {
            abort(403, 'You do not have permission to edit workflows');
        }
        
        // Proceed with update
        $workflow->update(request()->validated());
    }
}
```

### Data Encryption

**Credentials:**
```php
class Credential extends Model {
    protected $casts = [
        'data' => 'encrypted:array',  // Laravel automatic encryption
    ];
    
    // When saving
    $credential->data = ['api_key' => 'sk-...'];
    $credential->save();
    // Stored encrypted in database
    
    // When retrieving
    $apiKey = $credential->data['api_key'];
    // Automatically decrypted
}
```

---

## Scalability Design

### Horizontal Scaling

**Application Servers:**
```
Load Balancer (nginx)
    |
    +-- App Server 1 (Laravel)
    +-- App Server 2 (Laravel)
    +-- App Server 3 (Laravel)
```

**Queue Workers:**
```
Supervisor
    |
    +-- Worker Process 1 (queue:work)
    +-- Worker Process 2 (queue:work)
    +-- Worker Process 3 (queue:work)
    +-- Worker Process 4 (queue:work)
```

### Database Scaling

**Read Replicas:**
```php
// config/database.php
'connections' => [
    'pgsql' => [
        'read' => [
            'host' => ['read-replica-1.db', 'read-replica-2.db'],
        ],
        'write' => [
            'host' => ['master.db'],
        ],
    ],
],
```

**Connection Pooling:**
- Use PgBouncer for PostgreSQL
- Reduce connection overhead
- Handle high concurrency

### Caching for Scale

**Redis Cluster:**
- Distributed cache
- Session storage
- Queue backend

---

**This architecture supports:**
- ✅ Thousands of concurrent workflows
- ✅ Millions of executions per day
- ✅ Multi-region deployment
- ✅ High availability (99.9% uptime)
- ✅ Horizontal scaling
