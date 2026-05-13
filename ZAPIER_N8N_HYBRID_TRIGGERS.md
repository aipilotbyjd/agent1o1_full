# Proper Trigger Architecture: Zapier + n8n Hybrid Model

## 🎯 Vision

Implement a trigger system that combines:
- **Zapier's** simplicity (instant webhooks vs polling, service templates)
- **n8n's** flexibility (manual, schedule, webhook, polling, app-specific)

**Result:** LinkFlow supports ALL trigger patterns users expect from professional workflow tools.

---

## 📊 Comprehensive Trigger Landscape

### Trigger Categories (What Users Actually Need)

```
1. MANUAL TRIGGERS
   ├─ User clicks "Run" button
   └─ Use case: Testing, on-demand workflows

2. TIME-BASED TRIGGERS (Cron/Schedule)
   ├─ Every N minutes/hours/days
   ├─ Daily at specific time
   ├─ Weekly on certain days
   ├─ Monthly on certain date
   └─ Use case: Backups, reports, batch processing

3. WEBHOOK TRIGGERS (Real-time)
   ├─ Custom webhook (raw POST)
   ├─ Service-specific (GitHub, Slack, Stripe, etc.)
   └─ Use case: Instant reactions to external events

4. POLLING TRIGGERS (Periodic checks)
   ├─ Generic API polling (custom endpoint)
   ├─ Service-specific (Airtable, Google Sheets, etc.)
   └─ Use case: Services without webhooks

5. APP-SPECIFIC TRIGGERS
   ├─ Gmail: New email
   ├─ Slack: New message
   ├─ GitHub: Push, PR, Issue
   ├─ Stripe: Charge, Invoice
   └─ Automatically handle auth + event checking
```

---

## 🗄️ Database Schema (Unified)

### Core Tables

```sql
-- ════════════════════════════════════════════════════════
-- 1. trigger_categories
-- ════════════════════════════════════════════════════════
CREATE TABLE trigger_categories (
  id BIGINT PRIMARY KEY AUTO_INCREMENT,
  slug VARCHAR(50) UNIQUE NOT NULL,
  name VARCHAR(100) NOT NULL,
  description TEXT,
  icon VARCHAR(50),
  
  -- Category type determines configuration options
  -- manual, schedule, webhook, polling, app_specific
  category_type ENUM('manual', 'schedule', 'webhook', 'polling', 'app_specific'),
  
  is_active BOOLEAN DEFAULT TRUE,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Seed data:
-- (manual, Manual Trigger, On Demand...)
-- (schedule, Scheduled Trigger, Time-based...)
-- (webhook, Custom Webhook, Raw HTTP...)
-- (polling, API Polling, Check custom endpoint...)
-- (github, GitHub, Service-specific...)
-- (slack, Slack, Service-specific...)
-- (stripe, Stripe, Service-specific...)
-- ... etc for all 40+ services


-- ════════════════════════════════════════════════════════
-- 2. trigger_types
-- ════════════════════════════════════════════════════════
CREATE TABLE trigger_types (
  id BIGINT PRIMARY KEY AUTO_INCREMENT,
  category_id BIGINT NOT NULL,
  slug VARCHAR(100) UNIQUE NOT NULL,
  name VARCHAR(100) NOT NULL,
  description TEXT,
  
  -- HOW this trigger works under the hood
  execution_mode ENUM('manual', 'webhook', 'polling') NOT NULL,
  
  -- For webhook-based: how Zapier calls it
  zapier_mode ENUM('instant', 'polling') DEFAULT NULL,
  
  -- Config
  requires_credential BOOLEAN DEFAULT FALSE,
  requires_config_fields BOOLEAN DEFAULT FALSE,
  is_active BOOLEAN DEFAULT TRUE,
  
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  
  FOREIGN KEY (category_id) REFERENCES trigger_categories(id),
  INDEX idx_category (category_id)
);

-- Seed examples:
-- GitHub: On Push
--   category: github
--   execution_mode: webhook (GitHub sends webhook)
--   zapier_mode: instant (real-time, not polled)
--   requires_credential: true (GitHub auth)
--   requires_config_fields: true (owner, repo, branch)
--
-- GitHub: Issues Updated (older repos without webhooks)
--   category: github
--   execution_mode: polling (we poll GitHub API)
--   zapier_mode: polling (check every 5 min)
--   requires_credential: true
--   requires_config_fields: true
--
-- Schedule: Daily
--   category: schedule
--   execution_mode: polling (our scheduler checks)
--   zapier_mode: null (not Zapier-like)
--   requires_credential: false
--   requires_config_fields: true (time, timezone)
--
-- Schedule: Cron Expression
--   category: schedule
--   execution_mode: polling
--   zapier_mode: null
--   requires_credential: false
--   requires_config_fields: true (cron_expression)


-- ════════════════════════════════════════════════════════
-- 3. trigger_type_fields
-- ════════════════════════════════════════════════════════
CREATE TABLE trigger_type_fields (
  id BIGINT PRIMARY KEY AUTO_INCREMENT,
  trigger_type_id BIGINT NOT NULL,
  
  field_name VARCHAR(100) NOT NULL,              -- owner, repo, branch, time_of_day, etc.
  field_label VARCHAR(100) NOT NULL,             -- "Repository Owner", "Daily time", etc.
  field_type ENUM('text', 'number', 'select', 'multiselect', 'date', 'time', 'cron', 'textarea') NOT NULL,
  
  is_required BOOLEAN DEFAULT FALSE,
  is_secret BOOLEAN DEFAULT FALSE,               -- Hide from logs (for tokens)
  
  placeholder VARCHAR(255),
  help_text TEXT,
  validation_regex VARCHAR(500),
  
  -- For select/multiselect
  options JSON,                                  -- [{"label": "main", "value": "main"}, ...]
  
  sort_order INT DEFAULT 0,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  
  FOREIGN KEY (trigger_type_id) REFERENCES trigger_types(id),
  UNIQUE KEY unique_field (trigger_type_id, field_name),
  INDEX idx_trigger_type (trigger_type_id)
);

-- Examples:
-- GitHub: On Push
--   (owner, "Repository Owner", text, required, "mycompany")
--   (repo, "Repository", text, required, "backend")
--   (branch, "Branch", text, optional, "main")
--
-- Schedule: Daily
--   (time_of_day, "Run at time", time, required)
--   (timezone, "Timezone", select, required, ["America/New_York", ...])


-- ════════════════════════════════════════════════════════
-- 4. triggers (Unified instance of any trigger type)
-- ════════════════════════════════════════════════════════
CREATE TABLE triggers (
  id BIGINT PRIMARY KEY AUTO_INCREMENT,
  workflow_id BIGINT NOT NULL UNIQUE,           -- One trigger per workflow
  workspace_id BIGINT NOT NULL,
  
  trigger_type_id BIGINT NOT NULL,
  trigger_category_id BIGINT NOT NULL,
  credential_id BIGINT,                         -- Optional, for auth
  
  -- Display name (optional)
  name VARCHAR(255),
  
  -- Status
  is_active BOOLEAN DEFAULT TRUE,
  is_published BOOLEAN DEFAULT FALSE,           -- Only published triggers run
  
  -- ─────────────────────────────────────────────
  -- WEBHOOK FIELDS (if execution_mode = webhook)
  -- ─────────────────────────────────────────────
  webhook_uuid VARCHAR(36) UNIQUE,              -- Our unique identifier
  webhook_provider VARCHAR(50),                 -- github, slack, stripe, etc.
  webhook_external_id VARCHAR(255),             -- Provider's webhook ID
  webhook_secret LONGTEXT ENCRYPTED,            -- HMAC secret
  webhook_registered_url VARCHAR(255),          -- URL we registered
  webhook_status ENUM('pending', 'active', 'failed'),
  webhook_status_message TEXT,
  
  -- ─────────────────────────────────────────────
  -- POLLING FIELDS (if execution_mode = polling)
  -- ─────────────────────────────────────────────
  polling_interval_seconds INT,                 -- How often to check
  polling_last_check_at TIMESTAMP,              -- Last time we checked
  polling_last_seen_ids JSON,                   -- Dedup: last 1000 IDs
  polling_endpoint_url VARCHAR(255),            -- For generic polling
  
  -- ─────────────────────────────────────────────
  -- SCHEDULE FIELDS (if category = schedule)
  -- ─────────────────────────────────────────────
  schedule_expression VARCHAR(255),             -- Cron or "daily" etc.
  schedule_next_run_at TIMESTAMP,               -- Next scheduled time
  schedule_timezone VARCHAR(50),                -- Europe/London, etc.
  schedule_last_run_at TIMESTAMP,               -- Last time it ran
  
  -- ─────────────────────────────────────────────
  -- ERROR TRACKING
  -- ─────────────────────────────────────────────
  last_error TEXT,
  last_error_at TIMESTAMP,
  consecutive_errors INT DEFAULT 0,
  
  -- ─────────────────────────────────────────────
  -- STATS
  -- ─────────────────────────────────────────────
  trigger_count INT DEFAULT 0,                  -- How many times fired
  last_triggered_at TIMESTAMP,
  
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  
  FOREIGN KEY (workflow_id) REFERENCES workflows(id) ON DELETE CASCADE,
  FOREIGN KEY (workspace_id) REFERENCES workspaces(id),
  FOREIGN KEY (trigger_type_id) REFERENCES trigger_types(id),
  FOREIGN KEY (trigger_category_id) REFERENCES trigger_categories(id),
  FOREIGN KEY (credential_id) REFERENCES credentials(id),
  INDEX idx_workflow (workflow_id),
  INDEX idx_active_published (is_active, is_published),
  INDEX idx_next_run (schedule_next_run_at)
);


-- ════════════════════════════════════════════════════════
-- 5. trigger_field_values
-- ════════════════════════════════════════════════════════
CREATE TABLE trigger_field_values (
  id BIGINT PRIMARY KEY AUTO_INCREMENT,
  trigger_id BIGINT NOT NULL,
  trigger_type_field_id BIGINT NOT NULL,
  value LONGTEXT,                                -- owner=mycompany, repo=backend, etc.
  
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  
  FOREIGN KEY (trigger_id) REFERENCES triggers(id) ON DELETE CASCADE,
  FOREIGN KEY (trigger_type_field_id) REFERENCES trigger_type_fields(id),
  UNIQUE KEY unique_trigger_field (trigger_id, trigger_type_field_id),
  INDEX idx_trigger (trigger_id)
);


-- ════════════════════════════════════════════════════════
-- 6. trigger_executions (Audit trail)
-- ════════════════════════════════════════════════════════
CREATE TABLE trigger_executions (
  id BIGINT PRIMARY KEY AUTO_INCREMENT,
  trigger_id BIGINT NOT NULL,
  workflow_execution_id VARCHAR(36),            -- Links to executions table
  
  -- What fired the trigger
  source ENUM('manual', 'webhook', 'polling', 'schedule') NOT NULL,
  triggered_at TIMESTAMP,
  
  -- The event data that caused execution
  trigger_payload JSON,                         -- Raw webhook data, poll result, etc.
  
  -- Status
  status ENUM('success', 'failed', 'skipped'),
  error_message TEXT,
  
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  
  FOREIGN KEY (trigger_id) REFERENCES triggers(id) ON DELETE CASCADE,
  FOREIGN KEY (workflow_execution_id) REFERENCES executions(id),
  INDEX idx_trigger (trigger_id),
  INDEX idx_created_at (created_at),
  INDEX idx_workflow_execution (workflow_execution_id)
);


-- ════════════════════════════════════════════════════════
-- UPDATE workflows table
-- ════════════════════════════════════════════════════════
-- OLD columns to remove (after migration):
--   trigger_type (moved to triggers.trigger_category)
--   cron_expression (moved to triggers.schedule_expression)
--   webhook_status (moved to triggers.webhook_status)
--   webhook_status_message (moved to triggers.webhook_status_message)
--   next_run_at (moved to triggers.schedule_next_run_at)
--   last_cron_run_at (moved to triggers.schedule_last_run_at)

-- NEW relationship:
--   workflow.trigger() -> HasOne Trigger
```

---

## 🔧 Trigger Category Breakdown

### 1. MANUAL Category
```
Used for: Testing, on-demand execution
┌─────────────────────────────────────────────┐
│ Trigger Type: Manual Trigger                │
│ Execution Mode: manual                      │
│ Config: None                                │
│ Registration: N/A                           │
└─────────────────────────────────────────────┘

Database:
- trigger_categories (1 row): manual
- trigger_types (1 row): Manual Trigger
- trigger_field_values: empty
- No webhook_*, polling_*, schedule_* fields used
```

### 2. SCHEDULE Category
```
Used for: Time-based automation
┌─────────────────────────────────────────────┐
│ Trigger Type: Daily at specific time        │
│ Trigger Type: Weekly on day + time          │
│ Trigger Type: Monthly on date + time        │
│ Trigger Type: Cron Expression (advanced)    │
│                                             │
│ Execution Mode: polling (scheduler checks)  │
│ Config: time, timezone, cron_expression     │
│ Registration: Internal (our scheduler)      │
└─────────────────────────────────────────────┘

Database:
- trigger_categories (1 row): schedule
- trigger_types (4 rows): Daily, Weekly, Monthly, Cron
- Fields: time_of_day, day_of_week, date, timezone, cron_expression
- Uses: schedule_expression, schedule_next_run_at, schedule_timezone
```

### 3. WEBHOOK Category (Generic)
```
Used for: Custom webhooks
┌─────────────────────────────────────────────┐
│ Trigger Type: Custom Webhook                │
│                                             │
│ Execution Mode: webhook                     │
│ Config: path, auth_type, auth_secret        │
│ Registration: Generate unique URL           │
└─────────────────────────────────────────────┘

Database:
- trigger_categories (1 row): webhook
- trigger_types (1 row): Custom Webhook
- Fields: path, auth_type
- Uses: webhook_uuid, webhook_secret
```

### 4. POLLING Category (Generic)
```
Used for: Custom API polling
┌─────────────────────────────────────────────┐
│ Trigger Type: API Polling                   │
│                                             │
│ Execution Mode: polling                     │
│ Config: endpoint_url, interval, dedup_key   │
│ Registration: N/A (external service URL)    │
└─────────────────────────────────────────────┘

Database:
- trigger_categories (1 row): polling
- trigger_types (1 row): API Polling
- Fields: endpoint_url, interval_seconds, dedup_key_path
- Uses: polling_endpoint_url, polling_interval_seconds, polling_last_seen_ids
```

### 5. APP_SPECIFIC Categories (40+ services)
```
Used for: Service integrations
┌─────────────────────────────────────────────┐
│ Category: GitHub                            │
│ ├─ Trigger Type: On Push (webhook)          │
│ ├─ Trigger Type: On Pull Request (webhook)  │
│ ├─ Trigger Type: On Issue (webhook)         │
│ └─ Trigger Type: Issues Updated (polling)   │
│                                             │
│ Category: Slack                             │
│ ├─ Trigger Type: On Message (webhook)       │
│ ├─ Trigger Type: On App Mention (webhook)   │
│ └─ Trigger Type: On File Upload (polling)   │
│                                             │
│ Category: Stripe                            │
│ ├─ Trigger Type: On Charge (webhook)        │
│ ├─ Trigger Type: On Invoice (webhook)       │
│ └─ Trigger Type: On Customer (webhook)      │
│                                             │
│ ... 37 more services ...                    │
└─────────────────────────────────────────────┘

Database:
- trigger_categories (40+): github, slack, stripe, ... zendesk
- trigger_types (150+): per-service trigger types
  - Each either webhook-based or polling-based
  - Zapier-like: instant (webhook) or polling
- Fields: service-specific (owner, repo for GitHub, etc.)
```

---

## 🏗️ Implementation Architecture

### Backend Structure

```
app/Models/
├─ TriggerCategory (generic trigger group)
├─ TriggerType (specific trigger within category)
├─ TriggerTypeField (field definition)
├─ Trigger (instance for a workflow)
├─ TriggerFieldValue (field value for instance)
└─ TriggerExecution (audit trail)

app/Services/
├─ TriggerService
│  ├─ createTrigger(workflow, type, fields, credential)
│  ├─ publishTrigger(trigger)
│  ├─ deleteTrigger(trigger)
│  └─ getAvailableTriggers(category)
│
├─ TriggerRegistrationService
│  ├─ registerWebhookTrigger(trigger)      // GitHub, Slack, etc.
│  ├─ unregisterWebhookTrigger(trigger)
│  └─ verifyWebhookStatus(trigger)
│
├─ TriggerExecutionService
│  ├─ handleWebhookEvent(webhook_uuid, payload)
│  ├─ handlePollingCheck(trigger)
│  ├─ handleScheduledCheck(trigger)
│  └─ executeWorkflow(trigger, payload)
│
└─ TriggerValidationService
   ├─ validateFieldValues(trigger_type, fields)
   ├─ validateConfiguration(trigger)
   └─ validateCredentials(trigger, credential)

app/Jobs/
├─ PollTriggersJob              // Replaces PollTriggersCommand
├─ CheckScheduledTriggersJob    // New: for cron/schedule
├─ RegisterWebhookJob           // Async registration
└─ UnregisterWebhookJob         // Async cleanup

app/Http/Controllers/
├─ TriggerController
│  ├─ store(category, type, fields)         // Create
│  ├─ update(trigger, fields)               // Edit
│  ├─ publish(trigger)                      // Activate
│  ├─ unpublish(trigger)                    // Deactivate
│  ├─ destroy(trigger)                      // Delete
│  └─ getAvailable()                        // List all
│
└─ TriggerWebhookController
   ├─ receive(webhook_uuid, request)        // Unified webhook endpoint
   └─ verify(request)                       // Verify signatures

app/Engine/
├─ WebhookSignatureVerifiers/
│  ├─ GitHubWebhookVerifier
│  ├─ SlackWebhookVerifier
│  ├─ StripeWebhookVerifier
│  └─ ... (per service)
│
├─ PollingHandlers/
│  ├─ GenericApiPoller
│  ├─ GitHubPollingHandler
│  ├─ AirtablePollingHandler
│  └─ ... (per service)
│
└─ ScheduleHandlers/
   ├─ CronExpressionHandler
   ├─ DailyScheduleHandler
   ├─ WeeklyScheduleHandler
   └─ MonthlyScheduleHandler
```

### Frontend Structure

```
src/pages/editor/TriggerBuilder/
├─ TriggerBuilder.tsx               // Main container
│
├─ Steps/
│  ├─ Step1_SelectCategory.tsx      // Manual, Schedule, Webhook, Polling, or Service
│  ├─ Step2_SelectType.tsx          // GitHub: On Push, Schedule: Daily, etc.
│  ├─ Step3_ConfigureFields.tsx     // Owner, Repo, Time, etc.
│  ├─ Step4_ConnectAccount.tsx      // OAuth if needed
│  └─ Step5_TestTrigger.tsx         // Optional: test webhook
│
├─ Components/
│  ├─ CategorySelector.tsx          // Grid of category icons
│  ├─ TypeSelector.tsx              // List of types for category
│  ├─ FieldForm.tsx                 // Dynamic form from trigger_type_fields
│  ├─ CredentialPicker.tsx          // OAuth or existing credential
│  ├─ TriggerPreview.tsx            // Show configured trigger
│  └─ TriggerStatus.tsx             // Active/inactive, last trigger time
│
└─ Hooks/
   ├─ useTriggerCategories()
   ├─ useTriggerTypes()
   ├─ useTriggerFields()
   ├─ useTriggerValidation()
   └─ useTriggerPublish()
```

---

## 🔄 Execution Flows

### Flow 1: Manual Trigger
```
User clicks "Test" or "Run" button
  → TriggerController::testManual()
  → TriggerExecutionService::executeWorkflow()
  → WorkflowEngine runs workflow
  → TriggerExecution logged (source: manual)
```

### Flow 2: Webhook Trigger (GitHub Example)
```
1. User creates GitHub: On Push trigger
   → TriggerService::createTrigger()
   → Store in triggers table
   → TriggerRegistrationService::registerWebhookTrigger()
   → Use GitHubWebhookRegistrar to create webhook
   → Save webhook_uuid, webhook_external_id, webhook_secret
   
2. GitHub sends push event
   → POST /api/triggers/webhooks/{webhook_uuid}
   → TriggerWebhookController::receive()
   → GitHubWebhookVerifier::verifySignature()
   → TriggerExecutionService::handleWebhookEvent()
   → WorkflowEngine runs workflow
   → TriggerExecution logged (source: webhook)
```

### Flow 3: Polling Trigger (Airtable Example)
```
1. User creates Airtable: New Records trigger
   → TriggerService::createTrigger()
   → Store in triggers table (no registration)
   → polling_interval_seconds = 300
   
2. CheckScheduledTriggersJob runs every minute
   → Query triggers where category=polling AND is_published=true
   → For each: check if due (last_check_at + interval < now)
   → AirtablePollingHandler::poll()
   → Get records from Airtable API
   → Compare with polling_last_seen_ids (dedup)
   → TriggerExecutionService::handlePollingCheck()
   → WorkflowEngine runs workflow for NEW records only
   → Update polling_last_seen_ids
   → TriggerExecution logged (source: polling)
```

### Flow 4: Schedule Trigger (Daily Example)
```
1. User creates Daily trigger at 9am EST
   → TriggerService::createTrigger()
   → Store in triggers table
   → schedule_expression = "daily"
   → schedule_next_run_at = tomorrow 9am
   → schedule_timezone = "America/New_York"
   
2. CheckScheduledTriggersJob runs every minute
   → Query triggers where category=schedule AND is_published=true
   → For each: check if schedule_next_run_at < now
   → CronExpressionHandler::calculateNextRun()
   → TriggerExecutionService::handleScheduledCheck()
   → WorkflowEngine runs workflow
   → Update schedule_next_run_at
   → TriggerExecution logged (source: schedule)
```

---

## 📈 Zapier vs n8n Comparison in LinkFlow

| Feature | Zapier | n8n | LinkFlow |
|---------|--------|-----|----------|
| Manual Trigger | ❌ No | ✅ Yes | ✅ Yes |
| Instant Webhooks | ✅ Yes | ✅ Yes (webhook node) | ✅ Yes (webhook mode) |
| Polling | ✅ Yes (1-15 min) | ✅ Yes | ✅ Yes (configurable) |
| Schedule/Cron | ❌ No (costs extra) | ✅ Yes | ✅ Yes (free) |
| App-Specific Triggers | ✅ 6000+ | ✅ 400+ | ✅ Hybrid (semantic) |
| Deduplication | ✅ Auto (hash) | ✅ Auto (configurable) | ✅ Auto (last_seen_ids) |

---

## 📋 Implementation Checklist

- [ ] **Phase 1: Database & Models**
  - [ ] Create 6 new tables (categories, types, fields, triggers, field_values, executions)
  - [ ] Create 6 model classes with relationships
  - [ ] Create seeder for trigger_categories (manual, schedule, webhook, polling)
  - [ ] Create seeder for trigger_types (basic types)
  - [ ] Create seeder for 40+ service integrations

- [ ] **Phase 2: Core Services**
  - [ ] TriggerService (CRUD)
  - [ ] TriggerRegistrationService (webhook registration)
  - [ ] TriggerExecutionService (webhook/polling/schedule handling)
  - [ ] TriggerValidationService (validation)

- [ ] **Phase 3: Jobs & Scheduling**
  - [ ] PollTriggersJob (replace command)
  - [ ] CheckScheduledTriggersJob (new: for schedules)
  - [ ] Update schedule in `console.php`

- [ ] **Phase 4: Controllers & API**
  - [ ] TriggerController (REST endpoints)
  - [ ] TriggerWebhookController (unified webhook handler)
  - [ ] Update routes

- [ ] **Phase 5: Frontend (TriggerBuilder)**
  - [ ] Step 1: Category selection
  - [ ] Step 2: Type selection
  - [ ] Step 3: Field configuration
  - [ ] Step 4: Credential connection
  - [ ] Step 5: Test & preview
  - [ ] Integration into WorkflowEditor

- [ ] **Phase 6: Migration & Testing**
  - [ ] Migration script (webhooks → new triggers)
  - [ ] Test manual triggers
  - [ ] Test webhook triggers (GitHub, Slack)
  - [ ] Test polling triggers
  - [ ] Test schedule triggers
  - [ ] Test signature verification
  - [ ] Test deduplication

---

## 🎯 Key Differences from Previous Plan

✅ **NOW INCLUDES:**
1. Manual triggers (missing before)
2. Schedule/Cron triggers (missed before)
3. Generic custom webhook (missing before)
4. Generic API polling (mentioned but not detailed)
5. Clear execution_mode + zapier_mode distinction
6. Proper categorization (manual, schedule, webhook, polling, app_specific)
7. All n8n trigger types covered
8. All Zapier features covered

❌ **REMOVED:**
- Confusing multiple tables approach
- Overly complex database schema

✨ **BENEFITS:**
- Unified trigger system (one `triggers` table)
- Flexible field system (supports any trigger type)
- Backward compatible with existing webhooks/polling
- Supports full Zapier + n8n feature set
- Easy to add new services

---

## 📚 Sources

- [How Zaps trigger work – Zapier](https://help.zapier.com/hc/en-us/articles/8496244568589-How-Zap-triggers-work)
- [Zapier trigger basics](https://consultevo.com/zapier-trigger-basics-guide/)
- [Types of Triggers in n8n](https://www.c-sharpcorner.com/article/types-of-triggers-in-n8n/)
- [n8n Trigger Nodes: Complete Guide](https://ryanandmattdatascience.com/n8n-trigger-node/)
- [Full guide to n8n triggers](https://agentforeverything.com/n8n-trigger-guide/)

