# n8n Clone — Full Product Requirements Document

**Tech Stack:** Laravel (REST API backend) + React (frontend)
**Version:** 1.0
**Date:** March 26, 2026

---

## Table of Contents

1. [Product Overview](#1-product-overview)
2. [System Architecture](#2-system-architecture)
3. [Authentication & Authorization](#3-authentication--authorization)
4. [User & Team Management](#4-user--team-management)
5. [Workflow Engine](#5-workflow-engine)
6. [Node System](#6-node-system)
7. [Credential Management](#7-credential-management)
8. [Execution Engine](#8-execution-engine)
9. [Trigger System](#9-trigger-system)
10. [Expression Language](#10-expression-language)
11. [Variables & Environments](#11-variables--environments)
12. [Workflow Canvas (Frontend)](#12-workflow-canvas-frontend)
13. [Node Editor Panel (Frontend)](#13-node-editor-panel-frontend)
14. [Execution History & Logs](#14-execution-history--logs)
15. [API Reference Requirements](#15-api-reference-requirements)
16. [Database Schema Overview](#16-database-schema-overview)
17. [Queue & Background Processing](#17-queue--background-processing)
18. [Webhook System](#18-webhook-system)
19. [Error Handling & Retry Logic](#19-error-handling--retry-logic)
20. [Notifications & Alerting](#20-notifications--alerting)
21. [Settings & System Configuration](#21-settings--system-configuration)
22. [Non-Functional Requirements](#22-non-functional-requirements)

---

## 1. Product Overview

### 1.1 Purpose

This product is a self-hosted workflow automation platform, modeled after n8n. It allows non-technical and technical users to visually build automated workflows that connect external services, process data, and run on schedules or events — without writing code.

### 1.2 Core Concepts

| Concept | Description |
|---|---|
| **Workflow** | A directed graph of nodes defining a data flow or automation |
| **Node** | A single unit of logic — trigger, action, transformation, or condition |
| **Execution** | A single run of a workflow, triggered manually or automatically |
| **Credential** | Stored, encrypted connection details for an external service |
| **Trigger** | A node that starts a workflow, either on an event, schedule, or webhook |
| **Expression** | A dynamic value resolved at execution time using data from prior nodes |

### 1.3 User Goals

- Build automations visually with drag-and-drop
- Connect to services (HTTP, databases, SaaS APIs) via reusable credentials
- Run workflows on schedules, webhooks, or manual triggers
- Monitor execution history, debug failures, and retry runs
- Share workflows within teams with role-based access

---

## 2. System Architecture

### 2.1 High-Level Components

```
React Frontend
    |
    | HTTP/REST
    v
Laravel API (REST)
    |           |
    v           v
PostgreSQL   Redis (Queue / Cache / Pub-Sub)
                |
                v
         Queue Workers (Laravel Horizon or custom workers)
                |
                v
         Execution Engine (PHP process)
```

### 2.2 Backend Modules (Laravel)

| Module | Responsibility |
|---|---|
| `Auth` | Login, registration, token management |
| `Users` | User profile, team membership |
| `Teams` | Multi-tenancy grouping |
| `Workflows` | CRUD, versioning, activation |
| `Nodes` | Node type registry, metadata |
| `Credentials` | Encrypted storage, per-type schema |
| `Executions` | Execution records, logs, retry |
| `Webhooks` | Inbound webhook routing |
| `Triggers` | Scheduler, poller management |
| `Variables` | Environment-level key-value secrets |
| `Queue` | Job dispatch and worker management |

### 2.3 Frontend Modules (React)

| Module | Responsibility |
|---|---|
| `WorkflowCanvas` | Visual node graph editor |
| `NodePanel` | Node configuration side panel |
| `CredentialManager` | Credential creation and management UI |
| `ExecutionList` | History of workflow runs |
| `ExecutionDetail` | Per-execution log and data inspector |
| `WorkflowList` | Dashboard, workflow search/filter |
| `Settings` | User, team, and system settings |
| `Variables` | Environment variable management |

---

## 3. Authentication & Authorization

### 3.1 Authentication Methods

- **Email + Password** — standard form-based login
- **Personal Access Tokens (PAT)** — long-lived tokens for API access
- **OAuth2 (optional phase 2)** — Google, GitHub SSO

### 3.2 Session & Token Management

- On login, issue a short-lived **access token** (JWT, 15 minutes) and a **refresh token** (stored in `HttpOnly` cookie, 7 days)
- All API requests must include `Authorization: Bearer <access_token>`
- Refresh endpoint silently rotates both tokens
- On logout, revoke the refresh token server-side
- Failed login attempts should be rate-limited: lock account after 10 failures in 15 minutes

### 3.3 Role System

Three roles exist at the **team** level:

| Role | Description |
|---|---|
| `owner` | Full access; can delete the team, manage billing, and assign any role |
| `admin` | Can manage members, credentials, and all workflows in the team |
| `member` | Can create and run their own workflows; can use shared credentials |

One additional role exists at the **system** level (instance-wide):

| Role | Description |
|---|---|
| `superadmin` | Can manage all teams, users, and global settings |

### 3.4 Permission Matrix

| Action | owner | admin | member |
|---|---|---|---|
| Create workflow | ✓ | ✓ | ✓ |
| Edit own workflow | ✓ | ✓ | ✓ |
| Edit any workflow | ✓ | ✓ | ✗ |
| Delete any workflow | ✓ | ✓ | ✗ |
| Activate workflow | ✓ | ✓ | ✓ |
| View all executions | ✓ | ✓ | ✗ |
| View own executions | ✓ | ✓ | ✓ |
| Create credential | ✓ | ✓ | ✓ |
| Share credential | ✓ | ✓ | ✗ |
| Invite members | ✓ | ✓ | ✗ |
| Manage team settings | ✓ | ✓ | ✗ |
| Delete team | ✓ | ✗ | ✗ |

---

## 4. User & Team Management

### 4.1 User Registration

- Users register with: `name`, `email`, `password`
- Email verification required before full access
- Invitation-only mode (toggle in system settings): disables self-registration; users only join via invite link
- On registration, a personal default team is created automatically

### 4.2 User Profile

Users can update:
- Display name
- Email (re-verification required)
- Password
- Avatar (URL or file upload)
- Timezone (affects scheduler display and execution timestamps)
- Theme preference (light / dark / system)

### 4.3 Teams

- A team is the top-level namespace for all resources (workflows, credentials, variables)
- A user can belong to multiple teams
- Each team has:
  - `name`
  - `slug` (URL-safe unique identifier)
  - `plan` (for future billing: `free`, `starter`, `pro`)
  - `settings` (JSON blob for team-level config overrides)

### 4.4 Invitations

- Admins/owners can invite new users by email
- An invitation record stores: `email`, `role`, `team_id`, `token`, `expires_at`, `accepted_at`
- Invitation links expire after 7 days
- Inviting an existing platform user adds them to the team directly (no email required)
- Inviting a new email sends a signup+join email

### 4.5 Member Management

- List all members of a team with their roles and join dates
- Change a member's role (owner/admin only)
- Remove a member from the team
- Transfer ownership (owner only; new owner must be an existing admin)

---

## 5. Workflow Engine

### 5.1 Workflow Data Model

A workflow is stored as a versioned JSON document representing a directed graph:

```
Workflow:
  id              UUID
  team_id         UUID (FK)
  created_by      UUID (FK → users)
  name            string (max 255)
  description     text (nullable)
  is_active       boolean (default false)
  version         integer (auto-increment on save)
  nodes           JSON array of node objects
  connections     JSON array of connection objects
  settings        JSON (timeout, timezone, error_workflow_id, etc.)
  tags            string[] (searchable labels)
  created_at      timestamp
  updated_at      timestamp
  deleted_at      timestamp (soft delete)
```

### 5.2 Node Object Structure

Each node in the `nodes` array has:

```
Node:
  id              string (UUID, unique within workflow)
  type            string (e.g. "n8n-nodes-base.httpRequest")
  name            string (user-provided display name)
  position        { x: float, y: float }
  parameters      object (node-specific configuration, may include expressions)
  credentials     object (map of credential type to credential ID)
  disabled        boolean
  notes           string (optional annotation)
  on_error        "stop" | "continue" | "continue_error_output"
```

### 5.3 Connection Object Structure

```
Connection:
  source_node_id  string
  source_output   string (e.g. "main", "true", "false", "error")
  target_node_id  string
  target_input    string (e.g. "main")
  order           integer (for multiple connections from same output)
```

### 5.4 Workflow Operations

#### 5.4.1 Create

- Create a new empty workflow with a default Start node
- Duplicate an existing workflow (creates a new copy with "(copy)" appended to name)

#### 5.4.2 Read

- List all workflows in a team (paginated, filterable by name, tag, status, creator)
- Get a single workflow by ID (includes all nodes, connections, settings)
- Get workflow version history (id, version, changed_by, created_at)
- Restore a previous version

#### 5.4.3 Update

- Update workflow (auto-saves a new version; previous version retained in history)
- Auto-save draft every 30 seconds while canvas is open and dirty
- Rename, update tags, update description

#### 5.4.4 Delete

- Soft-delete: mark `deleted_at`, exclude from listings
- Hard delete only available to owners after confirmation
- Deleting an active workflow must first deactivate it

#### 5.4.5 Activate / Deactivate

- Activating a workflow registers its triggers (webhook routes, cron jobs, pollers)
- Deactivating unregisters all triggers and stops scheduled execution
- Only one activation state per workflow (no staging vs. production distinction in v1)

### 5.5 Workflow Settings

Per-workflow overridable settings:

| Setting | Type | Default | Description |
|---|---|---|---|
| `timeout` | integer (seconds) | 300 | Max execution duration |
| `timezone` | string | team timezone | For cron expressions |
| `save_execution_progress` | boolean | true | Store intermediate node outputs |
| `save_manual_executions` | boolean | true | Store runs triggered manually |
| `save_data_success` | "all" \| "none" | "all" | How much output data to store |
| `save_data_error` | "all" \| "none" | "all" | How much error data to store |
| `error_workflow_id` | UUID | null | Workflow to run on execution failure |
| `max_retries` | integer | 0 | Auto-retry count on failure |
| `retry_wait` | integer (seconds) | 60 | Delay between retries |

---

## 6. Node System

### 6.1 Node Type Registry

All available node types are registered in the database and served via API. The frontend fetches the registry on load to build the node palette.

A node type definition includes:

```
NodeType:
  name            string (unique identifier, e.g. "n8n-nodes-base.httpRequest")
  display_name    string (e.g. "HTTP Request")
  description     string
  category        string (e.g. "Core", "Data Transformation", "Communication")
  icon            string (SVG path or URL)
  version         integer
  is_trigger      boolean
  is_webhook      boolean
  credentials     array of required credential types
  inputs          array of input slot definitions
  outputs         array of output slot definitions
  properties      JSON Schema defining the node's parameters UI
```

### 6.2 Node Categories (Phase 1 Required)

#### Core Nodes (must ship in v1)

| Node | Type | Description |
|---|---|---|
| **Start** | Trigger | Manual trigger; entry point for every workflow |
| **Webhook** | Trigger | Receives HTTP POST/GET at a unique URL |
| **Schedule** | Trigger | Cron-based or interval-based trigger |
| **HTTP Request** | Action | Makes any HTTP/HTTPS request (GET, POST, PUT, PATCH, DELETE) |
| **Set** | Transform | Manually sets/overrides fields on the item |
| **IF** | Logic | Branches based on a condition (true/false outputs) |
| **Switch** | Logic | Routes to one of N outputs based on a value |
| **Merge** | Flow | Combines items from multiple branches |
| **Split in Batches** | Flow | Processes items in configurable chunks |
| **Code** | Transform | Runs custom JavaScript/Python on items |
| **Function** | Transform | Single-item JavaScript transformation |
| **Wait** | Flow | Pauses execution for a duration or until a webhook |
| **No Op** | Utility | Does nothing; useful for placeholder connections |
| **Stop and Error** | Utility | Throws a custom error and halts the workflow |
| **Execute Workflow** | Utility | Triggers another workflow and waits for result |
| **Execute Workflow Trigger** | Trigger | Entry point for sub-workflows |

#### Data Nodes (must ship in v1)

| Node | Description |
|---|---|
| **JSON Transform** | Parse, stringify, pick, omit JSON fields |
| **Date & Time** | Parse, format, add/subtract date intervals |
| **Math** | Arithmetic and numeric operations |
| **String** | Regex, case conversion, trim, pad, split, join |
| **Crypto** | Hash (MD5, SHA1, SHA256), HMAC, base64 encode/decode |
| **XML** | Parse XML to JSON and back |
| **CSV** | Parse CSV string to items or items to CSV |
| **HTML Extract** | Scrape data from HTML using CSS selectors |
| **Rename Keys** | Bulk-rename item fields |
| **Remove Duplicates** | Deduplicate items by a key |
| **Sort** | Sort items by field |
| **Limit** | Return only the first N items |
| **Summarize** | Aggregate: sum, count, avg, min, max by group |
| **Filter** | Keep/discard items based on conditions |
| **Compare Datasets** | Diff two sets of items |

#### Integration Nodes (prioritized v1 set)

| Node | Category |
|---|---|
| **Email (SMTP)** | Communication |
| **Slack** | Communication |
| **Discord** | Communication |
| **Telegram** | Communication |
| **Twilio (SMS)** | Communication |
| **Gmail** | Communication |
| **Google Sheets** | Productivity |
| **Google Calendar** | Productivity |
| **Google Drive** | Storage |
| **Airtable** | Database |
| **Notion** | Productivity |
| **Trello** | Project Management |
| **GitHub** | Developer Tools |
| **GitLab** | Developer Tools |
| **Jira** | Project Management |
| **Linear** | Project Management |
| **Stripe** | Finance |
| **HubSpot** | CRM |
| **Salesforce** | CRM |
| **Mailchimp** | Marketing |
| **SendGrid** | Email |
| **Twitch** | Social |
| **Twitter/X** | Social |
| **OpenAI** | AI |
| **MySQL** | Database |
| **PostgreSQL** | Database |
| **MongoDB** | Database |
| **Redis** | Cache |
| **FTP / SFTP** | Storage |
| **AWS S3** | Storage |
| **Dropbox** | Storage |

### 6.3 Node Property Types (UI Controls)

The `properties` array in a node type definition maps to the following UI control types in the React frontend:

| Type | Description |
|---|---|
| `string` | Single-line text input |
| `number` | Numeric input with optional min/max |
| `boolean` | Toggle switch |
| `options` | Dropdown select from a static list |
| `multiOptions` | Multi-select dropdown |
| `collection` | Grouped set of optional sub-fields |
| `fixedCollection` | Repeatable group of fields (e.g. headers list) |
| `json` | Multi-line JSON editor with syntax highlighting |
| `color` | Color picker |
| `dateTime` | Date-time picker |
| `hidden` | Not shown in UI; programmatically set |
| `resourceLocator` | Search-and-select from a remote resource list |
| `credentials` | Credential picker (filtered by type) |

### 6.4 Dynamic Properties

Node properties can have display conditions. A property is shown/hidden based on:
- The value of another property in the same node
- A boolean expression referencing the current node's parameter values

This is defined in the node type's `display_if` field per property.

### 6.5 Node Versioning

- Node types are versioned independently
- When a node type is updated, existing workflows retain their pinned version
- A warning is shown in the canvas when a node has an available upgrade
- Upgrading a node migrates parameters where possible; incompatible fields are flagged

---

## 7. Credential Management

### 7.1 Credential Data Model

```
Credential:
  id              UUID
  team_id         UUID (FK)
  created_by      UUID (FK → users)
  name            string
  type            string (matches node type's required credential type)
  data            JSON (encrypted at rest using AES-256)
  is_shared       boolean (team-wide vs. creator-only)
  created_at      timestamp
  updated_at      timestamp
  deleted_at      timestamp
```

### 7.2 Credential Types

Each credential type defines a schema of fields. Examples:

**HTTP Basic Auth:**
- `username` (string, required)
- `password` (string, required, masked)

**API Key:**
- `api_key` (string, required, masked)
- `header_name` (string, default: "X-API-Key")
- `in` (enum: header | query)

**OAuth2:**
- `client_id` (string)
- `client_secret` (string, masked)
- `authorization_url` (string)
- `token_url` (string)
- `scope` (string)
- `access_token` (string, auto-filled after OAuth flow)
- `refresh_token` (string, auto-filled, masked)
- `token_expiry` (datetime, auto-managed)

**Database:**
- `host`, `port`, `database`, `user`, `password`

Each credential type is registered in the same node type registry.

### 7.3 Encryption

- All credential `data` fields are encrypted using AES-256-GCM before storage
- The encryption key is stored separately from the database (environment variable)
- Decryption happens only inside the execution engine, never exposed via API responses
- A separate `test_credential` endpoint runs a lightweight connectivity check per type

### 7.4 OAuth2 Flow

- For OAuth2 credential types, the backend provides a `/api/credentials/{id}/oauth/authorize` redirect URL
- After the user grants access, the provider redirects to a backend callback route
- The backend exchanges the code for tokens and stores them encrypted
- Token refresh is handled automatically by the execution engine before each use

### 7.5 Credential Sharing

- By default a credential is private to its creator
- Owner/admin can mark it as team-shared (visible and usable by all team members)
- Shared credentials: members can use but not view the raw secrets
- Non-shared credentials: only the creator can use or view them

---

## 8. Execution Engine

### 8.1 Execution Lifecycle

```
PENDING → RUNNING → SUCCESS
                  → ERROR
                  → CANCELED
                  → WAITING (if a Wait node is encountered)
```

### 8.2 Execution Data Model

```
Execution:
  id              UUID
  workflow_id     UUID (FK)
  workflow_version integer (snapshot of version at time of run)
  status          enum (pending, running, success, error, canceled, waiting)
  mode            enum (manual, webhook, trigger, retry, sub_workflow)
  triggered_by    UUID | null (user ID if manual)
  started_at      timestamp
  finished_at     timestamp
  execution_time  integer (milliseconds)
  node_data       JSON (per-node: input, output, error, execution_time)
  error           JSON (null on success)
  retries         integer
  parent_execution_id UUID | null (for sub-workflows)
  created_at      timestamp
```

### 8.3 Execution Process

1. **Dispatch**: A job is queued with the `execution_id` and full resolved workflow snapshot
2. **Initialization**: Worker resolves all referenced credentials (decrypted)
3. **Graph traversal**: Starting from the trigger node, execute nodes in topological order
4. **Item passing**: Each node receives an array of items from connected upstream nodes
5. **Output routing**: Node outputs are mapped to connected downstream nodes
6. **State persistence**: After each node, intermediate results are saved to `execution.node_data`
7. **Completion**: On finish (all branches exhausted or error), status is updated and notifications fired

### 8.4 Item Model

All data flowing through a workflow is an array of **items**. Each item is:

```
Item:
  json    object     (the payload)
  binary  object     (optional; map of key to binary file reference)
```

Nodes always receive `Item[]` and always produce `Item[]`.

### 8.5 Multi-Output Routing

- A node can have multiple named outputs (e.g. `true`, `false`, `error`)
- Each output produces its own `Item[]` subset
- Connections define which output connects to which downstream node's input
- Items that don't match any output are discarded (unless an explicit catch-all exists)

### 8.6 Execution Isolation

- Each execution runs in an isolated worker process (no shared state between concurrent executions)
- Code nodes (JavaScript/Python) run in a sandboxed environment with:
  - No filesystem access (except explicit temp dir)
  - No network access (all HTTP goes through the HTTP Request node)
  - CPU time limit: configurable per team (default 10s per code node)
  - Memory limit: 128 MB per code node

### 8.7 Manual Execution

- "Test" run: triggers a single execution in manual mode; user waits for result in the canvas
- "Run from node": re-runs from a selected node using the last execution's data for upstream nodes (useful for debugging)
- "Run with test data": allows the user to paste custom input JSON before running

---

## 9. Trigger System

### 9.1 Trigger Types

#### Manual Trigger

- Activated only via the API or canvas "Execute" button
- No scheduling or event listening

#### Schedule Trigger (Cron)

- Supports standard 5-field cron expressions
- Also supports human-readable shortcuts: every X minutes/hours/days/weeks
- Stored in the database as a cron expression + timezone
- At activation, registers a cron job in the scheduler (Redis-based or database-backed queue)
- At deactivation, removes the cron entry

#### Webhook Trigger

- At activation, registers a unique URL: `/webhook/{uuid}`
- Supports GET and POST
- Configurable response mode:
  - `immediately`: respond with HTTP 200 before workflow finishes
  - `when_done`: hold the HTTP connection until workflow completes, then respond with workflow output
- Configurable authentication:
  - None
  - Header token (`x-webhook-token`)
  - Basic auth
- Supports binary payloads (multipart/form-data, application/octet-stream)
- Rate limit: configurable per webhook (default: 100 req/min)

#### Polling Trigger

- Some integrations require periodic polling rather than receiving webhooks
- The engine polls the external service at a configured interval (min 1 minute)
- Only fires the workflow when new data is found (compares last-seen timestamp or cursor)

### 9.2 Trigger Registration

Active triggers are tracked in a `triggers` table:

```
Trigger:
  id              UUID
  workflow_id     UUID
  team_id         UUID
  type            enum (manual, schedule, webhook, polling)
  config          JSON (cron expression, webhook path, poll interval, etc.)
  is_active       boolean
  last_fired_at   timestamp
  next_fire_at    timestamp (for schedule/polling)
  created_at      timestamp
```

---

## 10. Expression Language

### 10.1 Overview

Expressions allow dynamic values in node parameters. They are evaluated at execution time against the current execution context.

Syntax: `{{ expression_here }}`

Example: `{{ $json.user.email }}` or `{{ $node["HTTP Request"].json.status }}`

### 10.2 Available Variables

| Variable | Description |
|---|---|
| `$json` | The `json` field of the current input item |
| `$binary` | The binary data of the current input item |
| `$item(index)` | Access a specific item by index from the current input |
| `$items(nodeName)` | All items from a specific upstream node |
| `$node["NodeName"].json` | Output data from a named node (first item) |
| `$node["NodeName"].context` | Context data stored by a named node |
| `$execution.id` | Current execution ID |
| `$execution.mode` | Execution mode (manual, webhook, etc.) |
| `$workflow.id` | Current workflow ID |
| `$workflow.name` | Current workflow name |
| `$now` | Current timestamp (ISO 8601) |
| `$today` | Current date (ISO 8601) |
| `$env.MY_VAR` | Team environment variable |
| `$vars.MY_VAR` | Alias for `$env` |

### 10.3 Built-in Functions

The expression engine exposes a helper library:

- **String**: `$string(value)`, `.toUpperCase()`, `.toLowerCase()`, `.trim()`, `.includes()`, `.startsWith()`, `.endsWith()`, `.replace()`, `.split()`, `.substring()`
- **Number**: `$number(value)`, `.toFixed()`, `.round()`, `.floor()`, `.ceil()`
- **Date**: `$now`, `$today`, `DateTime.now()`, `.plus()`, `.minus()`, `.toFormat()`, `.toISO()`
- **Array**: `.length`, `.first()`, `.last()`, `.filter()`, `.map()`, `.find()`, `.includes()`
- **Object**: `Object.keys()`, `Object.values()`, `Object.entries()`
- **JSON**: `JSON.parse()`, `JSON.stringify()`

### 10.4 Expression Evaluation Security

- Expressions are evaluated in a sandboxed JavaScript context
- No access to `process`, `require`, `eval`, `fetch`, `XMLHttpRequest`, or any global Node.js APIs
- Evaluation timeout: 1 second per expression
- Circular reference protection in JSON serialization

### 10.5 Expression Editor (Frontend)

- Inline expression toggle button next to any parameter field
- When toggled, field becomes a code editor (Monaco-based or CodeMirror)
- Auto-complete suggestions for `$json`, `$node`, `$vars`, and available upstream node names
- Live preview: shows the resolved value of the expression against the last available execution data

---

## 11. Variables & Environments

### 11.1 Variable Types

| Type | Scope | Editable By |
|---|---|---|
| **Team Variable** | All workflows in a team | Admin/Owner |
| **System Variable** | All teams (read-only) | Superadmin only |

### 11.2 Variable Data Model

```
Variable:
  id          UUID
  team_id     UUID (FK, null for system variables)
  key         string (uppercase, underscore only, unique per team)
  value       text (encrypted at rest)
  description text (nullable)
  is_secret   boolean (if true, value is never returned in API responses)
  created_at  timestamp
  updated_at  timestamp
```

### 11.3 Variable Access in Expressions

- `$env.VARIABLE_NAME` or `$vars.VARIABLE_NAME`
- Read-only at expression time (not modifiable by workflow nodes)
- Variables are resolved and injected into execution context before run starts

---

## 12. Workflow Canvas (Frontend)

### 12.1 Canvas Technology

The canvas is a React component using a graph rendering library (e.g. React Flow / XYFlow). It must support:

- Pan (click + drag on empty space)
- Zoom (scroll wheel, pinch on trackpad)
- Box-select (drag-select multiple nodes)
- Snap-to-grid (toggleable)
- Mini-map (collapsible)

### 12.2 Node Rendering

Each node on the canvas must display:

- Node icon (colored per category)
- Node display name (editable on double-click)
- Status indicator (idle, running, success, error)
- Input and output port handles
- Disabled overlay (semi-transparent when node.disabled = true)
- Note tooltip (if note is set)

### 12.3 Connection Rendering

- Connections rendered as animated bezier curves
- Distinct colors per output type (e.g. green = true, red = false, grey = main)
- Hover state: highlights connected pair
- Clicking a connection shows delete option

### 12.4 Node Palette

- Left-side collapsible panel listing all available node types
- Organized by category with expand/collapse
- Search input: filters nodes by name, category, or tag
- Dragging a node from the palette onto the canvas creates a new node instance
- Double-clicking empty canvas space also opens a quick-add search

### 12.5 Canvas Toolbar

Top bar contains:

| Control | Function |
|---|---|
| Workflow name | Editable inline |
| Save button | Saves draft (or shows "Saved" if auto-saved) |
| Activate/Deactivate toggle | Enables or disables the workflow |
| Execute button | Triggers a manual test run |
| Execution history button | Opens execution history side panel |
| Zoom controls | Fit, zoom in, zoom out |
| Settings button | Opens workflow settings panel |
| Share / Export button | Download as JSON |

### 12.6 Canvas Context Menu

Right-click on a node:
- Rename
- Duplicate
- Disable / Enable
- Copy
- Paste (from clipboard)
- Add note
- Delete

Right-click on empty canvas:
- Paste
- Add node (opens search)

### 12.7 Real-Time Execution Visualization

During a manual test run:
- Nodes flash an animated "running" indicator as they execute
- On success: node gets a green check with item count badge
- On error: node gets a red X with error tooltip
- Connections animate data flow direction
- User can click any node after run to inspect its input/output data

### 12.8 Keyboard Shortcuts

| Shortcut | Action |
|---|---|
| `Ctrl/Cmd + Z` | Undo |
| `Ctrl/Cmd + Shift + Z` | Redo |
| `Ctrl/Cmd + C` | Copy selected nodes |
| `Ctrl/Cmd + V` | Paste nodes |
| `Ctrl/Cmd + A` | Select all |
| `Delete / Backspace` | Delete selected |
| `Ctrl/Cmd + S` | Save |
| `Ctrl/Cmd + Enter` | Execute workflow |
| `Space` | Toggle fit-to-view |
| `Ctrl/Cmd + F` | Open node search |

---

## 13. Node Editor Panel (Frontend)

### 13.1 Panel Structure

When a node is selected (single-click), a right-side panel opens with:

1. **Header**: Node icon, type name, display name (editable)
2. **Tabs**: Parameters | Settings | Notes
3. **Parameters tab**: Dynamic form generated from the node type's `properties` schema
4. **Settings tab**: Node-level overrides (disable node, on-error behavior, retry count)
5. **Notes tab**: Free-text markdown note for this node

### 13.2 Dynamic Form Generation

The frontend must be able to generate a complete parameter form from the node type's JSON Schema `properties` definition:

- Each property maps to its UI control type (see §6.3)
- Conditional visibility via `display_if` rules
- Expression toggle per field
- Validation with inline error messages (required, pattern, min/max)
- Credential picker: shows a dropdown of matching credentials + "Create new" option

### 13.3 HTTP Request Node UI (Special Case)

Due to its complexity, the HTTP Request node has an expanded UI:

- Method selector (GET, POST, PUT, PATCH, DELETE, HEAD, OPTIONS)
- URL field with expression support
- Authentication dropdown (none, basic, bearer, API key, OAuth2, credential)
- Headers: key-value table with add/remove rows
- Query parameters: key-value table
- Body: type selector (none, JSON, form-data, x-www-form-urlencoded, raw, binary)
- Body editor based on type (JSON editor, key-value table, raw text)
- Response tab: expected format (JSON, text, binary, file), response code handling
- Pagination: off | response body cursor | link header | offset+limit
- Timeout: per-request override

### 13.4 Code Node UI (Special Case)

- Language selector (JavaScript or Python)
- Full-screen Monaco/CodeMirror editor
- Auto-complete for `$input`, `$items`, `$node`, `$env`, `$binary`
- "Test" button to run the code against the last execution data
- Error line highlighting

### 13.5 Data Inspector

When the canvas shows post-execution state:
- Each node's input and output data is viewable in the panel
- Toggle between Table view and JSON view
- Table view: shows items as rows with columns per JSON key
- Binary data: shows filename, size, MIME type, and download link
- Pagination if > 25 items

---

## 14. Execution History & Logs

### 14.1 Execution List

- Accessible per workflow (top toolbar button) and globally (sidebar menu)
- Columns: ID (truncated), status (icon + color), trigger mode, start time, duration, items processed
- Filter by: status, date range, trigger mode
- Sort by: start time (default desc), duration
- Pagination: 25 per page

### 14.2 Execution Detail View

Clicking an execution opens a detail view:

- Shows the workflow canvas in read-only state with execution state overlaid (per-node success/error/item counts)
- Timeline view: shows each node's execution time as a bar chart
- Clicking a node shows: input data, output data, error (if any), execution time

### 14.3 Execution Logs (Structured)

Per execution, structured logs are captured:

```
ExecutionLog:
  execution_id    UUID
  node_id         string
  level           enum (info, warn, error, debug)
  message         string
  data            JSON
  timestamp       timestamp
```

Logs are viewable in the execution detail panel, filterable by level and node.

### 14.4 Retry

- Any execution with status `error` can be manually retried
- "Retry with original data" — re-runs using the same input as the original trigger
- "Retry from failed node" — re-runs from the failed node using stored intermediate data
- Retried executions are linked to the original via `parent_execution_id`

### 14.5 Data Retention

- Execution records are retained based on team plan and settings
- Default retention: 30 days for full data, 90 days for metadata-only (status, duration)
- A background job purges expired records nightly
- Users can manually delete individual executions

---

## 15. API Reference Requirements

All API endpoints follow REST conventions. Base path: `/api/v1`.

### 15.1 Authentication Endpoints

| Method | Path | Description |
|---|---|---|
| POST | `/auth/register` | Register new user |
| POST | `/auth/login` | Login; returns access + refresh tokens |
| POST | `/auth/logout` | Revoke refresh token |
| POST | `/auth/refresh` | Rotate tokens |
| POST | `/auth/forgot-password` | Send reset email |
| POST | `/auth/reset-password` | Reset with token |
| GET | `/auth/me` | Get current user |

### 15.2 User Endpoints

| Method | Path | Description |
|---|---|---|
| PATCH | `/users/me` | Update profile |
| POST | `/users/me/avatar` | Upload avatar |
| PATCH | `/users/me/password` | Change password |
| GET | `/users/me/api-tokens` | List PATs |
| POST | `/users/me/api-tokens` | Create PAT |
| DELETE | `/users/me/api-tokens/{id}` | Revoke PAT |

### 15.3 Team Endpoints

| Method | Path | Description |
|---|---|---|
| GET | `/teams` | List user's teams |
| POST | `/teams` | Create team |
| GET | `/teams/{id}` | Get team details |
| PATCH | `/teams/{id}` | Update team |
| DELETE | `/teams/{id}` | Delete team |
| GET | `/teams/{id}/members` | List members |
| PATCH | `/teams/{id}/members/{userId}` | Change member role |
| DELETE | `/teams/{id}/members/{userId}` | Remove member |
| POST | `/teams/{id}/invitations` | Send invite |
| GET | `/teams/{id}/invitations` | List invitations |
| DELETE | `/teams/{id}/invitations/{id}` | Cancel invitation |
| POST | `/invitations/{token}/accept` | Accept invitation |

### 15.4 Workflow Endpoints

| Method | Path | Description |
|---|---|---|
| GET | `/workflows` | List workflows (paginated, filterable) |
| POST | `/workflows` | Create workflow |
| GET | `/workflows/{id}` | Get workflow |
| PUT | `/workflows/{id}` | Full update (save) |
| PATCH | `/workflows/{id}` | Partial update |
| DELETE | `/workflows/{id}` | Soft delete |
| POST | `/workflows/{id}/duplicate` | Duplicate |
| PATCH | `/workflows/{id}/activate` | Activate |
| PATCH | `/workflows/{id}/deactivate` | Deactivate |
| GET | `/workflows/{id}/versions` | List version history |
| POST | `/workflows/{id}/versions/{v}/restore` | Restore version |
| POST | `/workflows/{id}/execute` | Manual execute |
| GET | `/workflows/export` | Export all as JSON |
| POST | `/workflows/import` | Import from JSON |

### 15.5 Node Type Endpoints

| Method | Path | Description |
|---|---|---|
| GET | `/node-types` | List all node types |
| GET | `/node-types/{name}` | Get node type definition |

### 15.6 Credential Endpoints

| Method | Path | Description |
|---|---|---|
| GET | `/credentials` | List credentials (no raw data returned) |
| POST | `/credentials` | Create credential |
| GET | `/credentials/{id}` | Get credential (no raw data) |
| PATCH | `/credentials/{id}` | Update credential |
| DELETE | `/credentials/{id}` | Delete credential |
| POST | `/credentials/{id}/test` | Test connectivity |
| GET | `/credentials/{id}/oauth/authorize` | Start OAuth2 flow |
| GET | `/credentials/types` | List all credential types |

### 15.7 Execution Endpoints

| Method | Path | Description |
|---|---|---|
| GET | `/executions` | List executions (global, filterable) |
| GET | `/executions/{id}` | Get execution detail |
| DELETE | `/executions/{id}` | Delete execution record |
| POST | `/executions/{id}/retry` | Retry execution |
| POST | `/executions/{id}/cancel` | Cancel running execution |
| GET | `/workflows/{id}/executions` | Executions for a workflow |

### 15.8 Variable Endpoints

| Method | Path | Description |
|---|---|---|
| GET | `/variables` | List variables (values hidden for secrets) |
| POST | `/variables` | Create variable |
| PATCH | `/variables/{id}` | Update variable |
| DELETE | `/variables/{id}` | Delete variable |

### 15.9 Webhook Endpoint

| Method | Path | Description |
|---|---|---|
| GET/POST | `/webhook/{uuid}` | Public webhook receiver (no auth) |
| GET/POST | `/webhook-test/{uuid}` | Test-mode webhook receiver |

---

## 16. Database Schema Overview

### 16.1 Core Tables

```
users
  id, name, email, password_hash, email_verified_at, avatar_url,
  timezone, theme, created_at, updated_at

teams
  id, name, slug, owner_id, plan, settings_json, created_at, updated_at

team_user (pivot)
  team_id, user_id, role, joined_at

invitations
  id, team_id, email, role, token, expires_at, accepted_at, invited_by

personal_access_tokens
  id, user_id, name, token_hash, last_used_at, expires_at, created_at

password_reset_tokens
  email, token, created_at
```

### 16.2 Workflow Tables

```
workflows
  id, team_id, created_by, name, description, is_active,
  version, nodes_json, connections_json, settings_json,
  tags_json, created_at, updated_at, deleted_at

workflow_versions
  id, workflow_id, version, nodes_json, connections_json, settings_json,
  changed_by, created_at
```

### 16.3 Node & Credential Tables

```
node_types
  id, name, display_name, description, category, icon, version,
  is_trigger, is_webhook, credentials_json, inputs_json,
  outputs_json, properties_json, created_at, updated_at

credentials
  id, team_id, created_by, name, type, data_encrypted,
  is_shared, created_at, updated_at, deleted_at

credential_types
  id, name, display_name, description, properties_json, test_url, created_at
```

### 16.4 Execution Tables

```
executions
  id, workflow_id, workflow_version, status, mode, triggered_by,
  started_at, finished_at, execution_time_ms, node_data_json,
  error_json, retries, parent_execution_id, created_at

execution_logs
  id, execution_id, node_id, level, message, data_json, timestamp

triggers
  id, workflow_id, team_id, type, config_json, is_active,
  last_fired_at, next_fire_at, created_at, updated_at
```

### 16.5 Variable Tables

```
variables
  id, team_id (nullable), key, value_encrypted, description,
  is_secret, created_at, updated_at
```

### 16.6 Webhook Table

```
webhooks
  id, workflow_id, node_id, uuid, method, response_mode,
  auth_type, auth_config_json, rate_limit, is_active, created_at
```

---

## 17. Queue & Background Processing

### 17.1 Queue Architecture

Use **Laravel Horizon** (Redis-backed) to manage job queues.

### 17.2 Queue Names and Priorities

| Queue | Priority | Description |
|---|---|---|
| `critical` | Highest | Webhook-triggered executions (low-latency requirement) |
| `high` | High | Manual test executions (user is waiting) |
| `default` | Normal | Scheduled and polling trigger executions |
| `low` | Low | Retry executions, cleanup jobs, notification delivery |

### 17.3 Jobs

| Job Class | Queue | Description |
|---|---|---|
| `ExecuteWorkflowJob` | critical/high/default | Runs a single workflow execution |
| `ProcessWebhookJob` | critical | Handles inbound webhook, resolves workflow, enqueues execution |
| `PollTriggerJob` | default | Polls an external service for new data |
| `RetryExecutionJob` | low | Retries a failed execution |
| `SendNotificationJob` | low | Sends email/Slack error notifications |
| `PurgeExecutionDataJob` | low | Nightly cleanup of expired execution records |
| `RefreshOAuthTokenJob` | default | Proactively refreshes expiring OAuth tokens |

### 17.4 Concurrency

- Default: 10 concurrent `ExecuteWorkflowJob` workers
- Per-team concurrency limit: configurable (default: 5 concurrent runs per team)
- If limit exceeded, job is queued until a slot is free (no rejection, just delayed start)

### 17.5 Job Timeouts

- `ExecuteWorkflowJob` timeout = workflow `settings.timeout` (default 300s) + 30s buffer
- If timed out, the execution is marked as `error` with reason "execution_timeout"

---

## 18. Webhook System

### 18.1 Webhook URL Format

```
Production:  https://{domain}/webhook/{uuid}
Test mode:   https://{domain}/webhook-test/{uuid}
```

Test mode runs the workflow in manual mode (stores execution) but does not require the workflow to be activated.

### 18.2 Request Handling

1. Lookup the webhook record by UUID
2. Verify auth (if configured): reject with 401 if mismatch
3. Check rate limit: reject with 429 if exceeded
4. Parse request: method, headers, query params, body, binary files
5. Dispatch `ProcessWebhookJob`
6. If `response_mode = immediately`: respond with `{ success: true, executionId }` immediately
7. If `response_mode = when_done`: hold connection, wait for execution result, respond with last node's output

### 18.3 Binary File Handling

- Multipart file uploads are saved temporarily to object storage (S3 / local disk)
- Binary references are stored in item's `binary` field as metadata (key, filename, size, mime_type, url)
- Binary files are deleted after execution completes (configurable retention)

### 18.4 Webhook Response Customization

The Webhook Trigger node supports a "Respond to Webhook" sub-node (or inline config):
- Custom HTTP status code
- Custom response body (expression-supported)
- Custom response headers

---

## 19. Error Handling & Retry Logic

### 19.1 Node-Level Error Behavior

Each node has an `on_error` setting:

| Setting | Behavior |
|---|---|
| `stop` | Halt execution, mark as error, trigger error workflow if set |
| `continue` | Skip the errored item, continue with remaining items |
| `continue_error_output` | Route the error item to the `error` output connection instead |

### 19.2 Workflow-Level Error Workflow

- If `settings.error_workflow_id` is set, on any unhandled execution error the system automatically runs that other workflow
- The error workflow receives: the original execution ID, error message, error node name, workflow name, and full error stack

### 19.3 Automatic Retry

- If `settings.max_retries > 0`, failed executions are automatically re-queued
- Uses exponential backoff: first retry after `retry_wait` seconds, subsequent retries multiply by 2
- A retry is marked as a new execution with `mode = retry` and `parent_execution_id` pointing to original

### 19.4 Dead Letter Queue

- After all retries exhausted with continued failure, the execution is marked `error` permanently
- A dead-letter queue entry is created for admin review
- Admins can manually retry or discard from the DLQ

---

## 20. Notifications & Alerting

### 20.1 Notification Channels

- **Email** (via SMTP or configured email integration)
- **Slack** (webhook or Slack app integration)
- In-app notification bell (future v2)

### 20.2 Notification Events

| Event | Default Recipients |
|---|---|
| Workflow execution failed | Workflow creator |
| Workflow execution failed (N consecutive times) | Team admins |
| Credential OAuth token expiring in 7 days | Credential creator |
| New team invitation received | Invitee |
| Invitation accepted | Inviter |

### 20.3 Notification Settings

Per-user, per-event toggles for each channel:
- `workflow.execution_failed.email` = true/false
- `workflow.execution_failed.slack` = true/false

System-level defaults are set by superadmin.

---

## 21. Settings & System Configuration

### 21.1 Team Settings

| Setting | Type | Description |
|---|---|---|
| `default_timezone` | string | Used for cron scheduling |
| `max_concurrent_executions` | integer | Per-team concurrency cap |
| `execution_retention_days` | integer | How long to keep full execution data |
| `allow_external_webhooks` | boolean | Enable/disable webhook trigger type |
| `allow_code_nodes` | boolean | Enable/disable Code/Function nodes |
| `invitation_only` | boolean | Restrict new members to invitation-only |

### 21.2 System Settings (Superadmin)

| Setting | Description |
|---|---|
| `registration_enabled` | Allow self-registration |
| `max_workflows_per_team` | Hard cap on workflow count per team |
| `max_executions_per_hour` | Rate limit per team |
| `default_execution_timeout` | Global fallback timeout (seconds) |
| `smtp_host / port / user / pass` | Email delivery config |
| `encryption_key_rotation_date` | Audit log of last key rotation |
| `maintenance_mode` | Show maintenance page, block all API traffic |

### 21.3 User Settings (Personal)

| Setting | Description |
|---|---|
| Timezone | Personal display timezone |
| Theme | light / dark / system |
| Email notification preferences | Per-event toggles |
| Default workflow view | List / grid |

---

## 22. Non-Functional Requirements

### 22.1 Performance

| Metric | Target |
|---|---|
| Workflow canvas initial load | < 2 seconds |
| Workflow save (API) | < 500ms (P95) |
| Execution start latency (webhook) | < 1 second from receipt to first node |
| Execution start latency (scheduled) | < 5 seconds from scheduled time |
| API response time | < 200ms (P95) for all non-execution endpoints |
| Canvas render | Up to 200 nodes without frame drop below 30fps |

### 22.2 Security

- All traffic over HTTPS (TLS 1.2 minimum)
- API rate limiting: 300 requests/minute per IP, 1000/minute per authenticated user
- CSRF protection on all state-changing endpoints
- Input sanitization and SQL injection prevention via ORM (Eloquent parameterized queries)
- XSS prevention: all user-generated content escaped in React (no dangerouslySetInnerHTML)
- Secrets (credentials, variables) never returned in API responses in plaintext
- Execution sandboxing for Code nodes
- Content-Security-Policy headers on all frontend responses
- Regular dependency vulnerability scanning

### 22.3 Scalability

- All stateless API servers can scale horizontally
- Queue workers can be scaled independently of API servers
- Database connection pooling (PgBouncer or Laravel built-in pool)
- Redis for sessions, cache, queue, and pub-sub (separate Redis instances recommended for prod)
- Object storage (S3-compatible) for binary file attachments

### 22.4 Reliability

- Health check endpoint: `GET /api/v1/health` returns queue status, DB status, Redis status
- Graceful shutdown: workers drain active jobs before stopping (SIGTERM handling)
- Database migrations are backward-compatible (no destructive schema changes without a migration window)
- Zero-downtime deploys using rolling restarts

### 22.5 Observability

- Structured JSON logging (all backend logs include: timestamp, level, request_id, user_id, team_id)
- Error tracking integration (Sentry-compatible DSN configurable via env)
- Queue depth and worker health metrics exposed via `/api/v1/metrics` (Prometheus format)
- Execution time histograms per node type (for performance analysis)

### 22.6 Accessibility

- All interactive elements keyboard-accessible
- ARIA labels on canvas nodes and controls
- Color is not the only indicator of status (also uses icon + text)
- Minimum contrast ratio: 4.5:1 (WCAG AA)

### 22.7 Internationalization

- All UI strings externalized (i18n-ready)
- Date/time always displayed in the user's configured timezone
- v1 ships in English only; i18n architecture in place for future languages

### 22.8 Browser Support

- Chrome 100+
- Firefox 100+
- Safari 15+
- Edge 100+
- No IE support

---

*End of Document*
