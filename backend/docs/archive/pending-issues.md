# Pending Issues Tracker

Track backend/API gaps against the n8n-clone requirements.
**Frontend is excluded** — this is API-only.

**Statuses:** `PENDING` · `IN_PROGRESS` · `DONE` · `FAILED` · `SKIP`

---

## How to Use

Update the `Status` column as you work through each item.
Add a `Notes` line below an item when you mark it done or failed.

---

## Section A — Execution Engine Gaps

### A-01 · `on_error` Per-Node Routing
**Status:** `PENDING`

**What the spec says (§19.1):**
Each node has an `on_error` field with three behaviours:
- `stop` — halt execution, mark as error, fire error-workflow if configured
- `continue` — skip the failed item, continue with the remaining items in the batch
- `continue_error_output` — route the failed item to the node's `error` output connection instead

**What exists now:**
Only a binary `fail_on_error` flag inside `HttpRequestNode.php`. No other node type honours `on_error`, and the engine's graph-traversal loop does not read or act on this field.

**Files to touch:**
- `app/Engine/WorkflowEngine.php` — add `on_error` branch in the node-execution loop
- `app/Engine/Nodes/Core/HttpRequestNode.php` — replace `fail_on_error` with `on_error`
- Every other node class that can fail — `CodeNode`, `SubWorkflowNode`, `AppNode` subclasses

**Acceptance:**
A workflow with `on_error = continue` skips a failed item and proceeds; `continue_error_output` routes the bad item to the connected `error` output; `stop` halts as today.

---

### A-02 · Exponential Backoff on Auto-Retry
**Status:** `DONE`

**What the spec says (§19.3):**
When `settings.max_retries > 0`, failed executions are re-queued with exponential backoff:
first retry after `retry_wait` seconds, then `retry_wait × 2`, then `retry_wait × 4`, etc.
Each retry is a new execution record with `mode = retry` and `parent_execution_id` pointing to the original.

**What exists now:**
`retry_wait` is stored on the workflow but no doubling logic exists. The retry job dispatches with a flat delay.

**Files to touch:**
- `app/Jobs/ExecuteWorkflowJob.php` — compute `retry_wait * (2 ** attempt)` before re-dispatch
- `app/Jobs/RetryExecutionJob.php` — same
- `app/Models/Execution.php` — ensure `retries` counter increments on each attempt

**Acceptance:**
Three consecutive failures use delays of 60 s → 120 s → 240 s (given `retry_wait = 60`).

---

### A-03 · Wait Node — "Pause Until Webhook" Mode
**Status:** `DONE`

**What the spec says (§6.2):**
The Wait node has two modes:
1. Pause for a fixed duration (already covered by `DelayNode`)
2. Pause until a specific inbound webhook arrives, then resume the execution

**What exists now:**
`DelayNode` handles the duration mode. The webhook-resume mode does not exist — there is no unique URL generated per-wait, no paused checkpoint linked to it, and no route to receive the resume signal.

**Files to touch:**
- `app/Engine/Nodes/Flow/` — create `WaitNode.php` extending `DelayNode` with a webhook-resume branch
- `app/Engine/Persistence/CheckpointStore.php` — store a `wait_webhook_uuid` alongside the checkpoint
- `routes/api.php` — add `GET|POST /webhook-wait/{uuid}` that looks up the checkpoint and dispatches `ResumeWorkflowJob`
- `app/Jobs/ResumeWorkflowJob.php` — already exists, verify it handles this resume path

**Acceptance:**
A workflow paused at a Wait (webhook mode) resumes exactly once when the generated URL is hit, continuing from the correct checkpoint.

---

### A-04 · Node Versioning and Version Pinning
**Status:** `DONE`

**What the spec says (§6.5):**
- Node types are versioned independently (`version` integer on `node_types`)
- When a workflow is saved, each node stores the version it was built against
- When a node type gets a new version, the API should flag affected workflows
- Upgrading a node migrates parameters where possible; incompatible fields are flagged

**What exists now:**
`node_types.version` column exists. Workflows store nodes as a JSON blob but do not record the version each node was built against. No mismatch detection, no migration logic.

**Files to touch:**
- `app/Engine/NodeRegistry.php` — add `latestVersion(name)` helper
- `app/Models/Workflow.php` / `WorkflowVersion.php` — ensure each node object in `nodes_json` includes `"version": N`
- `app/Services/WorkflowService.php` — on save, stamp current node-type version into each node object
- New endpoint or field on `GET /workflows/{id}` — return `outdated_nodes: [{ nodeId, currentVersion, latestVersion }]`

**Acceptance:**
Saving a workflow stamps node versions. Loading the workflow returns a list of nodes whose version is behind the current registry version.

---

### A-05 · "Run from Node" Execution
**Status:** `PENDING`

**What the spec says (§8.7):**
A user can select any node in the canvas and trigger an execution that starts from that node, re-using the last execution's recorded output for all upstream nodes (so they don't have to re-run the whole workflow to debug a single node).

**What exists now:**
Only `POST /workspaces/{workspace}/workflows/{workflow}/execute` exists, which always starts from the trigger node.

**Files to touch:**
- `app/Http/Controllers/Api/V1/ExecutionController.php` — add `runFromNode(request, workspace, workflow)` action
- `app/Services/ExecutionService.php` — load previous `node_data_json`, inject upstream outputs, then run from the specified node id
- `routes/api.php` — `POST /workspaces/{workspace}/workflows/{workflow}/execute/from-node`

**Acceptance:**
`POST .../execute/from-node` with `{ "node_id": "uuid", "source_execution_id": "uuid" }` runs the workflow from that node using the prior execution's upstream data.

---

### A-06 · "Run with Test Data" Execution
**Status:** `PENDING`

**What the spec says (§8.7):**
Before running, the user can paste a custom JSON payload as the trigger input. The execution runs in manual mode using that JSON as the first node's output.

**What exists now:**
`POST .../execute` accepts no custom input data.

**Files to touch:**
- `app/Http/Controllers/Api/V1/ExecutionController.php` — accept optional `test_data` JSON field
- `app/Services/ExecutionService.php` — if `test_data` is present, inject it as the trigger node's output before graph traversal

**Acceptance:**
`POST .../execute` with `{ "test_data": { "name": "John" } }` passes that JSON as the first item flowing into the workflow.

---

## Section B — Background Jobs

### B-01 · `RefreshOAuthTokenJob`
**Status:** `DONE`

**What the spec says (§17.3):**
A scheduled job runs on the `default` queue, finds all OAuth credentials whose `access_token` expires within 7 days, and refreshes them proactively — before the execution engine encounters an expired token mid-run.

**What exists now:**
`OAuthCredentialFlowService.php` handles the token exchange flow manually (user-initiated). No background job exists to do this automatically.

**Files to touch:**
- `app/Jobs/RefreshOAuthTokenJob.php` — create; query `credentials` for OAuth type with `token_expiry < now() + 7 days`, call `OAuthCredentialFlowService::refresh()`
- `app/Console/Kernel.php` (or `routes/console.php`) — schedule this job daily
- `app/Notifications/` — fire the "OAuth token expiring in 7 days" notification to the credential creator if refresh fails

**Acceptance:**
Running the job refreshes tokens nearing expiry and fires a notification if the refresh fails.

---

## Section C — Notification Events

### C-01 · Execution Failure Notification (to workflow creator)
**Status:** `PENDING`

**What the spec says (§20.2):**
When a workflow execution fails, an email is sent to the workflow creator.

**What exists now:**
`ResetPasswordNotification`, `VerifyEmailNotification`, and `WorkspaceInvitationNotification` exist. No execution-failure notification.

**Files to touch:**
- `app/Notifications/ExecutionFailedNotification.php` — create; includes workflow name, execution ID, error message, link
- `app/Jobs/ExecuteWorkflowJob.php` or `app/Engine/WorkflowEngine.php` — dispatch notification on failure
- `app/Jobs/SendNotificationJob.php` — wire it through the low-priority queue

**Acceptance:**
A failing workflow triggers an email to its creator within the notification delivery window.

---

### C-02 · Consecutive Failure Alert (to team admins)
**Status:** `PENDING`

**What the spec says (§20.2):**
When a workflow fails N consecutive times, an alert is sent to all team admins (not just the creator).

**What exists now:**
Nothing. No consecutive-failure counter, no threshold check, no admin-targeted notification.

**Files to touch:**
- `app/Models/Workflow.php` — add `consecutive_failures` integer column (migration)
- `app/Engine/WorkflowEngine.php` — increment on failure, reset on success
- `app/Notifications/ConsecutiveFailuresNotification.php` — create; threshold configurable (e.g. 3)
- `app/Services/WorkflowService.php` — query workspace admins and dispatch notification when threshold hit

**Acceptance:**
After N consecutive failures, all admins of the workspace receive a notification email.

---

### C-03 · OAuth Token Expiry Notification (7 days warning)
**Status:** `PENDING`

**What the spec says (§20.2):**
When an OAuth credential's access token is expiring within 7 days, the credential creator is notified.

**What exists now:**
No such notification. Tied to `B-01` — the `RefreshOAuthTokenJob` should fire this when refresh fails.

**Files to touch:**
- `app/Notifications/OAuthTokenExpiringNotification.php` — create
- `app/Jobs/RefreshOAuthTokenJob.php` — dispatch this notification when a refresh attempt fails

**Acceptance:**
If `RefreshOAuthTokenJob` cannot refresh a token, the credential creator receives a warning email.

---

### C-04 · Invitation Accepted Notification (to inviter)
**Status:** `PENDING`

**What the spec says (§20.2):**
When an invitee accepts an invitation, the person who sent it receives a confirmation notification.

**What exists now:**
`WorkspaceInvitationNotification` sends to the invitee. Nothing fires back to the inviter on accept.

**Files to touch:**
- `app/Services/InvitationService.php` — after `accept()` succeeds, notify the invitation's `invited_by` user
- `app/Notifications/InvitationAcceptedNotification.php` — create

**Acceptance:**
Accepting an invitation fires an email to the original inviter.

---

### C-05 · Per-User Notification Preference Settings
**Status:** `PENDING`

**What the spec says (§20.3):**
Each user has per-event, per-channel toggles, e.g.:
- `workflow.execution_failed.email = true/false`
- `workflow.execution_failed.slack = true/false`
- `credential.oauth_expiring.email = true/false`

System-level defaults are set by a superadmin.

**What exists now:**
No model, migration, or endpoint for notification preferences.

**Files to touch:**
- Migration: `notification_preferences` table (`user_id`, `event_key`, `channel`, `enabled`)
- `app/Models/User.php` — `notificationPreferences()` relation
- `app/Http/Controllers/Api/V1/UserController.php` — add `GET /user/notification-preferences` and `PUT /user/notification-preferences`
- All notification dispatchers — check preference before sending

**Acceptance:**
A user who disables `workflow.execution_failed.email` stops receiving failure emails.

---

## Section D — Node Implementations

### D-01 · 15 Required Data Transformation Nodes
**Status:** `DONE`

**What the spec says (§6.2 — "must ship in v1"):**

| Node | Category | Description |
|------|----------|-------------|
| JSON Transform | Data | Parse, stringify, pick, omit JSON fields |
| Date & Time | Data | Parse, format, add/subtract date intervals |
| Math | Data | Arithmetic and numeric operations |
| String | Data | Regex, case conversion, trim, pad, split, join |
| Crypto | Data | Hash (MD5, SHA1, SHA256), HMAC, base64 encode/decode |
| XML | Data | Parse XML → JSON and back |
| CSV | Data | Parse CSV string to items or items to CSV |
| HTML Extract | Data | Scrape data from HTML using CSS selectors |
| Rename Keys | Data | Bulk-rename item fields |
| Remove Duplicates | Data | Deduplicate items by a key |
| Sort | Data | Sort items by field |
| Limit | Data | Return only the first N items |
| Summarize | Data | Aggregate: sum, count, avg, min, max by group |
| Filter | Data | Keep/discard items based on conditions |
| Compare Datasets | Data | Diff two sets of items |

**What exists now:**
None of these exist in `app/Engine/Nodes/`. `TransformNode.php` in Core is a generic field-setter, not any of the above.

**Files to create:**
- `app/Engine/Nodes/Data/` — new directory, one file per node
- Each node extends the base node contract and implements `execute(NodePayload): NodeResult`
- Node type seeder — register each in `node_types` table with full `properties_json` schema

**Acceptance:**
All 15 nodes are registered in the node type registry, execute correctly against sample inputs, and are covered by at least one test each.

---

### D-02 · 19 Prioritized v1 Integration Nodes
**Status:** `DONE`

**What the spec says (§6.2 — "prioritized v1 set"):**

| Node | Category |
|------|----------|
| Telegram | Communication |
| Twilio (SMS) | Communication |
| Trello | Project Management |
| GitLab | Developer Tools |
| Jira | Project Management |
| Linear | Project Management |
| HubSpot | CRM |
| Salesforce | CRM |
| Mailchimp | Marketing |
| SendGrid | Email |
| Twitch | Social |
| Twitter/X | Social |
| MySQL | Database |
| PostgreSQL (node) | Database |
| MongoDB | Database |
| Redis (node) | Cache |
| FTP / SFTP | Storage |
| AWS S3 | Storage |
| Dropbox | Storage |

**What exists now:**
None of these exist in `app/Engine/Nodes/Apps/`. Slack, Discord, Gmail, Google Sheets/Calendar/Drive, Airtable, Notion, GitHub, Stripe, OpenAI are done.

**Files to create:**
- `app/Engine/Nodes/Apps/{ServiceName}/` — one directory per service, one node class
- Each needs credential type definition and node type seeder entry

**Acceptance:**
Each node is registered, accepts its credential type, and its primary action (send message, create record, query, etc.) works end-to-end against a live or mocked API.

---

## Section E — Webhook & Binary Handling

### E-01 · Binary File Handling for Webhook Payloads
**Status:** `DONE`

**What the spec says (§18.3):**
When a webhook receives a `multipart/form-data` request with file attachments:
1. Each file is saved to object storage (S3-compatible or local disk)
2. The file metadata (key, filename, size, MIME type, URL) is attached to the item as a `binary` field
3. Binary files are deleted after the execution completes (configurable retention)

**What exists now:**
`WebhookReceiverController` parses JSON and form bodies but does not handle `multipart/form-data` file parts. The `Item` concept in the engine has no `binary` field support.

**Files to touch:**
- `app/Http/Controllers/Api/V1/WebhookReceiverController.php` — detect `multipart`, store files via `Storage::disk()`, build binary metadata
- `app/Engine/Data/` — add `BinaryItem` or extend `OutputBuffer` item structure with `binary` field
- `app/Engine/WorkflowEngine.php` — ensure binary metadata flows through node-to-node like any other item field
- `app/Jobs/ExecuteWorkflowJob.php` — after execution completes, delete uploaded temp files (or schedule cleanup)

**Acceptance:**
Posting a file to `/webhook/{uuid}` with `multipart/form-data` results in the file being accessible as `$item['binary']['data']` inside the first node, and the temp file is cleaned up after execution.

---

## Section F — API Endpoint Gaps

### F-01 · Global Executions Endpoint
**Status:** `PENDING`

**What the spec says (§15.7):**
`GET /executions` — a global, cross-workspace, filterable list of all executions the authenticated user has access to. Supports filters: `status`, `workflow_id`, `date_from`, `date_to`.

**What exists now:**
Only `GET /workspaces/{workspace}/executions` (scoped to one workspace).

**Files to touch:**
- `app/Http/Controllers/Api/V1/ExecutionController.php` — add `indexGlobal()` that queries across all workspaces the user belongs to
- `routes/api.php` — register `GET /api/v1/executions`

**Acceptance:**
`GET /api/v1/executions?status=error` returns failed executions from all workspaces the user is a member of, paginated.

---

### F-02 · Prometheus Metrics Endpoint
**Status:** `PENDING`

**What the spec says (§22.5):**
`GET /api/v1/metrics` returns queue depth, worker health, and execution time histograms per node type in Prometheus text format.

**What exists now:**
Only `GET /api/v1/health` (database + queue status in JSON).

**Files to touch:**
- `app/Http/Controllers/Api/V1/MetricsController.php` — create; pull Horizon queue stats, format as Prometheus `# HELP / # TYPE / metric{labels} value` lines
- `routes/api.php` — register `GET /api/v1/metrics` (IP-whitelisted or token-gated recommended)

**Metrics to expose (minimum):**
```
workflow_executions_total{status="success|error|canceled"}
workflow_execution_duration_seconds{workflow_id, quantile}
queue_depth{queue="critical|high|default|low"}
queue_workers_active{queue}
node_execution_duration_seconds{node_type, quantile}
```

**Acceptance:**
`curl /api/v1/metrics` returns valid Prometheus text that a scraper can ingest without errors.

---

### F-03 · Personal Access Token (PAT) Management Endpoints
**Status:** `PENDING`

**What the spec says (§3.1):**
PATs are a first-class auth method. Users need endpoints to create, list, and revoke their own tokens.

**What exists now:**
Passport is installed and tokens work, but there are no API endpoints for users to manage their tokens — create, name, list, or revoke.

**Files to touch:**
- `app/Http/Controllers/Api/V1/PersonalAccessTokenController.php` — create with `index`, `store`, `destroy`
- `routes/api.php` — register `GET/POST /user/tokens` and `DELETE /user/tokens/{tokenId}`

**Acceptance:**
A user can create a named token, list all their active tokens with last-used timestamps, and revoke any token by ID.

---

### F-04 · Transfer Workspace Ownership
**Status:** `DONE`

**What the spec says (§4.5):**
The current owner can transfer ownership to an existing admin. The original owner is demoted to admin.

**What exists now:**
`WorkspaceMemberController` handles role changes but has no dedicated transfer-ownership action. A role update could theoretically do it, but there is no guard that: (a) requires the new owner to be an existing admin, and (b) automatically demotes the old owner.

**Files to touch:**
- `app/Http/Controllers/Api/V1/WorkspaceMemberController.php` — add `transferOwnership(request, workspace, user)` action
- `app/Services/WorkspaceService.php` — atomic transaction: demote old owner → promote new owner
- `routes/api.php` — `POST /workspaces/{workspace}/transfer-ownership`

**Acceptance:**
Only the current owner can call this. The new owner must currently be an admin. Calling it swaps roles atomically — both succeed or neither does.

---

## Section G — Code Quality Fixes

### G-01 · Replace `auth()->user()` with `$request->user()` in Base Controller
**Status:** `PENDING`

**Why this matters:**
`auth()->user()` relies on the global auth guard resolver and can return the wrong user in stateless API contexts where multiple guards are configured. `$request->user()` is always correct in a middleware-resolved request context.

**Files to touch:**
- `app/Http/Controllers/Controller.php` (base controller) — find all `auth()->user()` usages and replace
- Any other controller that calls `auth()->user()` directly — grep: `grep -rn "auth()->user()" app/Http/Controllers/`

**Acceptance:**
`grep -rn "auth()->user()" app/Http/Controllers/` returns zero results.

---

### G-02 · Move `config()` Calls Out of Service Method Bodies
**Status:** `PENDING`

**Why this matters:**
Calling `config('key')` inside a method body is re-resolved on every call and cannot be mocked in tests. Config values should be read once in the constructor or injected via the service provider.

**Files to touch:**
- Run `grep -rn "config(" app/Services/ --include="*.php"` to find all offenders
- For each: move `config('...')` to a constructor parameter or a `private readonly string $value` property set in `__construct`

**Acceptance:**
All services that need config values receive them via constructor injection. `grep -rn "config(" app/Services/` returns only legitimate dynamic lookups (if any remain).

---

### G-03 · Teams vs. Workspaces Naming Alignment
**Status:** `PENDING`

**Why this matters:**
The spec, Postman collection, and frontend all use "team" / `team_id`. The entire backend uses "workspace" / `workspace_id`. This means every API path, every foreign key name, and every JSON response key differs from the spec. The frontend will be misaligned from day one unless one side is changed.

**Decision required before working on this:**
Choose a direction — either rename the backend to match the spec (`workspace` → `team`) or update the spec and frontend contract to use `workspace`. This is a large rename that touches routes, models, migrations (via aliases), and tests.

**Files affected (if renaming backend to match spec):**
- All routes containing `workspaces` and `{workspace}`
- `app/Models/Workspace.php` → `Team.php`
- `app/Http/Controllers/Api/V1/WorkspaceController.php` → `TeamController.php`
- All other controllers that type-hint `Workspace`
- All migrations referencing `workspace_id`
- `app/Services/WorkspaceService.php` → `TeamService.php`

**Acceptance:**
`GET /api/v1/teams` returns the same data as `GET /workspaces` does today. All spec-defined paths work.

---

*Last updated: March 26, 2026*
*Frontend items intentionally excluded from this document.*
