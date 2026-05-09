# Workflow Automation Engine

## Overview
A Laravel 12-based Workflow Automation Engine that allows creating, managing, and executing complex workflows via a REST API. It supports integrations with external services and features AI-driven workflow generation and diagnostics.

## Node Library (Section D — Complete)

**D-01 Data Transformation Nodes** (all in `App\Engine\Nodes\Apps\Data\DataNode`, type prefix `data.*`):
json_transform, date_time, math, string, crypto, xml, csv, html_extract, rename_keys, remove_duplicates, sort, limit, summarize, advanced_filter, compare_datasets

**D-02 Integration Nodes** (each in its own `Apps/{Name}/{Name}Node`):
- Communication: `telegram`, `twilio` (SMS + WhatsApp)
- Project Management: `trello`, `gitlab`, `jira`, `linear` (GraphQL)
- CRM / Marketing: `hubspot`, `salesforce`, `mailchimp`, `sendgrid`
- Social: `twitch`, `twitter` (API v2)
- Databases: `mysql` (PDO), `postgresql` (PDO + upsert), `mongodb` (Atlas Data API), `redis`
- Storage / Files: `ftp`, `aws_s3` (SigV4 signing, no SDK), `dropbox`

**Pre-existing Nodes**: Slack, Discord, GitHub, Google (Sheets/Drive/Calendar/Gmail), Airtable, Notion, Stripe, OpenAI, AI (LLM), Mail, Storage

## Architecture
- **Backend**: Laravel 12 (PHP 8.2+) REST API
- **Database**: PostgreSQL (Replit managed)
- **Queue**: Database-backed (no Redis available in this environment)
- **Cache**: Database-backed
- **Authentication**: Laravel Passport (OAuth2)
- **Frontend Assets**: Vite + Tailwind CSS v4
- **AI**: laravel/ai package with custom agents
- **Billing**: Laravel Cashier (Stripe)
- **Background Jobs**: Laravel Horizon (configured for Redis, using database queue in dev)
- **Monitoring**: Laravel Pulse
- **Testing**: Pest PHP 4

## Engine Architecture (restructured — single-responsibility modules)

The monolithic `WorkflowEngine.php` has been split into clean, single-responsibility modules:

### `app/Engine/Graph/`
- `WorkflowGraph` — immutable compiled DAG (nodes, edges, successors, predecessors)
- `GraphCompiler` — compiles raw workflow version data into a WorkflowGraph
- `ExpressionParser` — evaluates `{{ expression }}` templates in node configs

### `app/Engine/Execution/`
- `NodePayload` — immutable value object passed to every node handler
- `NodePayloadFactory` — resolves inputs, credentials, config and builds a NodePayload
- `OutputBuffer` — in-memory store of per-node outputs keyed by node ID
- `Suspension` — checkpoint value object (resumeAt, reason, nodeOutput, resumePayload)
- `SyncExecutor` — runs a single node synchronously; returns a NodeResult
- `AsyncExecutor` — runs a batch of nodes concurrently via Laravel Concurrency
- `ExecutionScheduler` — partitions ready nodes into sync / async / blocking buckets
- `ExecutionFinalizer` — handles terminal success/failure: updates status, fires events, schedules retries

### `app/Engine/Sse/`
- `SsePublisher` — publishes real-time Redis SSE events (nodeStarted, nodeCompleted, etc.)

### `app/Agents/` (replaces `app/Ai/`)
- `Contracts/AgentRunnable` — interface for agent runner
- `Internal/` — all internal Laravel AI agents (WorkflowAgent, ChatAgent, SentimentAgent, SummarizerAgent, TextClassifierAgent, StructuredExtractAgent, VisionAgent, WorkflowBuilderAgent, WorkflowDescriptionAgent, ErrorDiagnosisAgent)
- `Runner/AgentRunner` — builds and runs user-defined agents with skill context + tools
- `Skills/SkillContextBuilder` — selects relevant skills for a message via keyword scoring
- `Skills/SkillScriptTool` — wraps an AgentSkillScript as a callable Laravel AI tool
- `Tools/` — all standalone tools: AgentTool, WorkflowTool, WorkflowNodeTool, UpdateSkillTool, ListAvailableNodesTool, InspectNodeSchemaTool

## Key Features
- Graphical Workflow Engine with DAG execution
- Multi-tenant Workspace management
- AI-powered workflow building and diagnostics
- **Gumloop-equivalent Agent & Skills system** (see below)
- OAuth2 API authentication
- Credit-based billing with Stripe
- Synchronous and asynchronous node execution
- Async webhook architecture (GitHub, Stripe, Slack, Discord) with auto-registration, URL stability, and health monitoring

## Agent & Skills System (fully implemented)

First-class autonomous agent platform — equivalent to Gumloop's agent product — fully integrated into the LinkFlow workspace.

### Database Tables (8 new migrations)
- `agents` — Agent definitions: name, instructions, model/provider, max_steps, timeout, metadata
- `agent_tool_configs` — Per-agent tool configs: which nodes the agent can use and with what description
- `agent_skills` — Reusable skill documents: instructions, versioned, shareable across workspace
- `agent_agent_skill` — Pivot: many-to-many between agents and skills with sort_order
- `agent_skill_references` — Reference content blocks attached to skills (FAQ docs, examples, etc.)
- `agent_skill_scripts` — PHP/JS scripts attached to skills — callable as tools by the agent
- `agent_triggers` — Schedule/webhook/event triggers that fire agents autonomously
- `agent_conversations` — Updated: `agent_id` + `workspace_id` columns added

### Models
`Agent`, `AgentToolConfig`, `AgentSkill`, `AgentSkillReference`, `AgentSkillScript`, `AgentTrigger`

### AI Layer (`app/Ai/`)
- `Contracts/AgentRunnable.php` — Interface for all agent runners
- `Runners/AgentRunner.php` — Core runner: loads agent + skills, builds system prompt, wires tools, calls WorkflowAgent
- `Skills/SkillContextBuilder.php` — Keyword scoring to select only relevant skills per message (prevents token bloat)
- `Skills/SkillScriptTool.php` — Wraps an AgentSkillScript as a callable Laravel AI tool (PHP + Node.js execution)
- `Tools/WorkflowTool.php` — Lets the agent trigger a saved workflow
- `Tools/UpdateSkillTool.php` — Self-improvement: agent can rewrite a skill's instructions and increment its version
- `Tools/AgentTool.php` — Multi-agent: lets one agent call another as a tool (orchestration)

### Engine Node
- `AgentNode` (`type: agent`) — Use a saved agent inside any workflow. Config: `agent_id` + `message` (expression-compatible). Registered in `NodeType` enum.

### Services
- `AgentService` — create, update, delete, duplicate agents + syncToolConfigs
- `AgentConversationService` — start/continue conversations, integrate with Laravel AI SDK conversation storage

### Jobs
- `RunAgentJob` — Queued on `workflows-default`, executes an agent from a trigger, logs result, updates `last_fired_at`

### Permissions (added to `Permission` enum)
`agent.view`, `agent.create`, `agent.update`, `agent.delete`

### API Endpoints (all workspace-scoped, auth required)

**Agents** (`/workspaces/{workspace}/agents`):
- `GET /` — list (search, is_active filter, pagination)
- `POST /` — create with optional tools array
- `GET /{agent}` — show with skills, triggers, tool configs
- `PUT /{agent}` — update
- `DELETE /{agent}` — soft delete
- `POST /{agent}/duplicate`
- `POST /{agent}/skills/attach` — attach a skill
- `DELETE /{agent}/skills/{skillId}` — detach a skill

**Conversations** (`/agents/{agent}/conversations`):
- `GET /` — list conversations
- `POST /` — start a new conversation (runs agent immediately)
- `GET /{conversationId}` — show with messages
- `DELETE /{conversationId}`
- `POST /{conversationId}/messages` — send a follow-up message

**Triggers** (`/agents/{agent}/triggers`):
- `GET /`, `POST /`, `PUT /{trigger}`, `DELETE /{trigger}`
- `POST /{trigger}/fire` — manually fire a trigger (dispatches RunAgentJob)

**Agent Skills** (`/workspaces/{workspace}/agent-skills`):
- `GET /`, `POST /`, `GET /{skill}`, `PUT /{skill}`, `DELETE /{skill}`
- `POST /{skill}/references`, `PUT /{skill}/references/{id}`, `DELETE /{skill}/references/{id}`
- `POST /{skill}/scripts`, `PUT /{skill}/scripts/{id}`, `DELETE /{skill}/scripts/{id}`

## Webhook Architecture
See `docs/WEBHOOK_ARCHITECTURE.md` for full details.

- **Receiving** — `WebhookReceiverController` handles incoming events; synchronous handshakes (Slack challenge, Discord PING) returned inline; all other events dispatched via `WebhookProcessingJob` for async processing (< 50ms response).
- **Registration** — `WebhookRegistrationJob` + `WebhookAutoRegistrationService` auto-register webhooks on activation; `supportsAutoRegistration()` guards Discord (manual only); URL stability check re-registers when `APP_URL` changes.
- **Providers** — GitHub (HMAC-SHA256), Stripe (timestamp+HMAC), Slack (HMAC + url_verification challenge), Discord (Ed25519, manual setup).
- **Health** — `php artisan webhooks:health-check` runs daily at 03:00; detects silently broken webhooks and dispatches re-registration jobs.
- **DB columns** — `webhooks.registered_url` stores the URL at registration time; `workflows.webhook_status` / `webhook_status_message` surface registration state to the API.

## Notification System (fully implemented)

**Architecture**: Multi-channel, per-user preference-driven, queue-backed delivery.

**Delivery Channels** (all supported simultaneously per notification):
- `database` — In-app bell/notification centre (stored in `notifications` table)
- `mail` — Email via Laravel Mail (user's primary email)
- `slack` — Slack incoming webhook (URL stored in `notification_channels`)
- `discord` — Discord channel webhook (URL stored in `notification_channels`)
- `webhook` — Generic HTTP POST with HMAC-SHA256 signing (stored in `notification_channels`)
- `sms` — SMS via Twilio (phone stored in `notification_channels`)

**User Notification Types** (`app/Enums/NotificationType.php`):
- Execution: `execution.failed`, `execution.succeeded`, `execution.retrying`, `execution.timed_out`
- Quota: `quota.warning` (80%), `quota.exceeded` (100% — executions paused)
- Workspace: `workspace.invitation`, `workspace.member_joined`, `workspace.member_removed`
- Billing: `billing.payment_failed`, `billing.payment_succeeded`, `billing.subscription_expiring`, `billing.subscription_cancelled`, `billing.trial_ending`

**Admin Alert Types** (sent to admin emails/webhooks from config):
- `admin.execution_overflow` — concurrent execution threshold exceeded
- `admin.high_error_rate` — error rate spike in sliding window
- `admin.suspicious_activity` — brute force, unusual patterns
- `admin.new_signup` — new user registered
- `admin.system_health` — disk usage, queue worker down, etc.

**Key Files**:
- `app/Services/NotificationService.php` — user notification orchestrator
- `app/Services/AdminAlertService.php` — platform admin alerting
- `app/Notifications/Channels/` — Slack, Discord, Webhook, SMS channel drivers
- `app/Notifications/Admin/` — admin alert notification classes
- `app/Models/NotificationPreference.php` — per-user type/channel preferences
- `app/Models/NotificationChannel.php` — stored delivery endpoints (encrypted config)

**API Endpoints** (all under `/api/v1/`, auth required):
- `GET /notifications` — paginated list, `?unread=1` filter
- `GET /notifications/unread-count`
- `POST /notifications/{id}/read`, `POST /notifications/read-all`
- `DELETE /notifications/{id}`, `DELETE /notifications`
- `GET /notification-preferences`, `PUT /notification-preferences`
- `GET /notification-channels`, `POST/PUT/DELETE /notification-channels/{id}`
- `POST /notification-channels/{id}/test` — sends a live test message

**Admin Config** (env vars):
- `ADMIN_ALERT_EMAIL` — comma-separated admin emails
- `ADMIN_SLACK_WEBHOOK_URL`, `ADMIN_DISCORD_WEBHOOK_URL`, `ADMIN_WEBHOOK_URL`
- `ADMIN_ALERT_ERROR_RATE_PERCENT` (default 20%), `ALERT_MAX_CONCURRENT_EXECUTIONS` (default 100)

## Project Structure
- `app/Engine/` - Core workflow runtime (nodes, runners, persistence)
- `app/Ai/` - AI agents and tools
- `app/Http/Controllers/Api/V1/` - REST API controllers
- `app/Models/` - Eloquent models
- `app/Services/` - Business logic
- `app/Notifications/` - All notification classes (user + admin)
- `app/Listeners/` - Event listeners for auto-notifications
- `routes/api.php` - API routes
- `database/migrations/` - Database schema
- `resources/` - Frontend assets

## Environment Setup
- PHP dependencies installed via `composer install --no-interaction --ignore-platform-reqs`
- Node dependencies installed via `npm install`
- Database: PostgreSQL via Replit managed DB (`PGHOST=helium`, `PGDATABASE=heliumdb`)
- Queue/Cache: Database driver (Redis not available)
- Frontend assets built via `npm run build`

## Running the App
The app runs via `php artisan serve --host=0.0.0.0 --port=5000`

## Environment Variables
All configuration is stored as Replit environment variables:
- `APP_KEY` - Laravel encryption key
- `DB_CONNECTION=pgsql`, `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`
- `QUEUE_CONNECTION=database`
- `CACHE_STORE=database`
- `SESSION_DRIVER=database`

## Notes
- Composer lock file has PHP 8.3+ constraint issues; use `--ignore-platform-reqs` flag
- Redis is not available; queue and cache use database driver instead
- Laravel Passport OAuth keys are installed in storage/

## Performance Optimisations Applied
- **`app/Engine/WorkflowEngine.php`**: Combined two separate workflow counter DB calls (`increment` + `update`) into a single query. Added per-execution SSE throttling — node-level events are suppressed after 200 events per execution to reduce Redis overhead on large workflows. Execution-level events always fire.
- **`app/Engine/Runners/AsyncRunner.php`**: Raised the inline execution threshold from 2 to 3 nodes, avoiding child-process spawn overhead for small async batches.
- **`app/Engine/Data/OutputBuffer.php`**: Replaced the `json_encode`-based memory size estimator in `memoryUsage()` with a cheap recursive byte estimator, eliminating a full serialisation pass on every memory check.
- **`config/workflow.php`**: Added `WORKFLOW_SSE_NODE_THRESHOLD` env var (default 200) to control the SSE throttle cutoff.
- **`config/horizon.php`**: Enabled `fast_termination` in production. Raised `supervisor-workflows` production `minProcesses` from 5 to 8 and `balanceCooldown` from 3s to 2s for faster burst handling.
