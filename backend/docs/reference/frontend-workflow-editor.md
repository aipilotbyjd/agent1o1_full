# Frontend Workflow Editor — Backend Integration Guide

**Complete backend API reference for building a visual workflow editor UI**

This document contains everything a frontend team needs to integrate with the LinkFlow backend to build a canvas-based workflow editor: node palette, drag-and-drop canvas, version management, execution monitoring, and real-time updates.

> **No frontend code** — only APIs, data structures, request/response schemas, and integration patterns.

---

## Table of Contents

1. [Authentication & Base URL](#1-authentication--base-url)
2. [Response Format Standard](#2-response-format-standard)
3. [Workspace Context](#3-workspace-context)
4. [Node Catalog (Palette)](#4-node-catalog-palette)
5. [Workflow CRUD](#5-workflow-crud)
6. [Workflow Versions (Canvas Save/Publish)](#6-workflow-versions-canvas-savepublish)
7. [Execution Lifecycle](#7-execution-lifecycle)
8. [Real-Time Execution Monitoring (SSE)](#8-real-time-execution-monitoring-sse)
9. [Webhook Triggers](#9-webhook-triggers)
10. [Polling Triggers](#10-polling-triggers)
11. [Credentials Management](#11-credentials-management)
12. [Variables](#12-variables)
13. [Folders & Organization](#13-folders--organization)
14. [Tags](#14-tags)
15. [Sticky Notes & Pinned Data](#15-sticky-notes--pinned-data)
16. [AI Workflow Builder](#16-ai-workflow-builder)
17. [Expression System & Data Mapping](#17-expression-system--data-mapping)
18. [Permissions Matrix](#18-permissions-matrix)
19. [Rate Limits](#19-rate-limits)
20. [Error Handling](#20-error-handling)
21. [Complete Data Structure Reference](#21-complete-data-structure-reference)

---

## 1. Authentication & Base URL

### Base URL

```
Production:  https://your-domain.com/api/v1
Local:       http://localhost:8000/api/v1
```

### Authentication

All workspace-scoped endpoints require a Bearer token:

```http
Authorization: Bearer {access_token}
```

Obtain tokens via:

```http
POST /api/v1/auth/register
POST /api/v1/auth/login
POST /api/v1/auth/refresh
```

**Token response:**

```json
{
  "data": {
    "user": { "id": "uuid", "name": "...", "email": "..." },
    "access_token": "eyJ0eXAiOi...",
    "refresh_token": "def5020...",
    "token_type": "Bearer",
    "expires_in": 3600
  }
}
```

---

## 2. Response Format Standard

### Success (200/201)

```json
{
  "message": "Human-readable success message",
  "data": { ... },
  "meta": {          // present only for paginated lists
    "total": 150,
    "current_page": 1,
    "per_page": 20,
    "last_page": 8
  },
  "links": {         // present only for paginated lists
    "first": "...",
    "last": "...",
    "prev": null,
    "next": "..."
  }
}
```

### Error (4xx/5xx)

```json
{
  "message": "Error summary",
  "errors": {        // validation errors only
    "field_name": ["The field_name is required."]
  }
}
```

---

## 3. Workspace Context

Every workflow request is scoped to a workspace. The workspace ID is the first path parameter after `/workspaces/`.

### List Workspaces

```http
GET /api/v1/workspaces
```

**Response:**

```json
{
  "data": [
    {
      "id": "uuid",
      "name": "My Workspace",
      "slug": "my-workspace",
      "role": "owner",          // owner | admin | editor | viewer
      "created_at": "2024-01-01T00:00:00Z"
    }
  ]
}
```

**Role capabilities:**

| Role | Workflows | Executions | Credentials | Members | Billing |
|------|-----------|------------|-------------|---------|---------|
| `owner` | CRUD + activate | all | CRUD | transfer ownership | full |
| `admin` | CRUD + activate | all | CRUD | invite/manage | full |
| `editor` | CRUD | view + trigger | CRUD | — | view |
| `viewer` | view only | view only | view only | — | view |

---

## 4. Node Catalog (Palette)

The frontend canvas needs a palette of draggable nodes. Fetch the catalog from the backend.

### List All Nodes

```http
GET /api/v1/nodes
```

**Query parameters:**

| Parameter | Type | Description |
|-----------|------|-------------|
| `search` | string | Filter by name |
| `category_id` | uuid | Filter by category |
| `node_kind` | string | `trigger`, `action`, `flow`, `utility`, `ai` |
| `is_premium` | boolean | Filter premium nodes |
| `sort_by` | string | `name`, `node_kind`, `created_at` |
| `sort_direction` | string | `asc` or `desc` |
| `per_page` | integer | Max 100 |

**Response:**

```json
{
  "data": [
    {
      "id": "uuid",
      "type": "http_request",
      "name": "HTTP Request",
      "description": "Makes HTTP requests to any API",
      "icon": "globe",
      "color": "#3B82F6",
      "node_kind": "action",
      "config_schema": {
        "type": "object",
        "properties": {
          "url": { "type": "string", "format": "uri" },
          "method": { "type": "string", "enum": ["GET", "POST", "PUT", "PATCH", "DELETE"] },
          "headers": { "type": "object" },
          "body": { "type": "object" },
          "timeout": { "type": "integer", "default": 30 }
        },
        "required": ["url", "method"]
      },
      "input_schema": { "type": "object" },
      "output_schema": {
        "type": "object",
        "properties": {
          "status_code": { "type": "integer" },
          "body": {},
          "headers": { "type": "object" }
        }
      },
      "credential_type": null,        // or "openai", "slack", etc.
      "cost_hint_usd": 0.0001,
      "latency_hint_ms": 500,
      "is_active": true,
      "is_premium": false,
      "docs_url": "https://docs.linkflow.dev/nodes/http-request",
      "category": {
        "id": "uuid",
        "name": "HTTP & APIs",
        "slug": "http-apis",
        "icon": "globe",
        "color": "#3B82F6"
      }
    }
  ]
}
```

### List Node Categories

```http
GET /api/v1/node-categories
```

**Response:**

```json
{
  "data": [
    { "id": "uuid", "name": "Triggers", "slug": "triggers", "icon": "zap", "color": "#F59E0B" },
    { "id": "uuid", "name": "AI", "slug": "ai", "icon": "brain", "color": "#10A37F" },
    { "id": "uuid", "name": "Communication", "slug": "communication", "icon": "mail", "color": "#10B981" },
    { "id": "uuid", "name": "Data", "slug": "data", "icon": "database", "color": "#6366F1" },
    { "id": "uuid", "name": "Flow Control", "slug": "flow-control", "icon": "git-branch", "color": "#F59E0B" },
    { "id": "uuid", "name": "HTTP & APIs", "slug": "http-apis", "icon": "globe", "color": "#3B82F6" },
    { "id": "uuid", "name": "Storage", "slug": "storage", "icon": "hard-drive", "color": "#8B5CF6" },
    { "id": "uuid", "name": "Utility", "slug": "utility", "icon": "tool", "color": "#6B7280" }
  ]
}
```

### Get Single Node Details

```http
GET /api/v1/nodes/{node_id_or_type}
```

Returns the full node definition including `config_schema`, `input_schema`, and `output_schema` needed to render the node's configuration form.

---

## 5. Workflow CRUD

### List Workflows

```http
GET /api/v1/workspaces/{workspace}/workflows
```

**Query parameters:**

| Parameter | Type | Description |
|-----------|------|-------------|
| `search` | string | Search by name or description |
| `is_active` | boolean | Filter active/inactive |
| `tag` | string | Filter by tag name |
| `sort_by` | string | `name`, `created_at`, `updated_at`, `last_executed_at`, `execution_count` |
| `sort_direction` | string | `asc` or `desc` |
| `per_page` | integer | Default 20, max 100 |

**Response:**

```json
{
  "data": [
    {
      "id": "uuid",
      "folder_id": "uuid-or-null",
      "folder": { "id": "uuid", "name": "Marketing" },
      "name": "Webhook to Email",
      "description": "Receives a webhook and sends an email",
      "icon": "mail",
      "color": "#3B82F6",
      "is_active": true,
      "is_locked": false,
      "current_version_id": "uuid",
      "current_version": {            // when loaded
        "id": "uuid",
        "version_number": 3,
        "trigger_type": "webhook",
        "nodes": [...],
        "edges": [...]
      },
      "execution_count": 152,
      "last_executed_at": "2024-01-15T10:30:00Z",
      "success_rate": 98.50,
      "creator": { "id": "uuid", "name": "John Doe" },
      "created_at": "2024-01-01T00:00:00Z",
      "updated_at": "2024-01-15T10:30:00Z"
    }
  ]
}
```

### Create Workflow (Shell)

```http
POST /api/v1/workspaces/{workspace}/workflows
```

**Body:**

```json
{
  "name": "New Workflow",
  "description": "Optional description",
  "icon": "zap",
  "color": "#6366f1"
}
```

**Validation rules:**

| Field | Rules |
|-------|-------|
| `name` | required, string, max:255 |
| `description` | nullable, string, max:1000 |
| `icon` | nullable, string, max:50 |
| `color` | nullable, string, regex: `^#[0-9A-Fa-f]{6}$` |

**Response (201):** Returns the created `WorkflowResource`.

### Update Workflow Metadata

```http
PUT /api/v1/workspaces/{workspace}/workflows/{workflow}
```

Same body fields as create. Only updates the workflow shell (name, description, icon, color). **Does NOT update the graph definition** — use the Versions API for that.

### Delete Workflow

```http
DELETE /api/v1/workspaces/{workspace}/workflows/{workflow}
```

Requires `workflow.delete` permission. Soft-deletes the workflow and cascades to versions, executions, webhooks, etc.

### Activate / Deactivate Workflow

```http
POST /api/v1/workspaces/{workspace}/workflows/{workflow}/activate
POST /api/v1/workspaces/{workspace}/workflows/{workflow}/deactivate
```

**Activation side effects:**
- Sets `is_active = true`
- Schedules async external webhook registration (GitHub, Stripe, etc.)
- `webhook_status` becomes `pending`, then `active` or `failed`

**Deactivation side effects:**
- Sets `is_active = false`
- Stops accepting new triggers
- Schedules async webhook deregistration

### Duplicate Workflow

```http
POST /api/v1/workspaces/{workspace}/workflows/{workflow}/duplicate
```

Creates a deep copy of the workflow including its current version graph. Response is the new `WorkflowResource` (201).

---

## 6. Workflow Versions (Canvas Save/Publish)

The **version system** is how the canvas persists graph state. Every save creates a new version. Publishing a version makes it the "live" version that the engine executes.

### List Versions

```http
GET /api/v1/workspaces/{workspace}/workflows/{workflow}/versions
```

**Response:**

```json
{
  "data": [
    {
      "id": "uuid",
      "workflow_id": "uuid",
      "version_number": 3,
      "name": "Added email notification",
      "description": "Added email notification after webhook",
      "trigger_type": "webhook",
      "trigger_config": { "http_method": "POST" },
      "nodes": [
        {
          "id": "trigger_1",
          "type": "trigger",
          "position": { "x": 100, "y": 100 },
          "data": { "label": "Start" }
        },
        {
          "id": "http_1",
          "type": "http_request",
          "position": { "x": 400, "y": 100 },
          "data": { "url": "https://api.example.com", "method": "GET" }
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
      "settings": { "timeout": 300, "max_retries": 3 },
      "change_summary": "Added email notification",
      "is_published": true,
      "published_at": "2024-01-15T10:00:00Z",
      "creator": { "id": "uuid", "name": "John" },
      "created_at": "2024-01-15T10:00:00Z"
    }
  ]
}
```

### Create a Version (Save Canvas)

```http
POST /api/v1/workspaces/{workspace}/workflows/{workflow}/versions
```

**Body — this is the full canvas state:**

```json
{
  "name": "Added email notification",
  "description": "Optional version description",
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
      "data": { "url": "https://api.example.com", "method": "GET" }
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
  "settings": { "timeout": 300, "max_retries": 3, "error_workflow_id": null },
  "change_summary": "Added email notification"
}
```

**Validation rules:**

| Field | Rules |
|-------|-------|
| `name` | nullable, string, max:255 |
| `description` | nullable, string, max:1000 |
| `trigger_type` | nullable, string, max:50 |
| `trigger_config` | nullable, array |
| `nodes` | **required**, array, min:1 |
| `nodes.*.id` | required, string |
| `nodes.*.type` | required, string |
| `nodes.*.position` | nullable, array |
| `nodes.*.data` | nullable, array |
| `edges` | **present**, array (can be empty) |
| `edges.*.source` | required, string |
| `edges.*.target` | required, string |
| `edges.*.sourceHandle` | nullable, string |
| `edges.*.targetHandle` | nullable, string |
| `viewport` | nullable, array |
| `settings` | nullable, array |
| `change_summary` | nullable, string, max:255 |

**Important:** The first node should typically be a `trigger` type node.

### Publish a Version (Go Live)

```http
POST /api/v1/workspaces/{workspace}/workflows/{workflow}/versions/{version}/publish
```

Sets `is_published = true` and updates the workflow's `current_version_id`. Only published versions are executed by the engine.

**Response:** Returns the updated `WorkflowVersionResource`.

### Rollback to a Previous Version

```http
POST /api/v1/workspaces/{workspace}/workflows/{workflow}/versions/{version}/rollback
```

Creates a **new version** copied from the old one, publishes it, and returns the new version (201).

### Diff Two Versions

```http
GET /api/v1/workspaces/{workspace}/workflows/{workflow}/versions/diff?from={version_id_a}&to={version_id_b}
```

**Response:**

```json
{
  "message": "Version diff computed successfully.",
  "data": {
    "added_nodes": [...],
    "removed_nodes": [...],
    "modified_nodes": [
      {
        "id": "http_1",
        "changes": [
          { "field": "data.url", "from": "http://old.com", "to": "https://new.com" }
        ]
      }
    ],
    "added_edges": [...],
    "removed_edges": [...]
  }
}
```

---

## 7. Execution Lifecycle

### Trigger a Manual Execution

```http
POST /api/v1/workspaces/{workspace}/workflows/{workflow}/execute
```

**Rate limit:** `throttle:execution-trigger` (see Rate Limits section)

**Body:**

```json
{
  "trigger_data": {
    "user_id": 123,
    "email": "test@example.com"
  }
}
```

| Field | Rules |
|-------|-------|
| `trigger_data` | nullable, array |

**Requirements to execute:**
- Workflow `is_active` must be `true`
- Workflow must have a published version (`current_version_id` set)

**Response (201):**

```json
{
  "message": "Execution triggered successfully.",
  "data": {
    "id": "uuid",
    "workflow_id": "uuid",
    "workspace_id": "uuid",
    "status": "pending",
    "mode": "manual",
    "started_at": null,
    "finished_at": null,
    "duration_ms": null,
    "error": null,
    "attempt": 1,
    "max_attempts": 3,
    "credits_consumed": 0,
    "parent_execution_id": null,
    "is_deterministic_replay": false,
    "workflow": { "id": "uuid", "name": "..." },
    "triggered_by": { "id": "uuid", "name": "John" },
    "nodes": [],
    "nodes_count": 0,
    "created_at": "2024-01-15T10:00:00Z",
    "updated_at": "2024-01-15T10:00:00Z"
  }
}
```

### List All Executions (Workspace-level)

```http
GET /api/v1/workspaces/{workspace}/executions
```

**Query parameters:**

| Parameter | Type | Description |
|-----------|------|-------------|
| `status` | string | `pending`, `running`, `completed`, `failed`, `cancelled`, `waiting` |
| `workflow_id` | uuid | Filter by workflow |
| `mode` | string | `manual`, `webhook`, `scheduled`, `polling`, `sub_workflow` |
| `from` | ISO8601 | Start date |
| `to` | ISO8601 | End date |
| `per_page` | integer | Max 100 |

### List Workflow Executions

```http
GET /api/v1/workspaces/{workspace}/workflows/{workflow}/executions
```

Same query parameters as above.

### Get Execution Detail

```http
GET /api/v1/workspaces/{workspace}/executions/{execution}
```

**Response (full detail with nodes):**

```json
{
  "data": {
    "id": "uuid",
    "workflow_id": "uuid",
    "workspace_id": "uuid",
    "status": "completed",
    "mode": "manual",
    "started_at": "2024-01-15T10:00:00Z",
    "finished_at": "2024-01-15T10:00:12Z",
    "duration_ms": 12000,
    "error": null,
    "attempt": 1,
    "max_attempts": 3,
    "credits_consumed": 5,
    "parent_execution_id": null,
    "is_deterministic_replay": false,
    "workflow": { "id": "uuid", "name": "..." },
    "triggered_by": { "id": "uuid", "name": "..." },
    "nodes": [
      {
        "id": "uuid",
        "node_id": "trigger_1",
        "node_type": "trigger",
        "node_name": "Start",
        "status": "completed",
        "started_at": "2024-01-15T10:00:00Z",
        "finished_at": "2024-01-15T10:00:01Z",
        "duration_ms": 1000,
        "input_data": { "trigger_data": { "email": "test@example.com" } },
        "output_data": { "trigger_type": "manual", "data": { "email": "test@example.com" }, "timestamp": "2024-01-15T10:00:00Z" },
        "error": null,
        "sequence": 1
      },
      {
        "id": "uuid",
        "node_id": "http_1",
        "node_type": "http_request",
        "node_name": "HTTP Request",
        "status": "completed",
        "started_at": "2024-01-15T10:00:01Z",
        "finished_at": "2024-01-15T10:00:12Z",
        "duration_ms": 11000,
        "input_data": { "url": "https://api.example.com", "method": "GET" },
        "output_data": { "status_code": 200, "body": {...}, "headers": {...} },
        "error": null,
        "sequence": 2
      }
    ],
    "nodes_count": 2,
    "created_at": "2024-01-15T10:00:00Z",
    "updated_at": "2024-01-15T10:00:12Z"
  }
}
```

### Get Execution Nodes Only

```http
GET /api/v1/workspaces/{workspace}/executions/{execution}/nodes
```

Returns `ExecutionNodeResource[]` — useful for rendering the execution trace on the workflow canvas.

### Get Execution Logs

```http
GET /api/v1/workspaces/{workspace}/executions/{execution}/logs
```

**Query parameters:**

| Parameter | Type | Description |
|-----------|------|-------------|
| `level` | string | `debug`, `info`, `warning`, `error` |
| `execution_node_id` | integer | Filter by node |
| `per_page` | integer | Max 100, default 50 |

**Response:**

```json
{
  "data": [
    {
      "id": "uuid",
      "execution_id": "uuid",
      "execution_node_id": 42,
      "level": "info",
      "message": "HTTP request completed in 1100ms",
      "context": { "status_code": 200, "url": "..." },
      "logged_at": "2024-01-15T10:00:12Z"
    }
  ]
}
```

**Note:** Secret variable values are automatically masked in `input_data`, `output_data`, `message`, and `context` when returned via API.

### Retry a Failed Execution

```http
POST /api/v1/workspaces/{workspace}/executions/{execution}/retry
```

Creates a child execution with `mode = retry` and `parent_execution_id` set. Returns new `ExecutionResource` (201).

### Replay a Completed Execution

```http
POST /api/v1/workspaces/{workspace}/executions/{execution}/replay
```

Re-runs the exact same workflow snapshot and trigger data. Creates a new execution with `is_deterministic_replay = true`. Returns new `ExecutionResource` (201).

**Requirements:** The original execution must have a replay pack (captured automatically on every trigger).

### Cancel an Active Execution

```http
POST /api/v1/workspaces/{workspace}/executions/{execution}/cancel
```

Only works if execution status is `pending`, `running`, or `waiting`.

### Bulk Delete Executions

```http
DELETE /api/v1/workspaces/{workspace}/executions/bulk
```

**Body:**

```json
{
  "ids": ["uuid-1", "uuid-2", "uuid-3"]
}
```

Only terminal executions (`completed`, `failed`, `cancelled`) can be bulk-deleted.

### Execution Statistics

```http
GET /api/v1/workspaces/{workspace}/executions/stats
```

**Query parameters:**

| Parameter | Type | Description |
|-----------|------|-------------|
| `workflow_id` | uuid | Filter by workflow |

**Response:**

```json
{
  "message": "Execution stats retrieved successfully.",
  "data": {
    "total": 152,
    "completed": 145,
    "failed": 5,
    "cancelled": 2,
    "running": 0,
    "waiting": 0,
    "avg_duration_ms": 3200,
    "total_credits_consumed": 760,
    "success_rate": 95.39
  }
}
```

### Compare Two Executions

```http
GET /api/v1/workspaces/{workspace}/executions/compare?execution_a={id}&execution_b={id}
```

**Response:**

```json
{
  "data": {
    "execution_a": { ... },
    "execution_b": { ... },
    "node_comparison": [
      {
        "node_id": "http_1",
        "node_name": "HTTP Request",
        "execution_a": {
          "status": "completed",
          "duration_ms": 1100,
          "output_data": { "status_code": 200 },
          "error": null
        },
        "execution_b": {
          "status": "failed",
          "duration_ms": 30000,
          "output_data": null,
          "error": { "message": "Connection timeout" }
        }
      }
    ]
  }
}
```

---

## 8. Real-Time Execution Monitoring (SSE)

The engine publishes execution events to Redis Streams + Pub/Sub. The frontend connects via Server-Sent Events (SSE) for live execution progress.

### Stream a Single Execution

```http
GET /api/v1/workspaces/{workspace}/executions/{execution}/stream
```

**Headers:**

```http
Accept: text/event-stream
Last-Event-ID: 0-0        // For resuming after disconnect
```

**SSE event types:**

| Event | Description | Data Payload |
|-------|-------------|--------------|
| `execution.started` | Execution began | `{ "execution_id": "...", "started_at": "..." }` |
| `execution.node_started` | A node began running | `{ "execution_id": "...", "node_id": "...", "node_type": "...", "sequence": 1 }` |
| `execution.node_completed` | A node finished | `{ "execution_id": "...", "node_id": "...", "status": "completed", "duration_ms": 1000 }` |
| `execution.suspended` | Execution paused (delay/wait) | `{ "execution_id": "...", "reason": "delay", "resume_at": "..." }` |
| `execution.resumed` | Execution resumed | `{ "execution_id": "...", "resumed_at": "..." }` |
| `execution.completed` | Execution finished successfully | `{ "execution_id": "...", "duration_ms": 12000, "credits_consumed": 5 }` |
| `execution.failed` | Execution failed | `{ "execution_id": "...", "error": { "message": "...", "node_id": "..." } }` |
| `execution.cancelled` | Execution was cancelled | `{ "execution_id": "...", "cancelled_at": "..." }` |
| `timeout` | Stream reached max duration (5 min) | `{ "message": "Stream timeout, please reconnect." }` |

**Example SSE stream:**

```
id: 1705312800000-0
event: execution.started
data: {"execution_id":"uuid","started_at":"2024-01-15T10:00:00Z"}

id: 1705312801000-0
event: execution.node_started
data: {"execution_id":"uuid","node_id":"trigger_1","node_type":"trigger","sequence":1}

id: 1705312802000-0
event: execution.node_completed
data: {"execution_id":"uuid","node_id":"trigger_1","status":"completed","duration_ms":1000}

id: 1705312803000-0
event: execution.completed
data: {"execution_id":"uuid","duration_ms":12000,"credits_consumed":5}
```

**Connection details:**
- Max duration: **300 seconds** (reconnect after timeout)
- Heartbeat: Every **15 seconds** (`: heartbeat\n\n`)
- The stream auto-closes when `execution.completed`, `execution.failed`, or `execution.cancelled` is received.

### Stream All Workspace Executions (Dashboard View)

```http
GET /api/v1/workspaces/{workspace}/executions/stream-all
```

Same SSE format, but aggregates events from all active executions in the workspace. Useful for a global execution monitor dashboard.

---

## 9. Webhook Triggers

Each workflow can have one **manual webhook** (public URL that triggers the workflow) and multiple **external webhooks** (auto-registered with providers like GitHub, Stripe).

### Create a Manual Webhook

```http
POST /api/v1/workspaces/{workspace}/workflows/{workflow}/webhook
```

**Body:**

```json
{
  "path": "custom-path",            // optional, appended to webhook URL
  "methods": ["POST", "PUT"],       // default: ["POST"]
  "auth_type": "none",              // none | header | basic | bearer
  "auth_config": { "header_name": "X-Webhook-Secret", "header_value": "secret123" },
  "rate_limit": 100,                // requests per minute
  "response_mode": "immediate",       // immediate | wait
  "response_status": 200,
  "response_body": { "message": "Webhook received" }
}
```

**Validation:**

| Field | Rules |
|-------|-------|
| `path` | nullable, string, max:100 |
| `methods` | nullable, array, min:1 |
| `methods.*` | string, in: `GET`, `POST`, `PUT`, `PATCH`, `DELETE` |
| `auth_type` | nullable, string, in: `none`, `header`, `basic`, `bearer` |
| `auth_config` | nullable, array |
| `rate_limit` | nullable, integer, min:1, max:10000 |
| `response_mode` | nullable, string, in: `immediate`, `wait` |
| `response_status` | nullable, integer, min:100, max:599 |
| `response_body` | nullable, array |

**Response (201):**

```json
{
  "data": {
    "id": "uuid",
    "workflow_id": "uuid",
    "uuid": "webhook-unique-id",
    "node_id": "trigger_1",
    "url": "https://your-app.com/api/v1/webhook/webhook-unique-id",
    "provider": null,
    "is_externally_managed": false,
    "path": "custom-path",
    "methods": ["POST", "PUT"],
    "is_active": true,
    "auth_type": "none",
    "rate_limit": 100,
    "response_mode": "immediate",
    "response_status": 200,
    "response_body": { "message": "Webhook received" },
    "call_count": 0,
    "last_called_at": null,
    "workflow": { ... },
    "created_at": "2024-01-15T10:00:00Z"
  }
}
```

### List Webhooks

```http
GET /api/v1/workspaces/{workspace}/webhooks
```

**Query parameters:**

| Parameter | Type | Description |
|-----------|------|-------------|
| `workflow_id` | uuid | Filter by workflow |
| `is_active` | boolean | Filter active status |

### Update Webhook

```http
PUT /api/v1/workspaces/{workspace}/webhooks/{webhook}
```

Same body fields as create.

### Delete Webhook

```http
DELETE /api/v1/workspaces/{workspace}/webhooks/{webhook}
```

### Receiving Webhooks (Public — No Auth)

```http
GET|POST|PUT|PATCH|DELETE /api/v1/webhook/{uuid}
```

The UUID is the secret. Supports `X-Accel-Buffering: no` for streaming responses when `response_mode = wait`.

**Rate limit:** `throttle:webhook-receive`

---

## 10. Polling Triggers

Polling triggers periodically call an API endpoint and trigger the workflow when new items are detected.

### Create a Polling Trigger

```http
POST /api/v1/workspaces/{workspace}/workflows/{workflow}/polling-trigger
```

**Body:**

```json
{
  "endpoint_url": "https://api.example.com/new-orders",
  "http_method": "GET",
  "headers": { "Authorization": "Bearer token123" },
  "query_params": { "limit": 50 },
  "dedup_key": "id",
  "interval_seconds": 300
}
```

**Validation:**

| Field | Rules |
|-------|-------|
| `endpoint_url` | required, string, URL format |
| `http_method` | required, string, in: `GET`, `POST`, `PUT`, `PATCH`, `DELETE` |
| `headers` | nullable, array |
| `query_params` | nullable, array |
| `body` | nullable, array |
| `dedup_key` | required, string |
| `interval_seconds` | required, integer, min:60 |
| `auth_config` | nullable, array |

**Response (201):**

```json
{
  "data": {
    "id": "uuid",
    "workflow_id": "uuid",
    "endpoint_url": "https://api.example.com/new-orders",
    "http_method": "GET",
    "headers": { "Authorization": "Bearer token123" },
    "query_params": { "limit": 50 },
    "dedup_key": "id",
    "interval_seconds": 300,
    "is_active": true,
    "last_polled_at": null,
    "next_poll_at": "2024-01-15T10:05:00Z",
    "poll_count": 0,
    "trigger_count": 0,
    "last_error": null,
    "workflow": { ... },
    "created_at": "2024-01-15T10:00:00Z"
  }
}
```

**Constraints:** Each workflow can have at most **one** polling trigger.

### List Polling Triggers

```http
GET /api/v1/workspaces/{workspace}/polling-triggers
```

### Update Polling Trigger

```http
PUT /api/v1/workspaces/{workspace}/polling-triggers/{pollingTrigger}
```

### Delete Polling Trigger

```http
DELETE /api/v1/workspaces/{workspace}/polling-triggers/{pollingTrigger}
```

---

## 11. Credentials Management

Credentials store encrypted API keys, OAuth tokens, and connection strings. They are linked to workflows via `node_id` pivot.

### List Credential Types (Global Catalog)

```http
GET /api/v1/credential-types
```

Returns all available credential types with their `fields_schema` (forms to render).

### List Workspace Credentials

```http
GET /api/v1/workspaces/{workspace}/credentials
```

**Query parameters:**

| Parameter | Type | Description |
|-----------|------|-------------|
| `search` | string | Search by name |
| `type` | string | Filter by type (e.g., `openai`, `slack`) |
| `sort_by` | string | `name`, `type`, `created_at`, `last_used_at` |
| `sort_direction` | string | `asc` or `desc` |

**Response:**

```json
{
  "data": [
    {
      "id": "uuid",
      "name": "OpenAI Production",
      "type": "openai",
      "data": {
        "api_key": "••••••••••sk-last4",
        "organization_id": "••••••••"
      },
      "last_used_at": "2024-01-15T10:00:00Z",
      "expires_at": null,
      "creator": { "id": "uuid", "name": "..." },
      "created_at": "2024-01-15T10:00:00Z"
    }
  ]
}
```

**Note:** Sensitive fields are masked (e.g., `••••••••••sk-last4`). The real values are never returned over the API.

### Create Credential

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

**Validation:**

| Field | Rules |
|-------|-------|
| `name` | required, string, max:255, unique per workspace |
| `type` | required, string, max:100 |
| `data` | required, array |
| `expires_at` | nullable, date, after:now |

### Update Credential

```http
PUT /api/v1/workspaces/{workspace}/credentials/{credential}
```

Same body as create. Partial updates supported.

### Delete Credential

```http
DELETE /api/v1/workspaces/{workspace}/credentials/{credential}
```

### Test Credential

```http
POST /api/v1/workspaces/{workspace}/credentials/{credential}/test
```

Returns `{ "success": true/false, "message": "..." }` with HTTP 200 or 422.

---

## 12. Variables

Workspace-scoped key-value variables that can be referenced in node configs via expressions.

### List Variables

```http
GET /api/v1/workspaces/{workspace}/variables
```

**Response:**

```json
{
  "data": [
    {
      "id": "uuid",
      "key": "api_base_url",
      "value": "https://api.example.com",
      "type": "string",
      "is_secret": false,
      "description": "Base URL for all API calls"
    }
  ]
}
```

### CRUD Endpoints

| Action | Endpoint | Method |
|--------|----------|--------|
| Create | `/workspaces/{workspace}/variables` | POST |
| Show | `/workspaces/{workspace}/variables/{variable}` | GET |
| Update | `/workspaces/{workspace}/variables/{variable}` | PUT |
| Delete | `/workspaces/{workspace}/variables/{variable}` | DELETE |

**Variable types:** `string`, `number`, `boolean`, `json`.

**Secret variables** (`is_secret = true`) have their values masked in execution logs and API responses.

---

## 13. Folders & Organization

### List Folders

```http
GET /api/v1/workspaces/{workspace}/folders
```

**Response:**

```json
{
  "data": [
    {
      "id": "uuid",
      "name": "Marketing",
      "color": "#F472B6",
      "workflows_count": 12,
      "created_at": "2024-01-01T00:00:00Z"
    }
  ]
}
```

### Move Workflows Between Folders

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

Set `folder_id` to `null` to move workflows to the root.

---

## 14. Tags

### List Tags

```http
GET /api/v1/workspaces/{workspace}/tags
```

**Response:**

```json
{
  "data": [
    {
      "id": "uuid",
      "name": "Production",
      "color": "#10B981",
      "workflows_count": 8
    }
  ]
}
```

### Attach/Detach Workflows to Tags

```http
POST   /api/v1/workspaces/{workspace}/tags/{tag}/workflows
DELETE /api/v1/workspaces/{workspace}/tags/{tag}/workflows
```

**Body:**

```json
{
  "workflow_ids": ["uuid-1", "uuid-2"]
}
```

---

## 15. Sticky Notes & Pinned Data

### Sticky Notes (Canvas Annotations)

```http
GET    /api/v1/workspaces/{workspace}/workflows/{workflow}/sticky-notes
POST   /api/v1/workspaces/{workspace}/workflows/{workflow}/sticky-notes
PUT    /api/v1/workspaces/{workspace}/workflows/{workflow}/sticky-notes/{stickyNote}
DELETE /api/v1/workspaces/{workspace}/workflows/{workflow}/sticky-notes/{stickyNote}
```

**Body (create/update):**

```json
{
  "content": "TODO: Add error handling here",
  "position": { "x": 200, "y": 300 },
  "color": "#FEF3C7"
}
```

### Pinned Node Data (Test Data)

Pinned data lets users save test input/output for individual nodes during development.

```http
GET    /api/v1/workspaces/{workspace}/workflows/{workflow}/pinned-data
POST   /api/v1/workspaces/{workspace}/workflows/{workflow}/pinned-data
POST   /api/v1/workspaces/{workspace}/workflows/{workflow}/pinned-data/{pinnedData}/toggle
DELETE /api/v1/workspaces/{workspace}/workflows/{workflow}/pinned-data/{pinnedData}
```

**Body (create):**

```json
{
  "node_id": "http_1",
  "data": { "body": { "test": "value" } },
  "is_input": true,
  "is_active": true
}
```

---

## 16. AI Workflow Builder

Generate a complete workflow graph from a natural language description.

### Build Workflow via AI

```http
POST /api/v1/workspaces/{workspace}/workflows/build
```

**Body:**

```json
{
  "description": "When a webhook is received, send an email to the user using SendGrid"
}
```

**Response (201):**

```json
{
  "message": "Workflow generated successfully.",
  "data": {
    "id": "uuid",
    "name": "Webhook to Email",
    "description": "When a webhook is received, send an email to the user using SendGrid",
    "icon": "mail",
    "color": "#10B981",
    "is_active": false,
    "current_version_id": "uuid",
    "current_version": {
      "version_number": 1,
      "trigger_type": "webhook",
      "nodes": [
        { "id": "webhook_1", "type": "trigger.webhook", "position": { "x": 0, "y": 0 }, "data": { "label": "Webhook Trigger" } },
        { "id": "mail_1", "type": "mail.send", "position": { "x": 400, "y": 0 }, "data": { "label": "Send Email" } }
      ],
      "edges": [
        { "source": "webhook_1", "target": "mail_1", "sourceHandle": "output", "targetHandle": "input" }
      ]
    },
    "execution_count": 0,
    "created_at": "2024-01-15T10:00:00Z"
  }
}
}
```

The AI builder:
1. Selects appropriate node types from the registry
2. Wires them with edges
3. Creates a workflow + published version in one call
4. The workflow is ready to load onto the canvas

---

## 17. Expression System & Data Mapping

Node configs can reference data from upstream nodes, trigger data, variables, and execution metadata using an expression syntax.

### Expression Syntax

```
{{ $nodes.http_1.output.body.id }}
{{ $trigger.body.email }}
{{ $vars.api_base_url }}
{{ $execution.id }}
{{ $loop.item.name }}
{{ $env.APP_NAME }}
```

### Available Contexts

| Prefix | Source | Example |
|--------|--------|---------|
| `$nodes.{node_id}` | Output of an upstream node | `$nodes.http_1.output.status_code` |
| `$trigger` | Original trigger data | `$trigger.body.user_id` |
| `$vars.{key}` | Workspace variable | `$vars.api_key` |
| `$execution` | Execution metadata | `$execution.id`, `$execution.started_at` |
| `$loop` | Inside loop nodes | `$loop.item`, `$loop.index` |
| `$env` | Environment config | `$env.APP_URL` |

### Frontend Expression Editor

The frontend should:
1. Parse node configs for `{{ ... }}` expressions
2. Show autocomplete dropdowns with available upstream node outputs
3. Validate expressions against `output_schema` of referenced nodes
4. Highlight invalid references (e.g., node not connected)

### Node Output Flattening

If a node has only one predecessor, the engine **flattens** that predecessor's output into the top level for convenience. So `{{ $trigger.body.email }}` may also be accessible as `{{ $body.email }}` in some contexts.

---

## 18. Permissions Matrix

All endpoints check permissions via `workspace.role` middleware. The user's permissions are loaded once per request.

| Permission | Key | Endpoints |
|------------|-----|-----------|
| View workflows | `workflow.view` | `GET /workflows`, `GET /workflows/{workflow}` |
| Create workflow | `workflow.create` | `POST /workflows`, `POST /workflows/{workflow}/duplicate` |
| Update workflow | `workflow.update` | `PUT /workflows/{workflow}`, `POST /versions/{version}/publish` |
| Delete workflow | `workflow.delete` | `DELETE /workflows/{workflow}` |
| Activate workflow | `workflow.activate` | `POST /workflows/{workflow}/activate`, `POST /deactivate` |
| Execute workflow | `workflow.execute` | `POST /workflows/{workflow}/execute` |
| View versions | `version.view` | `GET /workflows/{workflow}/versions` |
| Restore version | `version.restore` | `POST /versions/{version}/rollback` |
| View executions | `execution.view` | `GET /executions`, `GET /executions/{execution}`, `GET /executions/{execution}/nodes`, `GET /executions/{execution}/logs` |
| Retry execution | `execution.retry` | `POST /executions/{execution}/retry` |
| Replay execution | `execution.replay` | `POST /executions/{execution}/replay` |
| Cancel execution | `execution.cancel` | `POST /executions/{execution}/cancel` |
| Delete execution | `execution.delete` | `DELETE /executions/{execution}`, `DELETE /executions/bulk` |
| View credentials | `credential.view` | `GET /credentials`, `GET /credentials/{credential}` |
| Create credential | `credential.create` | `POST /credentials` |
| Update credential | `credential.update` | `PUT /credentials/{credential}` |
| Delete credential | `credential.delete` | `DELETE /credentials/{credential}` |
| Test credential | `credential.test` | `POST /credentials/{credential}/test` |
| View webhooks | `webhook.view` | `GET /webhooks`, `GET /webhooks/{webhook}` |
| Create webhook | `webhook.create` | `POST /workflows/{workflow}/webhook` |
| Update webhook | `webhook.update` | `PUT /webhooks/{webhook}` |
| Delete webhook | `webhook.delete` | `DELETE /webhooks/{webhook}` |
| View polling triggers | `polling-trigger.view` | `GET /polling-triggers` |
| Create polling trigger | `polling-trigger.create` | `POST /workflows/{workflow}/polling-trigger` |
| Update polling trigger | `polling-trigger.update` | `PUT /polling-triggers/{pollingTrigger}` |
| Delete polling trigger | `polling-trigger.delete` | `DELETE /polling-triggers/{pollingTrigger}` |

**403 Response when permission denied:**

```json
{
  "message": "You do not have permission to perform this action."
}
```

---

## 19. Rate Limits

| Endpoint Group | Limit | Header Keys |
|----------------|-------|--------------|
| Authentication | 5 req/min | `X-RateLimit-Limit`, `X-RateLimit-Remaining`, `X-RateLimit-Reset` |
| General API | 60 req/min | Same |
| Execution trigger | Custom throttle | Same |
| Webhook receive | 100 req/min | Same |

**Rate limit exceeded (429):**

```json
{
  "message": "Too many requests. Please try again later."
}
```

---

## 20. Error Handling

### Validation Errors (422)

```json
{
  "message": "The given data was invalid.",
  "errors": {
    "nodes": ["At least one node is required to create a version."],
    "nodes.0.id": ["Each node must have an id."],
    "edges.0.source": ["Each edge must have a source node id."]
  }
}
```

### Common Errors

| Status | Scenario | Message |
|--------|----------|---------|
| 400 | Invalid JSON | `Malformed JSON in request body.` |
| 401 | Missing/invalid token | `Unauthenticated.` |
| 403 | No permission | `You do not have permission...` |
| 404 | Resource not found | `Resource not found.` |
| 409 | Conflict (duplicate webhook) | `This workflow already has a manual webhook.` |
| 422 | Validation failed | Field-specific errors |
| 429 | Rate limited | `Too many requests.` |
| 500 | Server error | `Internal server error.` |

---

## 21. Complete Data Structure Reference

### Node (Canvas Element)

```typescript
interface Node {
  id: string;                    // Unique within the workflow (e.g., "trigger_1", "http_1")
  type: string;                  // Node type key (e.g., "http_request", "mail.send")
  position: {
    x: number;
    y: number;
  };
  data: {
    label?: string;              // Display name on canvas
    [key: string]: any;          // Node-specific config (url, method, etc.)
  };
}
```

### Edge (Connection)

```typescript
interface Edge {
  source: string;                // Source node id
  target: string;                // Target node id
  sourceHandle?: string;         // Output port (default: "output")
  targetHandle?: string;         // Input port (default: "input")
  id?: string;                   // Optional edge id
}
```

### Workflow Version (Canvas State)

```typescript
interface WorkflowVersion {
  id: string;
  workflow_id: string;
  version_number: number;
  name: string | null;
  description: string | null;
  trigger_type: string | null;   // "manual" | "webhook" | "schedule" | "cron" | "polling"
  trigger_config: object | null;
  nodes: Node[];
  edges: Edge[];
  viewport: {
    x: number;
    y: number;
    zoom: number;
  } | null;
  settings: {
    timeout?: number;            // seconds
    max_retries?: number;
    error_workflow_id?: string | null;
    [key: string]: any;
  } | null;
  change_summary: string | null;
  is_published: boolean;
  published_at: string | null;     // ISO8601
}
```

### Execution

```typescript
interface Execution {
  id: string;
  workflow_id: string;
  workspace_id: string;
  status: "pending" | "running" | "completed" | "failed" | "cancelled" | "waiting";
  mode: "manual" | "webhook" | "scheduled" | "polling" | "sub_workflow" | "retry";
  started_at: string | null;     // ISO8601
  finished_at: string | null;    // ISO8601
  duration_ms: number | null;
  error: object | null;
  attempt: number;
  max_attempts: number;
  credits_consumed: number;
  parent_execution_id: string | null;
  is_deterministic_replay: boolean;
  trigger_data: object | null;
  result_data: object | null;
  nodes: ExecutionNode[];
  nodes_count: number;
}
```

### Execution Node

```typescript
interface ExecutionNode {
  id: string;
  node_id: string;               // Matches the node.id from the version
  node_type: string;
  node_name: string;
  status: "pending" | "running" | "completed" | "failed" | "skipped";
  started_at: string | null;
  finished_at: string | null;
  duration_ms: number | null;
  input_data: object | null;     // Masked secrets
  output_data: object | null;    // Masked secrets
  error: object | null;
  sequence: number;              // Execution order
}
```

### Credential

```typescript
interface Credential {
  id: string;
  name: string;
  type: string;                  // e.g., "openai", "slack", "sendgrid"
  data: object;                  // Masked values for display
  last_used_at: string | null;
  expires_at: string | null;
}
```

### Webhook

```typescript
interface Webhook {
  id: string;
  workflow_id: string;
  uuid: string;                  // Public identifier
  node_id: string | null;        // Associated trigger node
  url: string;                   // Full public URL
  provider: string | null;       // "github", "stripe", or null (manual)
  is_externally_managed: boolean;
  path: string | null;
  methods: string[];
  is_active: boolean;
  auth_type: "none" | "header" | "basic" | "bearer" | null;
  rate_limit: number | null;
  response_mode: "immediate" | "wait" | null;
  response_status: number | null;
  response_body: object | null;
  call_count: number;
  last_called_at: string | null;
}
```

### Polling Trigger

```typescript
interface PollingTrigger {
  id: string;
  workflow_id: string;
  endpoint_url: string;
  http_method: string;
  headers: object | null;
  query_params: object | null;
  dedup_key: string;             // Field used to detect new items
  interval_seconds: number;      // Min: 60
  is_active: boolean;
  last_polled_at: string | null;
  next_poll_at: string | null;
  poll_count: number;
  trigger_count: number;
  last_error: string | null;
}
```

---

## Appendix A: Workflow Activation Webhook Status Lifecycle

When a workflow is activated, external webhooks (GitHub, Stripe) are registered asynchronously:

```
activate() called
  -> is_active = true
  -> webhook_status = "pending"
  -> WebhookRegistrationJob dispatched
     -> Success: webhook_status = "active", webhook_status_message = null
     -> Failure: webhook_status = "failed", webhook_status_message = "GitHub API error: ..."
```

The frontend should poll the workflow or listen for updates to show the webhook registration status.

## Appendix B: Execution Status State Machine

```
[pending] ---> [running] ---> [completed]
                 |    |
                 |    +---> [waiting] --(ResumeWorkflowJob)--> [running]
                 |    |
                 |    +---> [cancelled]
                 |    |
                 +---> [failed] --(retry)--> [pending] (child execution)
```

## Appendix C: Engine Queue System

The backend uses multiple queues for different execution priorities:

| Queue | Purpose |
|-------|---------|
| `workflows-default` | Normal workflow executions |
| `workflows-low` | Auto-retry executions (exponential backoff) |
| `long-running` | AI diagnosis, complex multi-step executions |
| `default` | General background jobs |

The frontend does not need to interact with queues directly, but knowing this helps understand why retries may have a delay.

---

**Related Documentation:**
- [API Reference](./api.md) — All endpoints in one place
- [Node Reference](./nodes.md) — Detailed node type schemas
- [Database Schema](./database-schema.md) — Table structures and relationships
- [Workflow Engine Guide](../core/03-workflow-engine.md) — Deep dive into execution internals
