# Node (Node Type)

**TL;DR**: A single step in a workflow. The `Node` model defines the *type* (what a node can do); individual workflow steps reference these types by `type` string.

---

## Two Meanings of "Node"

1. **Node Type** (`NodeType` / `Node` model) — a registered integration or capability available in the node palette
2. **Workflow Node** — an instance of a node type placed in a specific workflow (stored as JSON inside `Workflow.nodes[]`)

This page covers the **Node Type** (the registry entry).

---

## Node Categories

| Category | Purpose |
|----------|---------|
| `trigger` | Starts a workflow (webhook, schedule, polling) |
| `action` | Does something (HTTP call, send email, etc.) |
| `logic` | Controls flow (if/else, switch, loop) |
| `transform` | Reshapes data (map, filter, set variables) |
| `integration` | Third-party service connectors |
| `ai` | LLM and AI capabilities |
| `utility` | Misc helpers (delay, code, merge) |
| `interaction` | Human-in-the-loop (approval, form) |

Categories are stored in `NodeCategory` model (with `slug`, `icon`, `color`, `sort_order`).

## Node Type Fields

| Field | Type | Notes |
|-------|------|-------|
| `type` | string | Unique string key (e.g. `http_request`) |
| `name` | string | Display name |
| `description` | string | |
| `category` | FK → NodeCategory | |
| `version` | string/int | |
| `icon` | string? | |
| `color` | string? | |
| `node_kind` | string? | Sub-classification |
| `tags` | string[] | |
| `inputs` | JSON | `[{name, type, description}]` |
| `outputs` | JSON | `[{name, type, description}]` |
| `parameters` | JSON | Config fields shown in the panel |
| `credentials` | string[] | Required credential type slugs |
| `config_schema` | JSON | JSON Schema for config |
| `input_schema` | JSON | JSON Schema for incoming data |
| `output_schema` | JSON | JSON Schema for outgoing data |
| `credential_type` | string? | Linked credential type |
| `is_active` | bool | Whether available to users |
| `is_premium` | bool | Requires paid plan |
| `docs_url` | string? | External documentation link |

## Parameter Types

`string`, `number`, `boolean`, `options`, `select`, `code`, `json`, `credential`, `expression`

Each parameter can have `show_if` conditions for dynamic visibility.

## Relationships

- belongs to `NodeCategory`
- has many `Credential` (via `credential_type`)

## API

| Method | Path | Action |
|--------|------|--------|
| GET | `/node-types` | List all, optionally filter by category/search |
| GET | `/node-types/{type}` | Get one |
| GET | `/node-categories` | List categories |

Model: `backend/app/Models/Node.php`, `NodeCategory.php`
Frontend types: `frontend/src/types/nodeType.type.ts`
API module: `frontend/src/api/modules/node-types/`
