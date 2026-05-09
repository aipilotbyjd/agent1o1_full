# Frontend Workflow Editor — API Usage Plan

**A practical screen-by-screen mapping: which backend API to call for every frontend action in the workflow editor.**

> This document maps UI screens and user actions to exact API endpoints, request bodies, and response handling patterns.

---

## Table of Contents

1. [Dashboard / Workflow List Page](#1-dashboard--workflow-list-page)
2. [Create New Workflow (Shell)](#2-create-new-workflow-shell)
3. [Canvas Editor — Load](#3-canvas-editor--load)
4. [Canvas Editor — Node Palette](#4-canvas-editor--node-palette)
5. [Canvas Editor — Save & Publish](#5-canvas-editor--save--publish)
6. [Canvas Editor — Version History](#6-canvas-editor--version-history)
7. [Node Configuration Sidebar](#7-node-configuration-sidebar)
8. [Expression Editor & Data Mapping](#8-expression-editor--data-mapping)
9. [Triggers Panel](#9-triggers-panel)
10. [Execution Panel — Manual Trigger](#10-execution-panel--manual-trigger)
11. [Execution Panel — Real-Time Monitoring](#11-execution-panel--real-time-monitoring)
12. [Execution Panel — History & Details](#12-execution-panel--history--details)
13. [Credentials Management Modal](#13-credentials-management-modal)
14. [Variables Panel](#14-variables-panel)
15. [Folders & Organization](#15-folders--organization)
16. [Tags Management](#16-tags-management)
17. [AI Workflow Builder](#17-ai-workflow-builder)
18. [Workflow Properties / Settings](#18-workflow-properties--settings)
19. [Sticky Notes on Canvas](#19-sticky-notes-on-canvas)
20. [Pinned Test Data](#20-pinned-test-data)

---

## 1. Dashboard / Workflow List Page

### On Page Load

**Call:**

```http
GET /api/v1/workspaces/{workspace}/workflows
```

**Query parameters to support:**

| UI Control | Query Param | Example |
|------------|-------------|---------|
| Search box | `search` | `?search=email` |
| Active filter | `is_active` | `?is_active=true` |
| Tag filter | `tag` | `?tag=production` |
| Sort dropdown | `sort_by` + `sort_direction` | `?sort_by=last_executed_at&sort_direction=desc` |
| Pagination | `page` + `per_page` | `?page=1&per_page=20` |

**Display fields from response `data[]`:**
- `name`, `description`, `icon`, `color`
- `is_active` — show active/paused badge
- `execution_count` — show run count
- `last_executed_at` — show "Last run 2h ago"
- `success_rate` — show percentage badge
- `current_version.trigger_type` — show trigger icon (webhook/schedule/manual)

### Create New Workflow Button

**Call:**

```http
POST /api/v1/workspaces/{workspace}/workflows
```

**Body (minimal):**

```json
{
  "name": "Untitled Workflow",
  "description": "",
  "icon": "zap",
  "color": "#6366f1"
}
```

**After 201 response:** Redirect to `/workflows/{response.data.id}/edit` to open the canvas.

### Duplicate Workflow Action (Menu)

**Call:**

```http
POST /api/v1/workspaces/{workspace}/workflows/{workflow}/duplicate
```

**After 201 response:** Refresh list or redirect to new workflow editor.

### Toggle Active/Inactive (Switch)

**Call:**

```http
POST /api/v1/workspaces/{workspace}/workflows/{workflow}/activate
POST /api/v1/workspaces/{workspace}/workflows/{workflow}/deactivate
```

**After response:** Update `is_active` badge locally. Also watch `webhook_status` in the response — if `"pending"`, show a spinner; if `"failed"`, show an error tooltip.

### Delete Workflow (Confirm Dialog)

**Call:**

```http
DELETE /api/v1/workspaces/{workspace}/workflows/{workflow}
```

**After 200:** Remove from list locally or refresh.

---

## 2. Create New Workflow (Shell)

**Alternative: AI-Generated Workflow**

Instead of creating an empty shell, call the AI builder immediately:

```http
POST /api/v1/workspaces/{workspace}/workflows/build
```

**Body:**

```json
{
  "description": "When a webhook is received, validate the email, and send a welcome email via SendGrid"
}
```

**Response (201):** Full `WorkflowResource` with `current_version` already populated. Redirect to editor with pre-loaded graph.

---

## 3. Canvas Editor — Load

### On Editor Open (Route: `/workflows/{id}/edit`)

**Call 1 — Workflow Metadata:**

```http
GET /api/v1/workspaces/{workspace}/workflows/{workflow}
```

**Use `data` fields for:**
- Top bar: `name`, `description`, `is_active`, `is_locked`
- Properties panel: `icon`, `color`, `error_workflow_id`, `trigger_type`, `cron_expression`

**Call 2 — Current Version (The Graph):**

The workflow response includes `current_version` when loaded. If not, make a separate call:

```http
GET /api/v1/workspaces/{workspace}/workflows/{workflow}/versions/{current_version_id}
```

**Use these fields to render the canvas:**

```json
{
  "nodes": [
    {
      "id": "trigger_1",
      "type": "trigger",
      "position": { "x": 100, "y": 100 },
      "data": { "label": "Start", "trigger_type": "webhook" }
    }
  ],
  "edges": [
    {
      "source": "trigger_1",
      "target": "http_1",
      "sourceHandle": "output",
      "targetHandle": "input"
    }
  ],
  "viewport": { "x": 0, "y": 0, "zoom": 1 },
  "settings": { "timeout": 300, "max_retries": 3 }
}
```

**Restore canvas state:**
1. Deserialize `nodes` → render React Flow / XYFlow nodes
2. Deserialize `edges` → render connections
3. Apply `viewport` → set camera position and zoom
4. `settings` → populate settings panel

---

## 4. Canvas Editor — Node Palette

### On Palette Mount

**Call:**

```http
GET /api/v1/nodes?is_active=true&per_page=100
GET /api/v1/node-categories
```

**Group nodes by `category.name`** for the palette sidebar.

**Palette item display:**
- `name` — label
- `icon` — icon name
- `color` — category/node accent color
- `node_kind` — badge (Trigger, Action, Flow, AI, etc.)
- `is_premium` — show premium badge if user's plan doesn't include it
- `credential_type` — show "Requires credential" hint

### On Drag from Palette to Canvas

When user drops a node, create a new canvas node with:

```json
{
  "id": "{type}_{random_id}",      // e.g., "http_request_a7f3"
  "type": "http_request",
  "position": { "x": dropX, "y": dropY },
  "data": {
    "label": "HTTP Request",
    "...node-specific-defaults": "..."
  }
}
```

**The node `id` must be unique** within the workflow. Use `{node_type}_{short_random}` pattern.

### On Node Double-Click / Select

**Call:**

```http
GET /api/v1/nodes/{node_type}
```

**Use response to render the configuration form:**
- `config_schema` — JSON Schema for form fields (text inputs, dropdowns, toggles, code editors)
- `input_schema` — What data this node expects
- `output_schema` — What data this node produces (used for expression autocomplete)
- `credential_type` — If not null, show credential selector dropdown
- `docs_url` — Link to documentation
- `cost_hint_usd` + `latency_hint_ms` — Show estimated cost/time badge

---

## 5. Canvas Editor — Save & Publish

### Auto-Save / Manual Save (Create Draft Version)

**Call:**

```http
POST /api/v1/workspaces/{workspace}/workflows/{workflow}/versions
```

**Body — serialize current canvas state:**

```json
{
  "name": null,
  "description": null,
  "trigger_type": "webhook",
  "trigger_config": { "http_method": "POST" },
  "nodes": [
    {
      "id": "trigger_1",
      "type": "trigger",
      "position": { "x": 100, "y": 100 },
      "data": { "label": "Start", "trigger_type": "webhook" }
    },
    {
      "id": "http_1",
      "type": "http_request",
      "position": { "x": 400, "y": 100 },
      "data": {
        "label": "Get User",
        "url": "https://api.example.com/users/{{ $trigger.body.user_id }}",
        "method": "GET",
        "headers": { "Authorization": "Bearer {{ $vars.api_token }}" }
      }
    }
  ],
  "edges": [
    {
      "source": "trigger_1",
      "target": "http_1",
      "sourceHandle": "output",
      "targetHandle": "input"
    }
  ],
  "viewport": { "x": 0, "y": 0, "zoom": 1 },
  "settings": {
    "timeout": 300,
    "max_retries": 3,
    "error_workflow_id": null
  },
  "change_summary": "Added HTTP request node"
}
```

**Important rules:**
- `nodes` is required, minimum 1 node
- `edges` is required, can be empty array `[]`
- Every node must have `id` and `type`
- Every edge must have `source` and `target`

**After 201:** Show "Saved as v{version_number}" toast. Update `current_version_id` locally.

### Publish (Go Live)

**Call:**

```http
POST /api/v1/workspaces/{workspace}/workflows/{workflow}/versions/{version}/publish
```

**Effect:** Sets `is_published = true`, updates workflow `current_version_id`. The engine will now execute this version.

**UI Pattern:**
- Save button → creates a new version (draft)
- Publish button → publishes the latest version
- Show "Unpublished changes" badge when current canvas differs from published version

---

## 6. Canvas Editor — Version History

### Open Version History Panel

**Call:**

```http
GET /api/v1/workspaces/{workspace}/workflows/{workflow}/versions
```

**Display as timeline:**
- `version_number` — badge
- `change_summary` — description
- `creator.name` — author
- `created_at` — timestamp
- `is_published` — highlight the live version

### View Old Version (Read-Only Canvas)

**Call:**

```http
GET /api/v1/workspaces/{workspace}/workflows/{workflow}/versions/{version}
```

**Use `nodes`, `edges`, `viewport` to render read-only canvas.** Show "Viewing v3 — read only" banner.

### Compare Two Versions

**Call:**

```http
GET /api/v1/workspaces/{workspace}/workflows/{workflow}/versions/diff?from={version_a}&to={version_b}
```

**Response `data`:**
- `added_nodes` — green highlight
- `removed_nodes` — red highlight
- `modified_nodes` — yellow highlight with field-level changes
- `added_edges` / `removed_edges`

**Render:** Side-by-side canvas or diff tree.

### Rollback to Previous Version

**Call:**

```http
POST /api/v1/workspaces/{workspace}/workflows/{workflow}/versions/{version}/rollback
```

**Effect:** Creates a **new** version copied from the old one and publishes it. Returns the new version (201).

**After response:** Reload canvas with the new version's graph.

---

## 7. Node Configuration Sidebar

### When a Node is Selected on Canvas

Render a form based on the node's `config_schema` from `GET /api/v1/nodes/{type}`.

**Dynamic fields to support from `config_schema`:**

| Schema Type | UI Component |
|-------------|-------------|
| `string` + `format: "uri"` | URL input |
| `string` + `enum` | Dropdown select |
| `string` (large) | Textarea / Code editor |
| `integer` | Number input |
| `boolean` | Toggle switch |
| `object` | Nested form / key-value editor |
| `array` | Repeating fields |

### Credential Selector (If `credential_type` is not null)

**Call:**

```http
GET /api/v1/workspaces/{workspace}/credentials?type={credential_type}
```

**Display:** Dropdown of credentials matching the required type. Show `name` and masked preview.

**Store in node data:** Save `credential_id` in the node's `data` object.

### Expression Input Fields

For any string field that supports expressions (usually marked in schema or all string fields):

**Show expression picker with these sources:**
1. **Trigger data:** From the trigger node's output schema
2. **Upstream nodes:** Iterate connected predecessors, show their `output_schema` fields
3. **Variables:** From `GET /api/v1/workspaces/{workspace}/variables`
4. **Execution metadata:** `execution.id`, `execution.started_at`

**Autocomplete format:**

```
{{ $nodes.http_1.output.body.id }}
{{ $trigger.body.email }}
{{ $vars.api_base_url }}
{{ $execution.id }}
```

---

## 8. Expression Editor & Data Mapping

### Expression Autocomplete Data Sources

**1. Upstream Node Outputs:**

Traverse `edges` backwards from selected node to find all predecessors. For each predecessor:

```
GET /api/v1/nodes/{predecessor.type}  // fetch output_schema
```

Build autocomplete tree:
```
$nodes.{predecessor_id}.output.{field}
```

**2. Workspace Variables:**

```http
GET /api/v1/workspaces/{workspace}/variables
```

Build autocomplete:
```
$vars.{key}
```

**3. Trigger Data:**

From the first node's `trigger_type` and `trigger_config`, infer trigger output shape. Or fetch trigger node schema:

```http
GET /api/v1/nodes/trigger.{trigger_type}
```

### Expression Validation

**Client-side only:**
- Check `{{ ... }}` syntax is balanced
- Verify referenced `node_id` exists in the graph
- Verify referenced `field` exists in `output_schema`
- Show red underline + tooltip for invalid references

---

## 9. Triggers Panel

### Display Current Trigger

Read from workflow's `current_version.trigger_type` and `current_version.trigger_config`.

### Webhook Trigger Setup

**Create webhook (after selecting webhook trigger type):**

```http
POST /api/v1/workspaces/{workspace}/workflows/{workflow}/webhook
```

**Body:**

```json
{
  "path": "welcome-hook",
  "methods": ["POST"],
  "auth_type": "header",
  "auth_config": {
    "header_name": "X-Secret",
    "header_value": "my-secret"
  },
  "rate_limit": 100,
  "response_mode": "immediate",
  "response_status": 200,
  "response_body": { "received": true }
}
```

**Display to user:** The generated URL from response:
```
https://your-app.com/api/v1/webhook/{response.data.uuid}
```

**Copy-to-clipboard button** for the URL.

**Show call count and last called time** from `call_count` and `last_called_at`.

### Polling Trigger Setup

**Create polling trigger:**

```http
POST /api/v1/workspaces/{workspace}/workflows/{workflow}/polling-trigger
```

**Body:**

```json
{
  "endpoint_url": "https://api.example.com/new-items",
  "http_method": "GET",
  "headers": { "Authorization": "Bearer token" },
  "query_params": { "limit": 50 },
  "dedup_key": "id",
  "interval_seconds": 300
}
}
```

**Display:** Next poll time from `next_poll_at`, poll count, trigger count, last error.

### Schedule/Cron Trigger

Store in the version's `trigger_config`:

```json
{
  "trigger_type": "schedule",
  "trigger_config": {
    "cron": "0 9 * * 1",
    "timezone": "America/New_York"
  }
}
```

The backend cron runner (`ScheduleCronWorkflows` command) reads this from the workflow record.

---

## 10. Execution Panel — Manual Trigger

### Trigger Button ("Test Workflow")

**Call:**

```http
POST /api/v1/workspaces/{workspace}/workflows/{workflow}/execute
```

**Body (optional test data):**

```json
{
  "trigger_data": {
    "email": "test@example.com",
    "name": "Test User"
  }
}
```

**Requirements checked before enabling button:**
- `workflow.is_active === true`
- `workflow.current_version_id !== null` (has published version)
- User has `workflow.execute` permission

**Rate limit warning:** Show "Please wait" if 429 is returned.

**After 201 response:**
- `response.data.id` — execution ID
- `response.data.status` — usually `"pending"` or `"running"`
- **Immediately connect SSE stream** to monitor progress

---

## 11. Execution Panel — Real-Time Monitoring

### Connect SSE Stream

```http
GET /api/v1/workspaces/{workspace}/executions/{execution}/stream
```

**Headers:**
```http
Accept: text/event-stream
Last-Event-ID: 0-0
```

**Handle events:**

| SSE Event | UI Action |
|-----------|-----------|
| `execution.started` | Start progress bar, set status "Running" |
| `execution.node_started` | Highlight node on canvas, show spinner on node |
| `execution.node_completed` | Mark node green on canvas, show duration |
| `execution.suspended` | Show "Waiting" badge, display resume time |
| `execution.resumed` | Resume progress bar |
| `execution.completed` | Show success badge, stop progress, show total duration |
| `execution.failed` | Show error badge, highlight failed node red, display error message |
| `execution.cancelled` | Show cancelled badge, gray out remaining nodes |
| `timeout` | Show "Connection lost — reconnecting?" |

**Canvas highlighting:** Map `node_id` from SSE events to canvas node `id`. Add a pulsing border or color overlay.

**Auto-reconnect:** If stream disconnects, reconnect with `Last-Event-ID` set to last received ID.

---

## 12. Execution Panel — History & Details

### Execution History List

**Call:**

```http
GET /api/v1/workspaces/{workspace}/workflows/{workflow}/executions
```

**Or for all workspace executions:**

```http
GET /api/v1/workspaces/{workspace}/executions?workflow_id={workflow_id}
```

**Filter controls:**

| UI Control | Query Param |
|------------|-------------|
| Status filter | `status=completed` |
| Mode filter | `mode=manual` |
| Date range | `from=2024-01-01T00:00:00Z&to=2024-01-31T23:59:59Z` |

**Display columns:**
- Status icon (colored dot)
- Mode badge (`manual`, `webhook`, `scheduled`, `polling`)
- Started time
- Duration
- Credits consumed
- Triggered by user name

### Execution Detail View (Click a row)

**Call 1 — Execution overview:**

```http
GET /api/v1/workspaces/{workspace}/executions/{execution}
```

**Call 2 — Node trace:**

```http
GET /api/v1/workspaces/{workspace}/executions/{execution}/nodes
```

**Render node trace on canvas:**
- Overlay execution status on each node (success = green, failed = red, skipped = gray)
- Show `duration_ms` per node
- Click a node → show `input_data` and `output_data` in a side panel

### Execution Logs (Debug Panel)

**Call:**

```http
GET /api/v1/workspaces/{workspace}/executions/{execution}/logs
```

**Filter:** `?level=error` to show only errors.

**Display:** Timestamp, level badge (debug/info/warning/error), message, expandable context.

### Retry a Failed Execution

**Call:**

```http
POST /api/v1/workspaces/{workspace}/executions/{execution}/retry
```

**After 201:** The new execution appears in the list. Can monitor via SSE.

### Replay a Completed Execution

**Call:**

```http
POST /api/v1/workspaces/{workspace}/executions/{execution}/replay
```

**After 201:** Same as retry but runs the exact same workflow snapshot and trigger data.

### Cancel Running Execution

**Call:**

```http
POST /api/v1/workspaces/{workspace}/executions/{execution}/cancel
```

**Only enabled if:** `status === "running" || status === "pending" || status === "waiting"`

### Execution Statistics

**Call:**

```http
GET /api/v1/workspaces/{workspace}/executions/stats?workflow_id={workflow_id}
```

**Display:** Total runs, success rate, avg duration, credits consumed chart.

---

## 13. Credentials Management Modal

### Open Credentials Modal

**Call:**

```http
GET /api/v1/workspaces/{workspace}/credentials
```

**Display:** Name, type badge, last used time, masked preview.

### Create New Credential

**Call 1 — Get available credential types (for form schema):**

```http
GET /api/v1/credential-types
```

Each type has a `fields_schema` — use it to render the form dynamically.

**Call 2 — Create credential:**

```http
POST /api/v1/workspaces/{workspace}/credentials
```

**Body:**

```json
{
  "name": "OpenAI Production",
  "type": "openai",
  "data": {
    "api_key": "sk-prod-...",
    "organization_id": "org-..."
  },
  "expires_at": "2025-01-01T00:00:00Z"
}
```

**Validation:** `name` must be unique within the workspace.

### Test Credential

**Call:**

```http
POST /api/v1/workspaces/{workspace}/credentials/{credential}/test
```

**Show result:** Green checkmark if `success: true`, red X with `message` if false.

### Update Credential

```http
PUT /api/v1/workspaces/{workspace}/credentials/{credential}
```

Same body as create. Partial updates are supported.

### Delete Credential

```http
DELETE /api/v1/workspaces/{workspace}/credentials/{credential}
```

---

## 14. Variables Panel

### List Variables

```http
GET /api/v1/workspaces/{workspace}/variables
```

**Display:** Key-value table. Mask values if `is_secret === true`.

### Create/Update/Delete

| Action | Endpoint | Method |
|--------|----------|--------|
| Create | `/workspaces/{workspace}/variables` | POST |
| Update | `/workspaces/{workspace}/variables/{variable}` | PUT |
| Delete | `/workspaces/{workspace}/variables/{variable}` | DELETE |

**Body (create/update):**

```json
{
  "key": "api_base_url",
  "value": "https://api.example.com",
  "type": "string",
  "is_secret": false,
  "description": "Base URL for API calls"
}
```

**Variable types:** `string`, `number`, `boolean`, `json`.

**Usage in expressions:** `{{ $vars.api_base_url }}`

---

## 15. Folders & Organization

### List Folders (Sidebar)

```http
GET /api/v1/workspaces/{workspace}/folders
```

**Display:** Folder tree with `workflows_count` per folder.

### Create Folder

```http
POST /api/v1/workspaces/{workspace}/folders
```

**Body:** `{ "name": "Marketing", "color": "#F472B6" }`

### Move Workflows to Folder

**Drag & drop or bulk move:**

```http
POST /api/v1/workspaces/{workspace}/folders/move-workflows
```

**Body:**

```json
{
  "folder_id": "uuid-or-null",
  "workflow_ids": ["uuid-1", "uuid-2"]
}
```

Set `folder_id` to `null` to move to root.

---

## 16. Tags Management

### List Tags

```http
GET /api/v1/workspaces/{workspace}/tags
```

**Display:** Colored badges with `workflows_count`.

### Attach Tags to Workflow

```http
POST /api/v1/workspaces/{workspace}/tags/{tag}/workflows
```

**Body:** `{ "workflow_ids": ["uuid"] }`

### Detach Tags

```http
DELETE /api/v1/workspaces/{workspace}/tags/{tag}/workflows
```

Same body.

---

## 17. AI Workflow Builder

### Natural Language Input

**Call:**

```http
POST /api/v1/workspaces/{workspace}/workflows/build
```

**Body:**

```json
{
  "description": "When a customer signs up via webhook, validate their email with ZeroBounce, add them to Mailchimp, and send a Slack notification to the team"
}
```

**Response (201):** Full workflow with `current_version` pre-populated.

**After response:**
1. Redirect to editor
2. Load the generated graph onto canvas
3. Show "AI-generated workflow — review before publishing" banner
4. User can edit, then save as a new version and publish

---

## 18. Workflow Properties / Settings

### Update Workflow Metadata

```http
PUT /api/v1/workspaces/{workspace}/workflows/{workflow}
```

**Body:**

```json
{
  "name": "Updated Name",
  "description": "New description",
  "icon": "mail",
  "color": "#10B981"
}
```

**This does NOT update the graph.** Use Versions API for graph changes.

### Settings Stored in Version

The following are stored in the version `settings` JSONB:

```json
{
  "timeout": 300,
  "max_retries": 3,
  "error_workflow_id": "uuid-or-null"
}
```

**UI:** Render as a settings panel in the editor. Include these fields when creating a version.

---

## 19. Sticky Notes on Canvas

### Load Sticky Notes (On Canvas Open)

```http
GET /api/v1/workspaces/{workspace}/workflows/{workflow}/sticky-notes
```

**Render on canvas:** As non-interactive annotation layers or draggable note widgets.

### Create / Update / Delete

| Action | Endpoint | Body |
|--------|----------|------|
| Create | `POST /workflows/{workflow}/sticky-notes` | `{ "content": "TODO: Add retry logic", "position": { "x": 200, "y": 300 }, "color": "#FEF3C7" }` |
| Update | `PUT /workflows/{workflow}/sticky-notes/{id}` | Same |
| Delete | `DELETE /workflows/{workflow}/sticky-notes/{id}` | — |

---

## 20. Pinned Test Data

### Load Pinned Data (On Node Select or Test Panel Open)

```http
GET /api/v1/workspaces/{workspace}/workflows/{workflow}/pinned-data
```

### Create Pinned Data (Save Mock Input for a Node)

```http
POST /api/v1/workspaces/{workspace}/workflows/{workflow}/pinned-data
```

**Body:**

```json
{
  "node_id": "http_1",
  "data": {
    "body": { "id": 123, "name": "Test" },
    "headers": { "Content-Type": "application/json" }
  },
  "is_input": true,
  "is_active": true
}
```

**Use case:** Let users save mock input data for a node so they can test individual nodes without running the full workflow.

### Toggle Pinned Data Active/Inactive

```http
POST /api/v1/workspaces/{workspace}/workflows/{workflow}/pinned-data/{pinnedData}/toggle
```

---

## Quick Reference: All Endpoints by Screen

| Screen | Endpoints Used |
|--------|---------------|
| **Dashboard** | `GET /workflows`, `POST /workflows`, `POST /workflows/{id}/duplicate`, `POST /workflows/{id}/activate`, `DELETE /workflows/{id}` |
| **Canvas Editor** | `GET /workflows/{id}`, `GET /workflows/{id}/versions/{version}`, `POST /workflows/{id}/versions`, `POST /versions/{version}/publish`, `GET /nodes`, `GET /nodes/{type}` |
| **Version History** | `GET /workflows/{id}/versions`, `GET /versions/{version}`, `GET /versions/diff`, `POST /versions/{version}/rollback` |
| **Node Config** | `GET /nodes/{type}`, `GET /credentials?type={type}`, `GET /variables` |
| **Triggers Panel** | `POST /workflows/{id}/webhook`, `GET /webhooks`, `PUT /webhooks/{id}`, `POST /workflows/{id}/polling-trigger`, `GET /polling-triggers` |
| **Execution Panel** | `POST /workflows/{id}/execute`, `GET /executions/{id}/stream` (SSE), `GET /executions`, `GET /executions/{id}`, `GET /executions/{id}/nodes`, `GET /executions/{id}/logs`, `POST /executions/{id}/retry`, `POST /executions/{id}/replay`, `POST /executions/{id}/cancel`, `GET /executions/stats` |
| **Credentials Modal** | `GET /credentials`, `GET /credential-types`, `POST /credentials`, `POST /credentials/{id}/test`, `PUT /credentials/{id}`, `DELETE /credentials/{id}` |
| **Variables Panel** | `GET /variables`, `POST /variables`, `PUT /variables/{id}`, `DELETE /variables/{id}` |
| **Folders** | `GET /folders`, `POST /folders`, `POST /folders/move-workflows` |
| **Tags** | `GET /tags`, `POST /tags`, `POST /tags/{id}/workflows`, `DELETE /tags/{id}/workflows` |
| **AI Builder** | `POST /workflows/build` |
| **Sticky Notes** | `GET /workflows/{id}/sticky-notes`, `POST /sticky-notes`, `PUT /sticky-notes/{id}`, `DELETE /sticky-notes/{id}` |
| **Pinned Data** | `GET /workflows/{id}/pinned-data`, `POST /pinned-data`, `POST /pinned-data/{id}/toggle`, `DELETE /pinned-data/{id}` |

---

## Permission-Gated UI Elements

Hide or disable buttons based on user's workspace role:

| Role | Hidden/Disabled Elements |
|------|--------------------------|
| **viewer** | Create workflow, Save, Publish, Execute, Delete, Create credential, Create webhook, Create polling trigger, AI Builder |
| **editor** | Can do everything except transfer ownership, manage billing, delete workspace |
| **admin** | Full access except transfer ownership (unless owner) |
| **owner** | No restrictions |

**Check permissions from:** `GET /workspaces` → each workspace has a `role` field. The backend also returns 403 if user attempts an unauthorized action.
