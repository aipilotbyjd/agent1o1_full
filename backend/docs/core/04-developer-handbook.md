# 👨‍💻 Developer Handbook

**Complete guide for developers working on LinkFlow**

---

## Table of Contents

1. [Getting Started](#getting-started)
2. [Common Development Tasks](#common-development-tasks)
3. [Code Organization](#code-organization)
4. [Testing](#testing)
5. [Debugging](#debugging)
6. [Best Practices](#best-practices)
7. [Troubleshooting](#troubleshooting)

---

## Getting Started

### Prerequisites

- **PHP 8.3+**
- **Composer**
- **PostgreSQL 14+** with pgvector extension
- **Redis 6+**
- **Node.js 18+** (for frontend)

### Initial Setup

```bash
# 1. Clone and install
git clone <repository>
cd linkflow
composer install

# 2. Environment setup
cp .env.example .env
php artisan key:generate

# 3. Database setup
createdb linkflow  # PostgreSQL
psql linkflow -c "CREATE EXTENSION vector;"  # pgvector

# 4. Run migrations and seeders
php artisan migrate
php artisan db:seed

# 5. Passport setup (OAuth2)
php artisan passport:install

# 6. Start services
php artisan horizon  # Queue worker
php artisan serve    # Development server
```

### IDE Setup (VS Code)

**Recommended Extensions:**
- PHP Intelephense
- Laravel Blade Snippets
- Laravel goto view
- PHPUnit Test Explorer
- GitLens

**settings.json:**
```json
{
  "php.suggest.basic": false,
  "intelephense.files.maxSize": 5000000,
  "editor.formatOnSave": true
}
```

---

## Common Development Tasks

### Adding a New API Endpoint

**1. Create Controller Method**
```php
// app/Http/Controllers/Api/V1/MyController.php
namespace App\Http\Controllers\Api\V1;

class MyController extends Controller
{
    public function index(Request $request)
    {
        // Workspace is already loaded by middleware
        $workspace = $request->attributes->get('workspace');
        
        $items = MyModel::where('workspace_id', $workspace->id)
            ->paginate(20);
        
        return MyResource::collection($items);
    }
}
```

**2. Add Route**
```php
// routes/api.php
Route::prefix('workspaces/{workspace}')
    ->middleware(['workspace.role'])
    ->group(function () {
        Route::get('my-endpoint', [MyController::class, 'index']);
    });
```

**3. Create Resource (Response Transformer)**
```php
// app/Http/Resources/MyResource.php
namespace App\Http\Resources;

class MyResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'created_at' => $this->created_at->toIso8601String(),
        ];
    }
}
```

**4. Add Tests**
```php
// tests/Feature/MyEndpointTest.php
test('can list items', function () {
    $user = User::factory()->create();
    $workspace = Workspace::factory()->create();
    $workspace->addMember($user, 'editor');
    
    $response = $this->actingAs($user)
        ->getJson("/api/v1/workspaces/{$workspace->id}/my-endpoint");
    
    $response->assertOk()
        ->assertJsonStructure(['data']);
});
```

### Creating a New Workflow Node

**1. Create Node Class**
```bash
php artisan make:node MyCustomNode
```

**2. Implement Node Logic**
```php
// app/Engine/Nodes/Apps/MyCustomNode.php
namespace App\Engine\Nodes\Apps;

use App\Engine\Nodes\BaseNode;
use App\Engine\RunContext;

class MyCustomNode extends BaseNode
{
    public function execute(array $input, RunContext $context): array
    {
        // Get node configuration
        $apiUrl = $this->config['api_url'];
        $timeout = $this->config['timeout'] ?? 30;
        
        // Get credential if needed
        $credential = $this->getCredential('my_service');
        
        // Perform the node's task
        $result = // ... your logic here
        
        // Log for debugging
        $this->log('Executed successfully', ['result' => $result]);
        
        // Return output data
        return [
            'success' => true,
            'data' => $result,
            'timestamp' => now()->toIso8601String()
        ];
    }
    
    public function validate(): array
    {
        // Validation rules for node config
        return [
            'api_url' => 'required|url',
            'timeout' => 'integer|min:1|max:300'
        ];
    }
}
```

**3. Register Node in Seeder**
```php
// database/seeders/NodeSeeder.php
[
    'type' => 'my_custom_node',
    'name' => 'My Custom Node',
    'category_id' => $appsCategory->id,
    'icon' => 'IconName',
    'color' => '#3B82F6',
    'description' => 'Does something amazing',
    'properties' => [
        [
            'name' => 'api_url',
            'displayName' => 'API URL',
            'type' => 'string',
            'required' => true,
            'placeholder' => 'https://api.example.com'
        ],
        [
            'name' => 'timeout',
            'displayName' => 'Timeout (seconds)',
            'type' => 'number',
            'default' => 30
        ]
    ],
    'credentials' => ['my_service'],  // Optional
    'outputs' => [
        ['name' => 'success', 'type' => 'boolean'],
        ['name' => 'data', 'type' => 'object']
    ]
]
```

**4. Run Seeder**
```bash
php artisan db:seed --class=NodeSeeder
```

### Adding a New Service

**1. Create Service Class**
```php
// app/Services/MyService.php
namespace App\Services;

class MyService
{
    public function __construct(
        protected MyRepository $repository,
        protected CacheService $cache
    ) {}
    
    public function create(array $data): MyModel
    {
        // Business logic here
        $model = $this->repository->create($data);
        
        // Clear cache
        $this->cache->forget("workspace:{$model->workspace_id}:items");
        
        // Fire event
        event(new MyModelCreated($model));
        
        return $model;
    }
}
```

**2. Register in Service Provider**
```php
// app/Providers/AppServiceProvider.php
public function register()
{
    $this->app->singleton(MyService::class);
}
```

### Creating a Background Job

**1. Generate Job**
```bash
php artisan make:job ProcessLargeDataset
```

**2. Implement Job**
```php
// app/Jobs/ProcessLargeDataset.php
namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class ProcessLargeDataset implements ShouldQueue
{
    use InteractsWithQueue, Queueable;
    
    public $tries = 3;
    public $timeout = 600;  // 10 minutes
    
    public function __construct(
        public Workspace $workspace,
        public string $filePath
    ) {}
    
    public function handle()
    {
        $data = Storage::get($this->filePath);
        
        // Process data in chunks
        collect(json_decode($data, true))
            ->chunk(100)
            ->each(function ($chunk) {
                // Process chunk
                DB::table('items')->insert($chunk->toArray());
            });
    }
    
    public function failed(Throwable $exception)
    {
        // Handle failure
        Log::error('Job failed', [
            'workspace_id' => $this->workspace->id,
            'file' => $this->filePath,
            'error' => $exception->getMessage()
        ]);
    }
}
```

**3. Dispatch Job**
```php
ProcessLargeDataset::dispatch($workspace, $filePath)
    ->onQueue('long-running');
```

---

## Code Organization

### Directory Structure Explained

```
app/
├── Agents/              # AI Agent system
│   ├── AgentManager.php
│   └── Skills/
├── Console/Commands/    # Artisan commands
├── Engine/              # Workflow execution engine
│   ├── Execution/       # Execution logic
│   ├── Graph/           # Graph compilation
│   ├── Nodes/           # All node types
│   └── WorkflowEngine.php
├── Enums/               # Type-safe enums
│   ├── ExecutionStatus.php
│   ├── WorkspaceMemberRole.php
│   └── ...
├── Events/              # Domain events
├── Exceptions/          # Custom exceptions
├── Http/
│   ├── Controllers/     # Request handlers
│   ├── Middleware/      # Request filters
│   ├── Requests/        # Form validation
│   └── Resources/       # Response transformers
├── Jobs/                # Background jobs
├── Models/              # Eloquent models
├── Notifications/       # User notifications
├── Services/            # Business logic
└── Traits/              # Reusable traits
```

### Naming Conventions

**Models:**
- Singular, PascalCase: `Workflow`, `Execution`, `User`
- Match table names (plural): `workflows`, `executions`, `users`

**Controllers:**
- PascalCase + Controller: `WorkflowController`
- Resourceful names: `index`, `store`, `show`, `update`, `destroy`

**Services:**
- PascalCase + Service: `WorkflowService`, `ExecutionService`

**Jobs:**
- Verb + Noun: `ExecuteWorkflow`, `ProcessExecution`

**Events:**
- Past tense: `WorkflowCreated`, `ExecutionCompleted`

### Model Relationships

**Example:**
```php
class Workflow extends Model
{
    // Belongs to workspace
    public function workspace()
    {
        return $this->belongsTo(Workspace::class);
    }
    
    // Has many executions
    public function executions()
    {
        return $this->hasMany(Execution::class);
    }
    
    // Has one webhook (optional)
    public function webhook()
    {
        return $this->hasOne(Webhook::class);
    }
    
    // Belongs to user (creator)
    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
```

---

## Testing

### Running Tests

```bash
# Run all tests
php artisan test

# Run specific test file
php artisan test tests/Feature/WorkflowTest.php

# Run specific test method
php artisan test --filter=test_can_create_workflow

# Run with coverage
php artisan test --coverage
```

### Writing Tests (Pest PHP)

**Feature Test Example:**
```php
// tests/Feature/WorkflowTest.php
uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->workspace = Workspace::factory()->create();
    $this->workspace->addMember($this->user, 'editor');
});

test('can create workflow', function () {
    $response = $this->actingAs($this->user)
        ->postJson("/api/v1/workspaces/{$this->workspace->id}/workflows", [
            'name' => 'Test Workflow',
            'definition' => [
                'nodes' => [],
                'connections' => []
            ]
        ]);
    
    $response->assertCreated()
        ->assertJsonStructure(['data' => ['id', 'name']]);
    
    expect($this->workspace->workflows()->count())->toBe(1);
});

test('cannot create workflow without permission', function () {
    $viewer = User::factory()->create();
    $this->workspace->addMember($viewer, 'viewer');
    
    $response = $this->actingAs($viewer)
        ->postJson("/api/v1/workspaces/{$this->workspace->id}/workflows", [
            'name' => 'Test Workflow'
        ]);
    
    $response->assertForbidden();
});
```

**Unit Test Example:**
```php
// tests/Unit/WorkflowEngineTest.php
test('graph compiler builds correct dependency tree', function () {
    $definition = [
        'nodes' => [
            ['id' => 'node1', 'type' => 'trigger'],
            ['id' => 'node2', 'type' => 'http'],
            ['id' => 'node3', 'type' => 'email']
        ],
        'connections' => [
            ['from' => 'node1', 'to' => 'node2'],
            ['from' => 'node2', 'to' => 'node3']
        ]
    ];
    
    $graph = GraphCompiler::compile($definition);
    
    expect($graph->getNode('node1')->dependencies)->toBeEmpty();
    expect($graph->getNode('node2')->dependencies)->toBe(['node1']);
    expect($graph->getNode('node3')->dependencies)->toBe(['node2']);
});
```

### Test Database

**Uses separate test database:**
```env
# .env.testing
DB_DATABASE=linkflow_test
```

**Refresh database for each test:**
```php
uses(RefreshDatabase::class);
```

---

## Debugging

### Laravel Debugbar

```bash
composer require barryvdh/laravel-debugbar --dev
```

**View in browser:**
- SQL queries
- Route info
- View data
- Session data

### Logging

**Log Levels:**
```php
Log::emergency('System is down');
Log::alert('Action required');
Log::critical('Critical condition');
Log::error('Error occurred');
Log::warning('Warning message');
Log::notice('Normal but significant');
Log::info('Informational message');
Log::debug('Debug-level messages');
```

**Context Logging:**
```php
Log::info('Workflow executed', [
    'workflow_id' => $workflow->id,
    'execution_id' => $execution->id,
    'duration' => $duration,
    'status' => $status
]);
```

**Log Channels:**
```php
// Log to specific channel
Log::channel('workflow')->info('Message');

// Log to multiple channels
Log::stack(['single', 'slack'])->info('Message');
```

### Debugging Workflow Execution

**1. Enable debug logging:**
```php
// In WorkflowEngine.php
protected function executeNode(Node $node, RunContext $context)
{
    Log::debug("Executing node: {$node->id}", [
        'type' => $node->type,
        'input' => $input,
        'config' => $node->config
    ]);
    
    $result = $node->execute($input, $context);
    
    Log::debug("Node completed: {$node->id}", [
        'output' => $result
    ]);
    
    return $result;
}
```

**2. Check node execution logs:**
```sql
SELECT * FROM node_executions 
WHERE execution_id = 'uuid'
ORDER BY created_at;
```

**3. Use Tinker:**
```bash
php artisan tinker

>>> $execution = Execution::find('uuid');
>>> $execution->nodeExecutions->pluck('status', 'node_id');
>>> $execution->error_data
```

### Horizon (Queue Dashboard)

**Access:** `http://localhost/horizon`

**Monitor:**
- Queue lengths
- Job throughput
- Failed jobs
- Job metrics

---

## Best Practices

### Code Style

**Follow PSR-12:**
```bash
composer require --dev laravel/pint
./vendor/bin/pint
```

### Database Queries

**❌ Bad (N+1 Problem):**
```php
$workflows = Workflow::all();
foreach ($workflows as $workflow) {
    echo $workflow->creator->name;  // N+1 queries
}
```

**✅ Good (Eager Loading):**
```php
$workflows = Workflow::with('creator')->get();
foreach ($workflows as $workflow) {
    echo $workflow->creator->name;  // 2 queries total
}
```

### API Response Standards

**Success Response:**
```php
return response()->json([
    'data' => $resource,
    'message' => 'Operation successful'
], 200);
```

**Error Response:**
```php
return response()->json([
    'message' => 'Validation failed',
    'errors' => [
        'email' => ['The email field is required']
    ]
], 422);
```

### Security Checklist

- ✅ Always use Eloquent or Query Builder (prevents SQL injection)
- ✅ Validate all input with Form Requests
- ✅ Use `@csrf` in forms
- ✅ Escape output with `{{ }}` in Blade (prevents XSS)
- ✅ Use HTTPS in production
- ✅ Rate limit API endpoints
- ✅ Encrypt sensitive data (credentials)
- ✅ Use workspace scoping middleware

---

## Troubleshooting

### Common Issues

**1. Queue not processing:**
```bash
# Check if Horizon is running
php artisan horizon:status

# Restart Horizon
php artisan horizon:terminate
php artisan horizon
```

**2. Workflow execution fails:**
```sql
-- Check execution status
SELECT id, status, error_data FROM executions WHERE id = 'uuid';

-- Check failed jobs
SELECT * FROM failed_jobs ORDER BY failed_at DESC LIMIT 10;
```

**3. Permission denied:**
```php
// Check user role
$member = WorkspaceMember::where('workspace_id', $workspaceId)
    ->where('user_id', $userId)
    ->first();
    
dd($member->role, $member->permissions);
```

**4. Node not found:**
```bash
# Re-seed nodes
php artisan db:seed --class=NodeSeeder
```

### Performance Issues

**1. Slow queries:**
```bash
# Enable query logging
DB::enableQueryLog();

// ... run code ...

dd(DB::getQueryLog());
```

**2. Memory issues:**
```php
// Process large datasets in chunks
Model::chunk(100, function ($items) {
    foreach ($items as $item) {
        // Process item
    }
});
```

**3. Cache everything possible:**
```php
Cache::remember('key', 3600, function () {
    return expensive_operation();
});
```

---

## Useful Commands

```bash
# Database
php artisan migrate
php artisan migrate:rollback
php artisan db:seed
php artisan migrate:fresh --seed

# Queue
php artisan horizon
php artisan queue:work
php artisan queue:failed
php artisan queue:retry all

# Cache
php artisan cache:clear
php artisan config:clear
php artisan route:clear
php artisan view:clear

# Code generation
php artisan make:model MyModel -mfsc  # model, migration, factory, seeder, controller
php artisan make:controller MyController --resource
php artisan make:request MyRequest
php artisan make:job MyJob
php artisan make:event MyEvent
php artisan make:listener MyListener

# Testing
php artisan test
php artisan test --parallel
php artisan test --coverage

# Code quality
./vendor/bin/pint  # Format code
./vendor/bin/phpstan analyse  # Static analysis
```

---

**Happy Coding! 🚀**

For questions, check:
- [PROJECT_OVERVIEW.md](../core/01-project-overview.md) - High-level overview
- [ARCHITECTURE_DEEP_DIVE.md](../core/02-architecture.md) - Technical details
- [WORKFLOW_ENGINE_GUIDE.md](../core/03-workflow-engine.md) - Engine specifics
