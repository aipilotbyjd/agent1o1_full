# Complete Trigger System Restructure - Zapier-Like Architecture

## 🎯 Vision

**Transform LinkFlow from "generic webhooks + polling"** → **"Zapier-style pre-built service triggers"**

Users will NEVER see the word "webhook" or "polling". They just see services and trigger types.

---

## 📋 Table of Contents

1. [Current vs Target Architecture](#current-vs-target)
2. [Database Redesign](#database-redesign)
3. [Backend Restructuring](#backend-restructuring)
4. [Frontend Complete Rebuild](#frontend-rebuild)
5. [Migration Strategy](#migration-strategy)
6. [Implementation Timeline](#timeline)

---

## 📊 Current vs Target Architecture

### Current Architecture (Problems)
```
Users see:
├─ Trigger Type: "Webhook" (confusing)
├─ Or: "Polling" (how often do you want to poll?)
├─ Or: "Cron" (cron expression syntax?)
└─ Need to understand webhooks/polling

Database:
├─ workflows.trigger_type (webhook, polling, cron)
├─ webhooks table (manual or auto-registered)
├─ polling_triggers table (separate config)
├─ Separate registrars (GitHub, Slack, Stripe)
└─ Confusing relationship graph

Flow:
├─ User creates webhook trigger
├─ Specifies provider + events
├─ We auto-register
└─ Webhook arrives → triggers execution

Problems:
❌ Steep learning curve
❌ Inconsistent UX (webhook vs polling vs cron)
❌ Hidden complexity
❌ Hard to onboard non-technical users
```

### Target Architecture (Zapier-Like)
```
Users see:
├─ Service: GitHub
│   ├─ On Push
│   ├─ On Pull Request
│   └─ On New Issue
├─ Service: Slack
│   ├─ On New Message
│   ├─ On App Mention
│   └─ On File Uploaded
├─ Service: Stripe
│   ├─ On Charge Succeeded
│   ├─ On Invoice Created
│   └─ On Customer Created
└─ Service: Custom (REST API)
    └─ On Webhook

Database:
├─ trigger_templates (GitHub: On Push, etc.)
├─ triggers (instance of template for a workflow)
├─ trigger_fields (field values: owner, repo, etc.)
└─ One unified system, no webhook/polling distinction

Flow:
├─ User selects service (GitHub)
├─ Selects trigger type (On Push)
├─ Fills form (owner, repo, branch)
├─ Authenticates GitHub (if needed)
├─ Done ✅ (registration hidden)

Benefits:
✅ Simple UX (Zapier-like)
✅ No learning curve
✅ Consistent experience
✅ Non-technical users understand
```

---

## 🗄️ Database Redesign

### New Schema (Complete Rewrite)

```sql
-- ════════════════════════════════════════════════════════
-- TABLE 1: trigger_services
-- ════════════════════════════════════════════════════════
CREATE TABLE trigger_services (
  id BIGINT PRIMARY KEY AUTO_INCREMENT,
  slug VARCHAR(50) UNIQUE,              -- "github", "slack", "stripe"
  name VARCHAR(100),                    -- "GitHub", "Slack", "Stripe"
  icon_url VARCHAR(255),                -- URL to service icon
  description TEXT,                     -- "Connect to GitHub to trigger workflows"
  auth_type ENUM(
    'oauth2',                           -- GitHub, Slack, Google
    'api_key',                          -- Stripe, Mailchimp
    'basic_auth',                       -- Some services
    'bearer_token',                     -- API tokens
    'none'                              -- Webhooks by Zapier
  ),
  credential_type VARCHAR(100),         -- Maps to credentials.type
  is_active BOOLEAN DEFAULT TRUE,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_slug (slug)
);

-- Example data:
-- (1, 'github', 'GitHub', '...icon.png', 'Connect to GitHub', 'oauth2', 'github', TRUE)
-- (2, 'slack', 'Slack', '...icon.png', 'Connect to Slack', 'oauth2', 'slack', TRUE)
-- (3, 'stripe', 'Stripe', '...icon.png', 'Connect to Stripe', 'api_key', 'stripe', TRUE)
-- (4, 'custom', 'Custom Webhook', '...icon.png', 'Any REST API', 'none', NULL, TRUE)

-- ════════════════════════════════════════════════════════
-- TABLE 2: trigger_types
-- ════════════════════════════════════════════════════════
CREATE TABLE trigger_types (
  id BIGINT PRIMARY KEY AUTO_INCREMENT,
  service_id BIGINT NOT NULL,
  slug VARCHAR(100),                    -- "on_push", "on_message", "on_charge"
  name VARCHAR(100),                    -- "On Push", "On Message", "On Charge"
  description TEXT,                     -- User-facing description
  icon_emoji VARCHAR(10),               -- "📤", "💬", "💳"
  trigger_mode ENUM(
    'webhook',                          -- Push model (event-driven)
    'polling'                           -- Pull model (periodic check)
  ),
  is_active BOOLEAN DEFAULT TRUE,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY unique_service_slug (service_id, slug),
  FOREIGN KEY (service_id) REFERENCES trigger_services(id),
  INDEX idx_service_id (service_id)
);

-- Example data:
-- (1, 1, 'on_push', 'On Push', 'Triggers when code is pushed to a repository', '📤', 'webhook', TRUE)
-- (2, 1, 'on_pr', 'On Pull Request', 'Triggers when a PR is opened/updated', '🔀', 'webhook', TRUE)
-- (3, 2, 'on_message', 'On New Message', 'Triggers when a message is posted', '💬', 'webhook', TRUE)
-- (4, 3, 'on_charge', 'On Charge Succeeded', 'Triggers when payment succeeds', '💳', 'webhook', TRUE)
-- (5, 4, 'custom_webhook', 'On Webhook', 'Generic webhook receiver', '🪝', 'webhook', TRUE)

-- ════════════════════════════════════════════════════════
-- TABLE 3: trigger_type_fields
-- ════════════════════════════════════════════════════════
-- Defines what fields each trigger type needs
CREATE TABLE trigger_type_fields (
  id BIGINT PRIMARY KEY AUTO_INCREMENT,
  trigger_type_id BIGINT NOT NULL,
  field_name VARCHAR(100),              -- "owner", "repository", "branch"
  field_label VARCHAR(100),             -- "Repository Owner", "Repository Name"
  field_type ENUM(
    'text',
    'select',
    'textarea',
    'boolean',
    'number',
    'email'
  ),
  is_required BOOLEAN,
  placeholder VARCHAR(255),
  help_text TEXT,
  options JSON,                         -- For select fields: ["main", "develop"]
  validation_regex VARCHAR(255),        -- For text fields
  sort_order INT,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (trigger_type_id) REFERENCES trigger_types(id),
  INDEX idx_trigger_type (trigger_type_id)
);

-- Example data:
-- GitHub On Push:
-- (1, 1, 'owner', 'Repository Owner', 'text', TRUE, 'mycompany', 'GitHub org or username', NULL, '^[a-zA-Z0-9-]+$', 1)
-- (2, 1, 'repository', 'Repository Name', 'text', TRUE, 'backend', 'Repository name', NULL, '^[a-zA-Z0-9-_]+$', 2)
-- (3, 1, 'branch', 'Branch (optional)', 'text', FALSE, 'main', 'Leave empty for all branches', NULL, NULL, 3)
-- Slack On Message:
-- (4, 3, 'channel', 'Channel', 'select', TRUE, NULL, 'Which channel to listen to', NULL, NULL, 1)
-- (5, 3, 'include_threads', 'Include thread replies', 'boolean', FALSE, NULL, 'Trigger on thread messages too', NULL, NULL, 2)

-- ════════════════════════════════════════════════════════
-- TABLE 4: triggers (Main table - replaces webhooks + polling_triggers)
-- ════════════════════════════════════════════════════════
CREATE TABLE triggers (
  id BIGINT PRIMARY KEY AUTO_INCREMENT,
  workflow_id VARCHAR(36) NOT NULL,     -- UUID
  workspace_id VARCHAR(36) NOT NULL,
  trigger_type_id BIGINT NOT NULL,
  credential_id BIGINT,                 -- User's auth credentials
  name VARCHAR(255),                    -- Optional custom name
  is_active BOOLEAN DEFAULT TRUE,
  
  -- Metadata for webhook-based triggers
  webhook_uuid VARCHAR(36) UNIQUE,      -- Unique URL identifier
  webhook_provider VARCHAR(50),         -- "github", "slack", "stripe"
  webhook_external_id VARCHAR(255),     -- Provider's webhook ID
  webhook_secret LONGTEXT ENCRYPTED,    -- HMAC secret
  webhook_registered_url VARCHAR(255),  -- URL we registered with provider
  
  -- Metadata for polling-based triggers
  polling_interval_seconds INT,         -- Check every N seconds
  polling_last_check_at TIMESTAMP,
  polling_last_seen_ids JSON,           -- Last 1000 seen IDs for dedup
  polling_endpoint_url VARCHAR(255),    -- For custom polling
  
  -- Error tracking
  last_error TEXT,
  last_error_at TIMESTAMP,
  error_count INT DEFAULT 0,
  
  -- Stats
  execution_count INT DEFAULT 0,
  last_triggered_at TIMESTAMP,
  
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  
  FOREIGN KEY (workflow_id) REFERENCES workflows(id),
  FOREIGN KEY (workspace_id) REFERENCES workspaces(id),
  FOREIGN KEY (trigger_type_id) REFERENCES trigger_types(id),
  FOREIGN KEY (credential_id) REFERENCES credentials(id),
  INDEX idx_workflow (workflow_id),
  INDEX idx_workspace (workspace_id),
  INDEX idx_active (is_active)
);

-- ════════════════════════════════════════════════════════
-- TABLE 5: trigger_field_values
-- ════════════════════════════════════════════════════════
-- Stores the actual field values for each trigger
CREATE TABLE trigger_field_values (
  id BIGINT PRIMARY KEY AUTO_INCREMENT,
  trigger_id BIGINT NOT NULL,
  trigger_type_field_id BIGINT NOT NULL,
  value TEXT,                           -- The actual value (owner=mycompany)
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  
  FOREIGN KEY (trigger_id) REFERENCES triggers(id),
  FOREIGN KEY (trigger_type_field_id) REFERENCES trigger_type_fields(id),
  UNIQUE KEY unique_trigger_field (trigger_id, trigger_type_field_id)
);

-- Example data:
-- Trigger ID 1 (GitHub: On Push for workflow 123):
-- (1, 1, 1, 'mycompany')          -- owner=mycompany
-- (2, 1, 2, 'backend')             -- repository=backend
-- (3, 1, 3, 'main')                -- branch=main

-- ════════════════════════════════════════════════════════
-- TABLE 6: trigger_executions (Audit trail)
-- ════════════════════════════════════════════════════════
CREATE TABLE trigger_executions (
  id BIGINT PRIMARY KEY AUTO_INCREMENT,
  trigger_id BIGINT NOT NULL,
  workflow_execution_id VARCHAR(36),    -- Links to executions table
  triggered_at TIMESTAMP,
  trigger_data JSON,                    -- The event data that triggered execution
  error TEXT,                           -- If trigger failed
  source ENUM('webhook', 'polling'),    -- How it was triggered
  
  FOREIGN KEY (trigger_id) REFERENCES triggers(id),
  FOREIGN KEY (workflow_execution_id) REFERENCES executions(id),
  INDEX idx_trigger (trigger_id),
  INDEX idx_triggered_at (triggered_at)
);

-- ════════════════════════════════════════════════════════
-- DROP OLD TABLES (After migration)
-- ════════════════════════════════════════════════════════
-- Once data migrated:
-- DROP TABLE webhooks;
-- DROP TABLE polling_triggers;
-- Remove from workflows: trigger_type, cron_expression, webhook_status, etc.
```

### Updated workflows table
```sql
ALTER TABLE workflows DROP COLUMN trigger_type;
ALTER TABLE workflows DROP COLUMN cron_expression;
ALTER TABLE workflows DROP COLUMN webhook_status;
ALTER TABLE workflows DROP COLUMN webhook_status_message;
ALTER TABLE workflows DROP COLUMN next_run_at;
ALTER TABLE workflows DROP COLUMN last_cron_run_at;

-- Now workflows has:
-- - One-to-one relationship with triggers table
-- - Or NULL if workflow has no trigger yet (draft state)
```

---

## 🔧 Backend Restructuring

### 1. New Models

```php
// app/Models/TriggerService.php
class TriggerService extends Model {
    public $timestamps = true;
    protected $fillable = ['slug', 'name', 'icon_url', 'description', 'auth_type', 'credential_type', 'is_active'];
    
    public function triggerTypes(): HasMany {
        return $this->hasMany(TriggerType::class);
    }
    
    public function triggers(): HasManyThrough {
        return $this->hasManyThrough(Trigger::class, TriggerType::class);
    }
}

// app/Models/TriggerType.php
class TriggerType extends Model {
    public $timestamps = true;
    protected $fillable = ['service_id', 'slug', 'name', 'description', 'icon_emoji', 'trigger_mode', 'is_active'];
    protected $casts = ['is_active' => 'boolean'];
    
    public function service(): BelongsTo {
        return $this->belongsTo(TriggerService::class);
    }
    
    public function fields(): HasMany {
        return $this->hasMany(TriggerTypeField::class);
    }
    
    public function triggers(): HasMany {
        return $this->hasMany(Trigger::class);
    }
}

// app/Models/TriggerTypeField.php
class TriggerTypeField extends Model {
    public $timestamps = true;
    protected $fillable = ['trigger_type_id', 'field_name', 'field_label', 'field_type', 'is_required', 'placeholder', 'help_text', 'options', 'validation_regex', 'sort_order'];
    protected $casts = ['is_required' => 'boolean', 'options' => 'array'];
    
    public function triggerType(): BelongsTo {
        return $this->belongsTo(TriggerType::class);
    }
}

// app/Models/Trigger.php
class Trigger extends Model {
    use HasUuid;
    public $timestamps = true;
    protected $fillable = ['workflow_id', 'workspace_id', 'trigger_type_id', 'credential_id', 'name', 'is_active', 'webhook_uuid', 'webhook_provider', 'webhook_external_id', 'webhook_secret', 'webhook_registered_url', 'polling_interval_seconds', 'polling_endpoint_url'];
    protected $casts = ['is_active' => 'boolean', 'polling_last_seen_ids' => 'array'];
    protected $hidden = ['webhook_secret'];
    
    public function workflow(): BelongsTo {
        return $this->belongsTo(Workflow::class);
    }
    
    public function triggerType(): BelongsTo {
        return $this->belongsTo(TriggerType::class);
    }
    
    public function credential(): BelongsTo {
        return $this->belongsTo(Credential::class);
    }
    
    public function fieldValues(): HasMany {
        return $this->hasMany(TriggerFieldValue::class);
    }
    
    public function executions(): HasMany {
        return $this->hasMany(TriggerExecution::class);
    }
    
    public function isWebhookBased(): bool {
        return $this->triggerType->trigger_mode === 'webhook';
    }
    
    public function isPollingBased(): bool {
        return $this->triggerType->trigger_mode === 'polling';
    }
}

// app/Models/TriggerFieldValue.php
class TriggerFieldValue extends Model {
    public $timestamps = true;
    protected $fillable = ['trigger_id', 'trigger_type_field_id', 'value'];
    
    public function trigger(): BelongsTo {
        return $this->belongsTo(Trigger::class);
    }
    
    public function field(): BelongsTo {
        return $this->belongsTo(TriggerTypeField::class);
    }
}

// app/Models/TriggerExecution.php
class TriggerExecution extends Model {
    public $timestamps = false;
    protected $fillable = ['trigger_id', 'workflow_execution_id', 'triggered_at', 'trigger_data', 'error', 'source'];
    protected $casts = ['trigger_data' => 'array'];
    
    public function trigger(): BelongsTo {
        return $this->belongsTo(Trigger::class);
    }
}

// Update Workflow model
class Workflow extends Model {
    public function trigger(): HasOne {
        return $this->hasOne(Trigger::class);
    }
}
```

### 2. New Services

```php
// app/Services/TriggerService.php
class TriggerService {
    
    /**
     * Get all available trigger services grouped by category
     */
    public function getAllServices() {
        return TriggerService::where('is_active', true)
            ->with('triggerTypes')
            ->get()
            ->groupBy('slug');
    }
    
    /**
     * Create a trigger for a workflow
     */
    public function createTrigger(
        Workflow $workflow,
        TriggerType $triggerType,
        Credential $credential,
        array $fieldValues
    ): Trigger {
        // Validate fields against template
        $this->validateFieldValues($triggerType, $fieldValues);
        
        // Create trigger
        $trigger = Trigger::create([
            'workflow_id' => $workflow->id,
            'workspace_id' => $workflow->workspace_id,
            'trigger_type_id' => $triggerType->id,
            'credential_id' => $credential->id,
            'is_active' => false, // Inactive until published
            'webhook_uuid' => Str::uuid(),
        ]);
        
        // Store field values
        foreach ($fieldValues as $fieldId => $value) {
            TriggerFieldValue::create([
                'trigger_id' => $trigger->id,
                'trigger_type_field_id' => $fieldId,
                'value' => $value,
            ]);
        }
        
        return $trigger->load('fieldValues', 'triggerType');
    }
    
    /**
     * Register trigger with external service (webhook)
     */
    public function registerTrigger(Trigger $trigger): void {
        if (!$trigger->isWebhookBased()) {
            $this->setupPollingTrigger($trigger);
            return;
        }
        
        $triggerType = $trigger->triggerType;
        $service = $triggerType->service;
        $fieldValues = $trigger->fieldValues()->get();
        
        // Get registrar for this service
        $registrar = $this->getRegistrar($service->slug);
        if (!$registrar) {
            throw new \Exception("No registrar for {$service->slug}");
        }
        
        // Build provider config from field values
        $providerConfig = [];
        foreach ($fieldValues as $fv) {
            $providerConfig[$fv->field->field_name] = $fv->value;
        }
        
        // Get credential
        $credential = $trigger->credential;
        
        // Register webhook
        $callbackUrl = "https://linkflow.io/api/v1/triggers/{$trigger->webhook_uuid}";
        $result = $registrar->register($callbackUrl, [], $credential->data, $providerConfig);
        
        // Store registration details
        $trigger->update([
            'webhook_provider' => $service->slug,
            'webhook_external_id' => $result['external_id'],
            'webhook_secret' => $result['secret'],
            'webhook_registered_url' => $callbackUrl,
            'is_active' => true,
        ]);
    }
    
    private function setupPollingTrigger(Trigger $trigger): void {
        // Set up polling
        $trigger->update([
            'polling_interval_seconds' => $trigger->triggerType->polling_interval_seconds ?? 300,
            'polling_last_check_at' => now(),
            'is_active' => true,
        ]);
    }
    
    private function getRegistrar(string $service) {
        // Map service slug to registrar class
        $registrars = [
            'github' => new GitHubWebhookRegistrar(),
            'slack' => new SlackWebhookRegistrar(),
            'stripe' => new StripeWebhookRegistrar(),
        ];
        return $registrars[$service] ?? null;
    }
    
    private function validateFieldValues(TriggerType $triggerType, array $values): void {
        foreach ($triggerType->fields as $field) {
            if ($field->is_required && (!isset($values[$field->id]) || empty($values[$field->id]))) {
                throw new \Exception("Missing required field: {$field->field_label}");
            }
        }
    }
}

// app/Services/TriggerExecutionService.php
class TriggerExecutionService {
    
    /**
     * When webhook fires, create execution
     */
    public function executeFromWebhook(Trigger $trigger, array $payload): Execution {
        $workflow = $trigger->workflow;
        
        // Create execution
        $execution = Execution::create([
            'workflow_id' => $workflow->id,
            'workspace_id' => $workflow->workspace_id,
            'status' => ExecutionStatus::Pending,
            'mode' => ExecutionMode::Webhook,
            'trigger_data' => $payload,
            'triggered_by' => $workflow->creator_id,
        ]);
        
        // Log trigger execution
        TriggerExecution::create([
            'trigger_id' => $trigger->id,
            'workflow_execution_id' => $execution->id,
            'triggered_at' => now(),
            'trigger_data' => $payload,
            'source' => 'webhook',
        ]);
        
        // Dispatch execution job
        ExecuteWorkflowJob::dispatch($execution);
        
        return $execution;
    }
    
    /**
     * Poll for new data and execute
     */
    public function executeFromPolling(Trigger $trigger): int {
        // Fetch data from endpoint
        $data = $this->fetchPollingData($trigger);
        
        // Deduplicate based on dedup_key
        $newRecords = $this->deduplicateRecords($trigger, $data);
        
        $count = 0;
        foreach ($newRecords as $record) {
            $execution = Execution::create([
                'workflow_id' => $trigger->workflow_id,
                'workspace_id' => $trigger->workspace_id,
                'status' => ExecutionStatus::Pending,
                'mode' => ExecutionMode::Polling,
                'trigger_data' => $record,
                'triggered_by' => $trigger->workflow->creator_id,
            ]);
            
            TriggerExecution::create([
                'trigger_id' => $trigger->id,
                'workflow_execution_id' => $execution->id,
                'triggered_at' => now(),
                'trigger_data' => $record,
                'source' => 'polling',
            ]);
            
            ExecuteWorkflowJob::dispatch($execution);
            $count++;
        }
        
        // Update last check
        $trigger->update(['polling_last_check_at' => now()]);
        
        return $count;
    }
    
    private function fetchPollingData(Trigger $trigger): array {
        // Get endpoint from trigger fields
        $fields = $trigger->fieldValues()->get();
        $fieldMap = $fields->pluck('value', 'field.field_name')->toArray();
        
        // Build request based on trigger type
        // This is service-specific logic
        // For now, assume we have endpoint_url
        
        $endpoint = $fieldMap['endpoint_url'] ?? $trigger->polling_endpoint_url;
        $response = Http::timeout(30)->get($endpoint);
        
        return $response->json();
    }
    
    private function deduplicateRecords(Trigger $trigger, array $data): array {
        // Extract dedup key from trigger type
        $dedupKey = $trigger->triggerType->dedup_key ?? 'id';
        
        $lastSeenIds = $trigger->polling_last_seen_ids ?? [];
        $newRecords = [];
        $newIds = [];
        
        foreach ($data as $record) {
            $id = $record[$dedupKey] ?? null;
            if ($id && !in_array($id, $lastSeenIds)) {
                $newRecords[] = $record;
                $newIds[] = $id;
            }
        }
        
        // Update last seen IDs (keep last 1000)
        $updatedIds = array_unique(array_merge($lastSeenIds, $newIds));
        $updatedIds = array_slice($updatedIds, -1000);
        
        $trigger->update(['polling_last_seen_ids' => $updatedIds]);
        
        return $newRecords;
    }
}
```

### 3. New Controllers

```php
// app/Http/Controllers/Api/V1/TriggerController.php
class TriggerController {
    
    /**
     * GET /api/v1/trigger-services
     * List all available trigger services
     */
    public function listServices(): JsonResponse {
        $services = TriggerService::where('is_active', true)
            ->with(['triggerTypes' => function ($q) {
                $q->where('is_active', true)
                  ->with(['fields' => function ($q2) {
                      $q2->orderBy('sort_order');
                  }]);
            }])
            ->get();
        
        return response()->json($services);
    }
    
    /**
     * GET /api/v1/trigger-services/{service}
     * Get trigger types for a service
     */
    public function getService(string $service): JsonResponse {
        $service = TriggerService::where('slug', $service)
            ->with(['triggerTypes' => function ($q) {
                $q->where('is_active', true)
                  ->with(['fields' => function ($q2) {
                      $q2->orderBy('sort_order');
                  }]);
            }])
            ->firstOrFail();
        
        return response()->json($service);
    }
    
    /**
     * POST /api/v1/workspaces/{ws}/workflows/{id}/triggers
     * Create a trigger for workflow
     */
    public function create(Request $request, Workflow $workflow): JsonResponse {
        $validated = $request->validate([
            'trigger_type_id' => 'required|exists:trigger_types,id',
            'credential_id' => 'required|exists:credentials,id',
            'field_values' => 'required|array',
        ]);
        
        $triggerType = TriggerType::findOrFail($validated['trigger_type_id']);
        $credential = Credential::findOrFail($validated['credential_id']);
        
        // Verify credential belongs to workspace
        if ($credential->workspace_id !== $workflow->workspace_id) {
            throw new \Exception("Credential not found");
        }
        
        $triggerService = app(TriggerService::class);
        $trigger = $triggerService->createTrigger(
            $workflow,
            $triggerType,
            $credential,
            $validated['field_values']
        );
        
        return response()->json([
            'id' => $trigger->id,
            'trigger_type' => $trigger->triggerType->name,
            'status' => 'created',
        ]);
    }
    
    /**
     * POST /api/v1/triggers/{id}/publish
     * Publish trigger (register with external service)
     */
    public function publish(Trigger $trigger): JsonResponse {
        $this->authorize('update', $trigger);
        
        try {
            $triggerService = app(TriggerService::class);
            $triggerService->registerTrigger($trigger);
            
            return response()->json([
                'status' => 'published',
                'trigger' => $trigger->load('triggerType'),
            ]);
        } catch (\Exception $e) {
            $trigger->update([
                'last_error' => $e->getMessage(),
                'last_error_at' => now(),
                'error_count' => $trigger->error_count + 1,
            ]);
            
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }
    
    /**
     * DELETE /api/v1/triggers/{id}
     * Delete trigger and unregister from service
     */
    public function delete(Trigger $trigger): JsonResponse {
        $this->authorize('delete', $trigger);
        
        if ($trigger->isWebhookBased() && $trigger->webhook_external_id) {
            // Unregister from external service
            $registrar = $this->getRegistrar($trigger->webhook_provider);
            if ($registrar) {
                $registrar->unregister(
                    $trigger->webhook_external_id,
                    $trigger->credential->data,
                    []
                );
            }
        }
        
        $trigger->delete();
        
        return response()->json(['status' => 'deleted']);
    }
}

// app/Http/Controllers/Api/V1/TriggerWebhookController.php
class TriggerWebhookController {
    
    /**
     * POST /api/v1/triggers/{uuid}
     * Receive webhook from external service
     */
    public function receive(Request $request, string $uuid): JsonResponse {
        $trigger = Trigger::where('webhook_uuid', $uuid)->firstOrFail();
        
        // Verify signature
        $signature = $request->header('X-Hub-Signature-256')
            ?? $request->header('X-Slack-Signature')
            ?? $request->header('Stripe-Signature');
        
        if (!$this->verifySignature($trigger, $signature, $request->getContent())) {
            return response()->json(['error' => 'Invalid signature'], 401);
        }
        
        // Execute workflow
        $triggerExecutionService = app(TriggerExecutionService::class);
        $execution = $triggerExecutionService->executeFromWebhook(
            $trigger,
            $request->all()
        );
        
        // Increment stat
        $trigger->increment('execution_count');
        $trigger->update(['last_triggered_at' => now()]);
        
        return response()->json(['status' => 'received']);
    }
    
    private function verifySignature(Trigger $trigger, $signature, $payload): bool {
        $registrar = $this->getRegistrar($trigger->webhook_provider);
        if (!$registrar) {
            return false;
        }
        
        return $registrar->verifySignature(
            $payload,
            $signature,
            $trigger->webhook_secret
        );
    }
}
```

### 4. New Jobs

```php
// app/Jobs/PollTriggersJob.php
class PollTriggersJob implements ShouldQueue {
    use Queueable;
    public int $timeout = 90;
    
    public function handle(): void {
        // Find all active polling triggers due for check
        $dueTriggers = Trigger::where('is_active', true)
            ->whereHas('triggerType', function ($q) {
                $q->where('trigger_mode', 'polling');
            })
            ->where(function ($q) {
                $q->whereNull('polling_last_check_at')
                  ->orWhere('polling_last_check_at', '<=', now()->subSeconds(
                      DB::raw('polling_interval_seconds')
                  ));
            })
            ->get();
        
        $triggerExecutionService = app(TriggerExecutionService::class);
        
        foreach ($dueTriggers as $trigger) {
            $lock = Cache::lock("poll-trigger:{$trigger->id}", 90);
            
            if (!$lock->get()) {
                continue;
            }
            
            try {
                $triggerExecutionService->executeFromPolling($trigger);
            } catch (\Exception $e) {
                \Log::error("Polling trigger failed", [
                    'trigger_id' => $trigger->id,
                    'error' => $e->getMessage(),
                ]);
                
                $trigger->update([
                    'last_error' => $e->getMessage(),
                    'last_error_at' => now(),
                    'error_count' => $trigger->error_count + 1,
                ]);
            } finally {
                $lock->release();
            }
        }
    }
}
```

---

## 🎨 Frontend Complete Rebuild

### 1. New Components Structure

```
frontend/src/pages/editor/WorkflowEditor/
├── TriggerBuilder/
│   ├── TriggerBuilder.tsx          (Main container)
│   ├── ServiceSelector.tsx         (Step 1: Pick service)
│   ├── TriggerTypeSelector.tsx    (Step 2: Pick trigger type)
│   ├── TriggerConfigForm.tsx      (Step 3: Fill form)
│   ├── CredentialSelector.tsx     (Inline auth selector)
│   ├── TriggerPreview.tsx         (Show what's configured)
│   └── TriggerStatus.tsx          (Webhook registered? Polling active?)
```

### 2. Main Component

```tsx
// TriggerBuilder.tsx
export const TriggerBuilder: React.FC = () => {
  const [step, setStep] = useState<'service' | 'type' | 'config' | 'review'>('service');
  const [selectedService, setSelectedService] = useState<TriggerService | null>(null);
  const [selectedTriggerType, setSelectedTriggerType] = useState<TriggerType | null>(null);
  const [selectedCredential, setSelectedCredential] = useState<Credential | null>(null);
  const [fieldValues, setFieldValues] = useState<Record<number, string>>({});
  
  const { data: services } = useQuery(['trigger-services'], () =>
    api.get('/trigger-services')
  );
  
  const handleServiceSelect = (service: TriggerService) => {
    setSelectedService(service);
    setSelectedTriggerType(null);
    setStep('type');
  };
  
  const handleTriggerTypeSelect = (triggerType: TriggerType) => {
    setSelectedTriggerType(triggerType);
    setStep('config');
  };
  
  const handleConfigSubmit = async () => {
    // Check for credential
    if (!selectedCredential && selectedService?.auth_type !== 'none') {
      // Redirect to OAuth
      const oauth = await api.post('/oauth/authorize', {
        credential_type: selectedService?.credential_type,
      });
      window.location.href = oauth.authorization_url;
      return;
    }
    
    // Create trigger
    const trigger = await api.post(`/workflows/${workflowId}/triggers`, {
      trigger_type_id: selectedTriggerType?.id,
      credential_id: selectedCredential?.id,
      field_values: fieldValues,
    });
    
    setStep('review');
  };
  
  return (
    <div className="trigger-builder">
      {step === 'service' && (
        <ServiceSelector
          services={services}
          onSelect={handleServiceSelect}
        />
      )}
      
      {step === 'type' && selectedService && (
        <TriggerTypeSelector
          triggerTypes={selectedService.trigger_types}
          onSelect={handleTriggerTypeSelect}
          onBack={() => setStep('service')}
        />
      )}
      
      {step === 'config' && selectedTriggerType && (
        <TriggerConfigForm
          triggerType={selectedTriggerType}
          fieldValues={fieldValues}
          onFieldChange={(fieldId, value) => setFieldValues({...fieldValues, [fieldId]: value})}
          onSubmit={handleConfigSubmit}
          onBack={() => setStep('type')}
        />
      )}
      
      {step === 'review' && (
        <TriggerPreview trigger={...} />
      )}
    </div>
  );
};
```

### 3. Update Workflow Editor

```tsx
// Replace old trigger node picker with:
<TriggerBuilder
  onTriggerCreated={(trigger) => {
    workflow.trigger_id = trigger.id;
    // Update canvas/editor
  }}
/>
```

---

## 🔄 Migration Strategy

### Phase 1: Parallel Running (Weeks 1-2)
```
✅ Deploy new tables + models
✅ Deploy new services
✅ Keep old webhook/polling logic running
✅ Both systems coexist
```

### Phase 2: Migration Tool (Week 3)
```php
// database/migrations/*_migrate_webhooks_to_triggers.php

class MigrateWebhooksToTriggers {
    public function up() {
        // For each workflow with webhook trigger:
        foreach (Workflow::whereNotNull('trigger_type')->get() as $workflow) {
            if ($workflow->trigger_type === 'webhook') {
                // Create Trigger from Webhook
                foreach ($workflow->webhooks as $webhook) {
                    $triggerType = TriggerType::where('service_id', $service->id)
                        ->where('slug', $this->mapWebhookToTriggerType($webhook))
                        ->first();
                    
                    if ($triggerType) {
                        // Create new trigger
                        $trigger = Trigger::create([
                            'workflow_id' => $workflow->id,
                            'trigger_type_id' => $triggerType->id,
                            'credential_id' => $webhook->credential_id,
                            'webhook_uuid' => $webhook->uuid,
                            'webhook_provider' => $webhook->provider,
                            'webhook_external_id' => $webhook->external_webhook_id,
                            'webhook_secret' => $webhook->external_webhook_secret,
                            'webhook_registered_url' => $webhook->registered_url,
                            'is_active' => $webhook->is_active,
                        ]);
                        
                        // Migrate field values from webhook config
                        foreach ($webhook->provider_config as $key => $value) {
                            // Map to trigger fields
                        }
                    }
                }
            }
            
            if ($workflow->trigger_type === 'polling') {
                // Similar migration for polling triggers
            }
        }
    }
}
```

### Phase 3: New UI (Week 4)
```
✅ Deploy new TriggerBuilder components
✅ Hide old trigger UI
✅ New workflows use new system
✅ Old workflows still work with old code
```

### Phase 4: Cutover (Week 5)
```
✅ Remove old WebhookReceiverController logic
✅ Route old webhook UUIDs to new TriggerWebhookController
✅ Stop using old polling_triggers table
✅ Final cleanup
```

---

## ⏱️ Timeline

| Phase | Duration | Tasks |
|-------|----------|-------|
| **Phase 1: Database** | 3 days | Migrations, models, services |
| **Phase 2: Backend** | 4 days | Controllers, jobs, API endpoints |
| **Phase 3: Frontend** | 5 days | Components, UI, integration |
| **Phase 4: Migration** | 3 days | Data migration, parallel testing |
| **Phase 5: QA/Polish** | 4 days | Testing, bug fixes, docs |

**Total: ~3 weeks with 2 developers**

---

## 📋 Implementation Checklist

### Week 1: Database & Models
- [ ] Create all 6 new tables
- [ ] Create all 6 new models
- [ ] Update Workflow model
- [ ] Write model tests
- [ ] Seed trigger services/types (GitHub, Slack, Stripe, etc.)

### Week 2: Backend Services & Controllers
- [ ] Create TriggerService
- [ ] Create TriggerExecutionService
- [ ] Create TriggerController (CRUD)
- [ ] Create TriggerWebhookController (webhook receiver)
- [ ] Create PollTriggersJob
- [ ] Write service tests
- [ ] Write controller tests

### Week 3: Frontend Components
- [ ] Create ServiceSelector component
- [ ] Create TriggerTypeSelector component
- [ ] Create TriggerConfigForm component
- [ ] Create CredentialSelector component
- [ ] Create TriggerPreview component
- [ ] Integrate into WorkflowEditor
- [ ] Handle OAuth redirect flow

### Week 4: Migration & Integration
- [ ] Write migration script
- [ ] Test migration with sample data
- [ ] Route old webhook URLs to new handler
- [ ] Smoke test old workflows still work
- [ ] Feature flag new UI

### Week 5: QA & Polish
- [ ] End-to-end testing (each service)
- [ ] Edge case testing (errors, missing creds, etc.)
- [ ] Performance testing
- [ ] Documentation
- [ ] Remove old code (webhooks/polling tables)

---

## 🎯 Key Differences from Current System

| Aspect | Current | New |
|--------|---------|-----|
| **User sees** | "Webhook" + "Polling" | "GitHub: On Push" |
| **Database** | 2 tables (webhooks, polling) | 1 unified table (triggers) |
| **Service mapping** | Registrars (code-based) | TriggerService + TriggerType (data-driven) |
| **Field config** | Per-registrar logic | TriggerTypeField (unified) |
| **Execution** | WebhookReceiverController | TriggerWebhookController |
| **Polling** | PollTriggersCommand | PollTriggersJob |
| **Audit** | Implicit | TriggerExecution table (explicit) |

---

## ✨ Benefits of Complete Restructure

```
✅ Zapier-like UX (no webhook/polling terminology)
✅ Unified architecture (one way to do things)
✅ Scalable (easy to add new services)
✅ Auditable (trigger_executions table)
✅ Flexible (polling_interval configurable per service)
✅ Data-driven (services/types are DB rows, not code)
✅ Type-safe (proper foreign keys)
✅ Better error tracking (last_error, error_count)
✅ Better observability (execution_count, last_triggered_at)
```

---

## 🚀 Ready to Start?

This is a **complete, well-structured rewrite** that:
- Removes all legacy webhook/polling abstraction
- Makes LinkFlow fully Zapier-like
- Is properly documented and testable
- Has a clear migration path
- Zero breaking changes to users

Would you like me to:
1. Start with Phase 1 (database migrations)?
2. Create detailed seeders for all services?
3. Start building models/services?
4. Build frontend components?
