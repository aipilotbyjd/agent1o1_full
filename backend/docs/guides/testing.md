# 🧪 Testing Guide

**Comprehensive testing guide for LinkFlow**

---

## Overview

This guide covers testing strategies, tools, and best practices for ensuring LinkFlow's reliability and quality.

**Testing Philosophy:**
- Write tests before fixing bugs (TDD for bug fixes)
- Test behavior, not implementation
- Aim for high coverage on critical paths
- Fast unit tests, fewer integration tests
- Keep tests simple and maintainable

---

## Table of Contents

1. [Testing Stack](#testing-stack)
2. [Setup](#setup)
3. [Running Tests](#running-tests)
4. [Unit Testing](#unit-testing)
5. [Feature Testing](#feature-testing)
6. [Database Testing](#database-testing)
7. [API Testing](#api-testing)
8. [Testing Workflows](#testing-workflows)
9. [Mocking External Services](#mocking-external-services)
10. [Test Coverage](#test-coverage)
11. [CI Integration](#ci-integration)
12. [Best Practices](#best-practices)
13. [Common Patterns](#common-patterns)
14. [Troubleshooting Tests](#troubleshooting-tests)

---

## Testing Stack

**Primary Framework:** [Pest PHP](https://pestphp.com/)

Pest is a modern PHP testing framework built on top of PHPUnit with a focus on simplicity.

**Additional Tools:**
- **Laravel Testing Utilities** - Database factories, HTTP testing, assertions
- **Mockery** - Mocking framework
- **Faker** - Generate fake data
- **Laravel Sanctum/Passport** - Authentication testing

**Why Pest over PHPUnit?**
- Cleaner syntax
- Better readability
- Faster to write tests
- Built-in parallel testing
- Great error messages

---

## Setup

### Install Pest

Pest should already be installed via composer. If not:

```bash
composer require pestphp/pest --dev --with-all-dependencies
composer require pestphp/pest-plugin-laravel --dev
php artisan pest:install
```

### Configure Test Database

Create a separate test database:

```bash
createdb linkflow_test
psql linkflow_test -c "CREATE EXTENSION vector;"
```

**Update `phpunit.xml`:**

```xml
<phpunit>
    <testsuites>
        <testsuite name="Unit">
            <directory suffix="Test.php">./tests/Unit</directory>
        </testsuite>
        <testsuite name="Feature">
            <directory suffix="Test.php">./tests/Feature</directory>
        </testsuite>
    </testsuites>
    
    <php>
        <env name="APP_ENV" value="testing"/>
        <env name="DB_CONNECTION" value="pgsql"/>
        <env name="DB_DATABASE" value="linkflow_test"/>
        <env name="CACHE_DRIVER" value="array"/>
        <env name="QUEUE_CONNECTION" value="sync"/>
        <env name="SESSION_DRIVER" value="array"/>
        <env name="MAIL_MAILER" value="array"/>
    </php>
</phpunit>
```

### Directory Structure

```
tests/
├── Feature/           # Integration tests (HTTP, database)
│   ├── Auth/
│   ├── Workflows/
│   ├── Executions/
│   └── Webhooks/
├── Unit/              # Unit tests (isolated logic)
│   ├── Engine/
│   ├── Services/
│   └── Nodes/
├── Pest.php           # Global test helpers
└── TestCase.php       # Base test case
```

---

## Running Tests

### Basic Commands

```bash
# Run all tests
php artisan test
# or
./vendor/bin/pest

# Run specific test file
php artisan test tests/Feature/WorkflowTest.php

# Run specific test
php artisan test --filter test_user_can_create_workflow

# Run tests in parallel (faster)
php artisan test --parallel

# Run with coverage
php artisan test --coverage

# Run with coverage minimum threshold
php artisan test --coverage --min=80
```

### Watch Mode (Auto-run on file change)

```bash
# Install pest plugin
composer require pestphp/pest-plugin-watch --dev

# Watch mode
./vendor/bin/pest --watch
```

### Filtering Tests

```bash
# Run only Unit tests
php artisan test --testsuite=Unit

# Run only Feature tests
php artisan test --testsuite=Feature

# Run tests matching pattern
php artisan test --filter=Workflow

# Exclude tests
php artisan test --exclude-group=slow
```

---

## Unit Testing

Unit tests test individual classes/methods in isolation.

### Example: Testing a Service

**Service:**
```php
// app/Services/WorkflowValidator.php
namespace App\Services;

class WorkflowValidator
{
    public function validateDefinition(array $definition): bool
    {
        if (empty($definition['nodes'])) {
            throw new \InvalidArgumentException('Workflow must have at least one node');
        }
        
        if (empty($definition['trigger_type'])) {
            throw new \InvalidArgumentException('Workflow must have a trigger type');
        }
        
        // Check for cycles
        if ($this->hasCycles($definition)) {
            throw new \InvalidArgumentException('Workflow cannot have cycles');
        }
        
        return true;
    }
    
    private function hasCycles(array $definition): bool
    {
        // Implementation...
        return false;
    }
}
```

**Test:**
```php
// tests/Unit/Services/WorkflowValidatorTest.php
use App\Services\WorkflowValidator;

it('validates workflow definition successfully', function () {
    $validator = new WorkflowValidator();
    
    $definition = [
        'trigger_type' => 'webhook',
        'nodes' => [
            ['id' => '1', 'type' => 'webhook'],
            ['id' => '2', 'type' => 'http'],
        ],
    ];
    
    expect($validator->validateDefinition($definition))->toBeTrue();
});

it('throws exception when nodes are empty', function () {
    $validator = new WorkflowValidator();
    
    $definition = [
        'trigger_type' => 'webhook',
        'nodes' => [],
    ];
    
    $validator->validateDefinition($definition);
})->throws(\InvalidArgumentException::class, 'Workflow must have at least one node');

it('throws exception when trigger type is missing', function () {
    $validator = new WorkflowValidator();
    
    $definition = [
        'nodes' => [['id' => '1', 'type' => 'webhook']],
    ];
    
    $validator->validateDefinition($definition);
})->throws(\InvalidArgumentException::class, 'Workflow must have a trigger type');
```

---

### Testing Node Executors

```php
// tests/Unit/Engine/Nodes/HttpNodeTest.php
use App\Engine\Nodes\Apps\HttpNode;
use App\Engine\RunContext;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    $this->context = Mockery::mock(RunContext::class);
});

it('executes HTTP GET request successfully', function () {
    Http::fake([
        'https://api.example.com/users' => Http::response(['id' => 1, 'name' => 'John'], 200),
    ]);
    
    $node = new HttpNode();
    $config = [
        'method' => 'GET',
        'url' => 'https://api.example.com/users',
        'headers' => [],
    ];
    
    $result = $node->execute($config, [], $this->context);
    
    expect($result['status'])->toBe(200)
        ->and($result['data']['id'])->toBe(1)
        ->and($result['data']['name'])->toBe('John');
});

it('handles HTTP errors gracefully', function () {
    Http::fake([
        'https://api.example.com/error' => Http::response(['error' => 'Not Found'], 404),
    ]);
    
    $node = new HttpNode();
    $config = [
        'method' => 'GET',
        'url' => 'https://api.example.com/error',
    ];
    
    $result = $node->execute($config, [], $this->context);
    
    expect($result['status'])->toBe(404);
});

it('interpolates variables in URL', function () {
    Http::fake();
    
    $node = new HttpNode();
    $config = [
        'method' => 'GET',
        'url' => 'https://api.example.com/users/{{ userId }}',
    ];
    
    $variables = ['userId' => 123];
    
    $node->execute($config, $variables, $this->context);
    
    Http::assertSent(function ($request) {
        return $request->url() === 'https://api.example.com/users/123';
    });
});
```

---

## Feature Testing

Feature tests test complete features including HTTP requests, database interactions, and authentication.

### Example: Testing Workflow CRUD

```php
// tests/Feature/WorkflowTest.php
use App\Models\User;
use App\Models\Workspace;
use App\Models\Workflow;
use Laravel\Passport\Passport;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->workspace = Workspace::factory()->create();
    $this->workspace->members()->attach($this->user, ['role' => 'owner']);
    
    Passport::actingAs($this->user);
});

it('lists workflows for workspace', function () {
    Workflow::factory()->count(3)->create(['workspace_id' => $this->workspace->id]);
    
    $response = $this->getJson("/api/v1/workspaces/{$this->workspace->id}/workflows");
    
    $response->assertStatus(200)
        ->assertJsonCount(3, 'data');
});

it('creates a new workflow', function () {
    $workflowData = [
        'name' => 'Test Workflow',
        'description' => 'A test workflow',
        'trigger_type' => 'webhook',
        'trigger_config' => [],
        'nodes' => [
            ['id' => '1', 'type' => 'webhook', 'position' => ['x' => 0, 'y' => 0]],
            ['id' => '2', 'type' => 'http', 'position' => ['x' => 200, 'y' => 0]],
        ],
        'edges' => [
            ['id' => 'e1', 'source' => '1', 'target' => '2'],
        ],
    ];
    
    $response = $this->postJson(
        "/api/v1/workspaces/{$this->workspace->id}/workflows",
        $workflowData
    );
    
    $response->assertStatus(201)
        ->assertJsonPath('data.name', 'Test Workflow');
    
    $this->assertDatabaseHas('workflows', [
        'name' => 'Test Workflow',
        'workspace_id' => $this->workspace->id,
        'trigger_type' => 'webhook',
    ]);
});

it('updates an existing workflow', function () {
    $workflow = Workflow::factory()->create(['workspace_id' => $this->workspace->id]);
    
    $response = $this->putJson(
        "/api/v1/workspaces/{$this->workspace->id}/workflows/{$workflow->id}",
        ['name' => 'Updated Workflow']
    );
    
    $response->assertStatus(200)
        ->assertJsonPath('data.name', 'Updated Workflow');
    
    $this->assertDatabaseHas('workflows', [
        'id' => $workflow->id,
        'name' => 'Updated Workflow',
    ]);
});

it('deletes a workflow', function () {
    $workflow = Workflow::factory()->create(['workspace_id' => $this->workspace->id]);
    
    $response = $this->deleteJson(
        "/api/v1/workspaces/{$this->workspace->id}/workflows/{$workflow->id}"
    );
    
    $response->assertStatus(204);
    
    $this->assertSoftDeleted('workflows', ['id' => $workflow->id]);
});

it('prevents access to workflows from other workspaces', function () {
    $otherWorkspace = Workspace::factory()->create();
    $workflow = Workflow::factory()->create(['workspace_id' => $otherWorkspace->id]);
    
    $response = $this->getJson(
        "/api/v1/workspaces/{$this->workspace->id}/workflows/{$workflow->id}"
    );
    
    $response->assertStatus(404);
});

it('requires authentication', function () {
    Passport::actingAs(null); // Clear authentication
    
    $response = $this->getJson("/api/v1/workspaces/{$this->workspace->id}/workflows");
    
    $response->assertStatus(401);
});

it('requires workspace member permission', function () {
    $otherUser = User::factory()->create();
    Passport::actingAs($otherUser);
    
    $response = $this->getJson("/api/v1/workspaces/{$this->workspace->id}/workflows");
    
    $response->assertStatus(403);
});
```

---

## Database Testing

### Factories

**Define factories for all models:**

```php
// database/factories/WorkflowFactory.php
namespace Database\Factories;

use App\Models\Workflow;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Factories\Factory;

class WorkflowFactory extends Factory
{
    protected $model = Workflow::class;
    
    public function definition()
    {
        return [
            'workspace_id' => Workspace::factory(),
            'name' => $this->faker->sentence(3),
            'description' => $this->faker->paragraph(),
            'trigger_type' => $this->faker->randomElement(['webhook', 'schedule', 'manual']),
            'trigger_config' => [
                'url' => '/webhooks/' . $this->faker->uuid(),
            ],
            'definition' => [
                'nodes' => [
                    ['id' => '1', 'type' => 'webhook'],
                ],
                'edges' => [],
            ],
            'is_active' => true,
            'settings' => [],
        ];
    }
    
    public function inactive()
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }
    
    public function scheduled()
    {
        return $this->state(fn (array $attributes) => [
            'trigger_type' => 'schedule',
            'trigger_config' => [
                'cron' => '0 0 * * *',
            ],
        ]);
    }
}
```

**Usage:**

```php
// Create single workflow
$workflow = Workflow::factory()->create();

// Create with specific attributes
$workflow = Workflow::factory()->create(['name' => 'My Workflow']);

// Create multiple
$workflows = Workflow::factory()->count(10)->create();

// Create with relationship
$workflow = Workflow::factory()
    ->for($workspace)
    ->create();

// Create with state
$workflow = Workflow::factory()->inactive()->create();
```

---

### Database Transactions

Tests automatically run in database transactions and roll back after each test.

```php
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('creates a workflow', function () {
    // Database changes are automatically rolled back after this test
    $workflow = Workflow::factory()->create();
    
    expect(Workflow::count())->toBe(1);
});
```

---

### Seeding Test Data

```php
it('requires seeded nodes', function () {
    $this->seed(NodeSeeder::class);
    
    expect(DB::table('nodes')->count())->toBeGreaterThan(0);
});
```

---

## API Testing

### Testing with Postman

See **[Postman Collection](./Agent1o1-API.postman_collection.json)**

**Running Postman tests in CI:**

```bash
# Install Newman (Postman CLI)
npm install -g newman

# Run collection
newman run docs/Agent1o1-API.postman_collection.json \
  --environment docs/Agent1o1-Local.postman_environment.json
```

---

### HTTP Assertions

```php
$response = $this->getJson('/api/v1/workflows');

// Status codes
$response->assertStatus(200);
$response->assertOk();
$response->assertCreated(); // 201
$response->assertNoContent(); // 204
$response->assertNotFound(); // 404
$response->assertForbidden(); // 403
$response->assertUnauthorized(); // 401

// JSON structure
$response->assertJson([
    'data' => [
        'name' => 'Test Workflow',
    ],
]);

$response->assertJsonStructure([
    'data' => [
        'id',
        'name',
        'created_at',
    ],
]);

// JSON path
$response->assertJsonPath('data.name', 'Test Workflow');
$response->assertJsonPath('meta.total', 10);

// JSON count
$response->assertJsonCount(5, 'data');

// Headers
$response->assertHeader('Content-Type', 'application/json');
```

---

## Testing Workflows

### Testing Workflow Execution

```php
// tests/Feature/WorkflowExecutionTest.php
use App\Jobs\ExecuteWorkflowJob;
use Illuminate\Support\Facades\Queue;

it('executes workflow via webhook trigger', function () {
    Queue::fake();
    
    $workflow = Workflow::factory()->create([
        'workspace_id' => $this->workspace->id,
        'trigger_type' => 'webhook',
        'is_active' => true,
    ]);
    
    $webhookUrl = $workflow->trigger_config['url'];
    
    $response = $this->postJson($webhookUrl, [
        'userId' => 123,
        'action' => 'test',
    ]);
    
    $response->assertStatus(200);
    
    Queue::assertPushed(ExecuteWorkflowJob::class, function ($job) use ($workflow) {
        return $job->workflowId === $workflow->id;
    });
});

it('creates execution record', function () {
    $workflow = Workflow::factory()->create(['workspace_id' => $this->workspace->id]);
    
    dispatch(new ExecuteWorkflowJob($workflow->id, ['test' => 'data']));
    
    $this->assertDatabaseHas('executions', [
        'workflow_id' => $workflow->id,
        'status' => 'running',
    ]);
});

it('handles workflow execution errors', function () {
    // Create workflow with failing node
    $workflow = Workflow::factory()->create([
        'workspace_id' => $this->workspace->id,
        'definition' => [
            'nodes' => [
                [
                    'id' => '1',
                    'type' => 'http',
                    'config' => [
                        'url' => 'https://invalid-url-that-will-fail.test',
                    ],
                ],
            ],
        ],
    ]);
    
    dispatch(new ExecuteWorkflowJob($workflow->id, []));
    
    $execution = $workflow->executions()->first();
    
    expect($execution->status)->toBe('failed')
        ->and($execution->error)->not->toBeNull();
});
```

---

### Testing with Queue Jobs

```php
use Illuminate\Support\Facades\Queue;

it('dispatches job to queue', function () {
    Queue::fake();
    
    // Action that dispatches job
    dispatch(new ExecuteWorkflowJob($workflowId, $payload));
    
    // Assert job was pushed
    Queue::assertPushed(ExecuteWorkflowJob::class);
    
    // Assert job was pushed with specific data
    Queue::assertPushed(ExecuteWorkflowJob::class, function ($job) use ($workflowId) {
        return $job->workflowId === $workflowId;
    });
    
    // Assert job was pushed to specific queue
    Queue::assertPushedOn('workflows', ExecuteWorkflowJob::class);
});

it('processes job successfully', function () {
    // Don't fake queue, actually run the job
    $workflow = Workflow::factory()->create();
    
    $job = new ExecuteWorkflowJob($workflow->id, []);
    $job->handle();
    
    // Assert side effects
    expect($workflow->fresh()->last_executed_at)->not->toBeNull();
});
```

---

## Mocking External Services

### HTTP Mocking

```php
use Illuminate\Support\Facades\Http;

it('mocks external API calls', function () {
    // Mock all HTTP requests
    Http::fake();
    
    // Call code that makes HTTP request
    $node = new HttpNode();
    $node->execute(['url' => 'https://api.example.com'], [], $context);
    
    // Assert request was made
    Http::assertSent(function ($request) {
        return $request->url() === 'https://api.example.com';
    });
});

it('mocks specific endpoints', function () {
    Http::fake([
        'https://api.openai.com/*' => Http::response([
            'choices' => [
                ['message' => ['content' => 'Mocked response']],
            ],
        ], 200),
        
        'https://api.stripe.com/*' => Http::response(['id' => 'cus_123'], 200),
    ]);
    
    // Requests to specified URLs will return mocked responses
});

it('simulates errors', function () {
    Http::fake([
        'https://api.example.com/*' => Http::response(['error' => 'Server error'], 500),
    ]);
    
    // Test error handling
});

it('simulates network timeout', function () {
    Http::fake(function () {
        throw new \Illuminate\Http\Client\ConnectionException('Connection timeout');
    });
    
    // Test timeout handling
});
```

---

### Mocking Services

```php
use App\Services\LLMService;

it('mocks LLM service', function () {
    // Create mock
    $mockLLM = Mockery::mock(LLMService::class);
    
    // Define expectations
    $mockLLM->shouldReceive('generateText')
        ->once()
        ->with('Test prompt')
        ->andReturn('Mocked response');
    
    // Bind mock to container
    $this->app->instance(LLMService::class, $mockLLM);
    
    // Test code that uses LLMService
    $result = app(LLMService::class)->generateText('Test prompt');
    
    expect($result)->toBe('Mocked response');
});
```

---

### Mocking Events

```php
use Illuminate\Support\Facades\Event;
use App\Events\WorkflowExecuted;

it('dispatches event on workflow completion', function () {
    Event::fake();
    
    // Execute workflow
    $workflow = Workflow::factory()->create();
    dispatch(new ExecuteWorkflowJob($workflow->id, []));
    
    // Assert event was dispatched
    Event::assertDispatched(WorkflowExecuted::class, function ($event) use ($workflow) {
        return $event->workflow->id === $workflow->id;
    });
});
```

---

## Test Coverage

### Generate Coverage Report

```bash
# HTML report
php artisan test --coverage-html=coverage

# Open report
open coverage/index.html

# Terminal output
php artisan test --coverage

# Minimum coverage threshold
php artisan test --coverage --min=80
```

**Coverage Goals:**
- **Critical paths:** 90%+ (workflow engine, auth)
- **Business logic:** 80%+
- **Controllers:** 70%+
- **Overall:** 75%+

---

### Coverage Configuration

**phpunit.xml:**

```xml
<coverage>
    <include>
        <directory suffix=".php">./app</directory>
    </include>
    <exclude>
        <directory>./app/Console</directory>
        <directory>./app/Exceptions</directory>
        <file>./app/Http/Kernel.php</file>
    </exclude>
    <report>
        <html outputDirectory="coverage"/>
        <clover outputFile="coverage/clover.xml"/>
    </report>
</coverage>
```

---

## CI Integration

### GitHub Actions

```yaml
# .github/workflows/tests.yml
name: Tests

on: [push, pull_request]

jobs:
  test:
    runs-on: ubuntu-latest
    
    services:
      postgres:
        image: pgvector/pgvector:pg17
        env:
          POSTGRES_DB: linkflow_test
          POSTGRES_USER: postgres
          POSTGRES_PASSWORD: postgres
        options: >-
          --health-cmd pg_isready
          --health-interval 10s
          --health-timeout 5s
          --health-retries 5
        ports:
          - 5432:5432
      
      redis:
        image: redis:7-alpine
        options: >-
          --health-cmd "redis-cli ping"
          --health-interval 10s
          --health-timeout 5s
          --health-retries 5
        ports:
          - 6379:6379
    
    steps:
      - uses: actions/checkout@v3
      
      - name: Setup PHP
        uses: shivammathur/setup-php@v2
        with:
          php-version: 8.3
          extensions: pgsql, redis, mbstring, xml, bcmath
          coverage: xdebug
      
      - name: Install dependencies
        run: composer install --prefer-dist --no-progress
      
      - name: Copy environment file
        run: cp .env.example .env
      
      - name: Generate application key
        run: php artisan key:generate
      
      - name: Run migrations
        env:
          DB_CONNECTION: pgsql
          DB_HOST: localhost
          DB_PORT: 5432
          DB_DATABASE: linkflow_test
          DB_USERNAME: postgres
          DB_PASSWORD: postgres
        run: php artisan migrate --force
      
      - name: Run tests with coverage
        env:
          DB_CONNECTION: pgsql
          DB_HOST: localhost
          DB_PORT: 5432
          DB_DATABASE: linkflow_test
          DB_USERNAME: postgres
          DB_PASSWORD: postgres
        run: php artisan test --coverage --min=75
      
      - name: Upload coverage to Codecov
        uses: codecov/codecov-action@v3
        with:
          file: ./coverage/clover.xml
```

---

## Best Practices

### 1. Arrange-Act-Assert Pattern

```php
it('updates workflow name', function () {
    // Arrange: Set up test data
    $workflow = Workflow::factory()->create(['name' => 'Old Name']);
    
    // Act: Perform the action
    $workflow->update(['name' => 'New Name']);
    
    // Assert: Verify the result
    expect($workflow->fresh()->name)->toBe('New Name');
});
```

### 2. One Assertion Per Test (Flexible)

Focus each test on one behavior, but multiple assertions are OK if they verify the same behavior:

```php
// ✅ Good: Multiple assertions for same behavior
it('creates workflow with correct attributes', function () {
    $workflow = Workflow::factory()->create(['name' => 'Test']);
    
    expect($workflow->name)->toBe('Test')
        ->and($workflow->is_active)->toBeTrue()
        ->and($workflow->workspace_id)->not->toBeNull();
});

// ❌ Bad: Testing multiple unrelated behaviors
it('does everything', function () {
    // Tests creation, update, deletion all in one test
});
```

### 3. Use Descriptive Test Names

```php
// ✅ Good
it('prevents non-owner from deleting workspace', function () {
    // ...
});

it('sends notification when workflow execution fails', function () {
    // ...
});

// ❌ Bad
it('test delete', function () {
    // ...
});
```

### 4. Test Edge Cases

```php
it('handles empty workflow definition', function () {
    // ...
})->throws(ValidationException::class);

it('handles very long workflow names', function () {
    $name = str_repeat('a', 300);
    // ...
})->throws(ValidationException::class);

it('handles null values gracefully', function () {
    // ...
});
```

### 5. Don't Test Framework Code

```php
// ❌ Don't test Laravel's validation
it('validates email format', function () {
    // Laravel already tests this
});

// ✅ Test your business logic
it('prevents duplicate workflow names in workspace', function () {
    // Your custom validation
});
```

### 6. Keep Tests Fast

```php
// Use factories instead of creating real files
$file = UploadedFile::fake()->create('test.pdf', 1000);

// Use database transactions (automatic with RefreshDatabase)
uses(RefreshDatabase::class);

// Mock external API calls
Http::fake();

// Use in-memory drivers for cache/queue in tests
Queue::fake();
Cache::fake();
```

### 7. Test Data Should Be Realistic

```php
// ❌ Too simple
$workflow = Workflow::factory()->create(['name' => 'Test']);

// ✅ Realistic
$workflow = Workflow::factory()->create([
    'name' => 'Send Welcome Email to New Users',
    'definition' => [
        'nodes' => [
            ['id' => '1', 'type' => 'webhook'],
            ['id' => '2', 'type' => 'filter', 'config' => ['condition' => 'user.status == "new"']],
            ['id' => '3', 'type' => 'email', 'config' => ['template' => 'welcome']],
        ],
    ],
]);
```

---

## Common Patterns

### Testing Authenticated Requests

```php
use Laravel\Passport\Passport;

beforeEach(function () {
    $this->user = User::factory()->create();
    Passport::actingAs($this->user);
});

it('returns authenticated user', function () {
    $response = $this->getJson('/api/v1/me');
    
    $response->assertStatus(200)
        ->assertJsonPath('data.id', $this->user->id);
});
```

### Testing Authorization

```php
it('prevents member from deleting workflows', function () {
    $member = User::factory()->create();
    $this->workspace->members()->attach($member, ['role' => 'member']);
    
    Passport::actingAs($member);
    
    $workflow = Workflow::factory()->create(['workspace_id' => $this->workspace->id]);
    
    $response = $this->deleteJson(
        "/api/v1/workspaces/{$this->workspace->id}/workflows/{$workflow->id}"
    );
    
    $response->assertStatus(403);
});
```

### Testing Validation

```php
it('validates required fields', function () {
    $response = $this->postJson(
        "/api/v1/workspaces/{$this->workspace->id}/workflows",
        [] // Empty data
    );
    
    $response->assertStatus(422)
        ->assertJsonValidationErrors(['name', 'trigger_type']);
});

it('validates email format', function () {
    $response = $this->postJson('/api/v1/register', [
        'email' => 'invalid-email',
        'password' => 'password123',
    ]);
    
    $response->assertStatus(422)
        ->assertJsonValidationErrors(['email']);
});
```

### Testing Pagination

```php
it('paginates workflows', function () {
    Workflow::factory()->count(30)->create(['workspace_id' => $this->workspace->id]);
    
    $response = $this->getJson(
        "/api/v1/workspaces/{$this->workspace->id}/workflows?per_page=10"
    );
    
    $response->assertStatus(200)
        ->assertJsonCount(10, 'data')
        ->assertJsonPath('meta.total', 30)
        ->assertJsonPath('meta.per_page', 10);
});
```

### Testing Soft Deletes

```php
it('soft deletes workflow', function () {
    $workflow = Workflow::factory()->create();
    
    $workflow->delete();
    
    // Still in database but deleted_at is set
    $this->assertSoftDeleted('workflows', ['id' => $workflow->id]);
    
    // Not in normal queries
    expect(Workflow::find($workflow->id))->toBeNull();
    
    // Can be found with trashed
    expect(Workflow::withTrashed()->find($workflow->id))->not->toBeNull();
});
```

---

## Troubleshooting Tests

### Common Issues

**1. Database not found:**

```bash
# Create test database
createdb linkflow_test
psql linkflow_test -c "CREATE EXTENSION vector;"
```

**2. Tests fail with "Class not found":**

```bash
# Regenerate autoload files
composer dump-autoload
```

**3. Passport errors:**

```bash
php artisan passport:install --env=testing
```

**4. Tests pass individually but fail when run together:**

Likely a shared state issue. Check for:
- Global variables
- Static properties
- Cached data
- Incomplete database rollbacks

**5. Slow tests:**

```bash
# Identify slow tests
php artisan test --profile

# Run in parallel
php artisan test --parallel
```

---

### Debugging Tests

```php
// Dump variables
it('debugs data', function () {
    $workflow = Workflow::factory()->create();
    
    dd($workflow); // Dump and die
    dump($workflow); // Dump and continue
});

// Check database state
it('checks database', function () {
    $workflow = Workflow::factory()->create();
    
    // Dump all workflows
    dd(Workflow::all());
    
    // Dump raw SQL query
    dd(Workflow::toSql());
});

// Enable query log
DB::enableQueryLog();
// ... run code
dd(DB::getQueryLog());
```

---

**Testing ensures reliability. Write tests, run them often, and trust your code!** 🧪

*Last Updated: December 2024*
