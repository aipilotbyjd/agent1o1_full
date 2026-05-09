---
type: entity
status: sourced
sources: 2
last_updated: 2026-05-09
tags: [entity, core, workflow]
---

# Workflow

**TL;DR**: The core automation artifact — a named, versioned directed graph of nodes and connections that executes when triggered.

---

## Key Fields

| Field | Type | Notes |
|-------|------|-------|
| `id` | UUID | |
| `name` | string | User-facing name |
| `description` | string? | |
| `status` | enum | `draft`, `active`, `inactive`, `archived` |
| `version` | int | Current version number |
| `nodes` | JSON | Array of node objects |
| `connections` | JSON | Array of edge objects |
| `settings` | JSON | Timeout, retry config |
| `color` | string? | Visual color tag |
| `icon` | string? | Emoji or icon key |
| `category` | string? | User-defined category |
| `is_favorite` | bool | Starred in UI |
| `is_locked` | bool | Prevents editing |
| `folder_id` | FK? | Folder grouping |
| `execution_count` | int | Total runs |
| `last_executed_at` | timestamp? | |

## Relationships

- belongs to `Workspace`
- has many `Execution`
- has many `WorkflowVersion` (snapshots)
- belongs to many `Tag`
- has many `Webhook` (triggers)
- has many `WorkflowShare`
- has many `StickyNote` (canvas annotations)
- has many `PinnedNodeData` (test data pinned to nodes)
- has one `WorkflowApproval`

## Versioning

`WorkflowVersion` stores immutable snapshots. The current published version is tracked via `current_version_id`. Users can compare versions — see `IWorkflowVersionComparison` in the frontend types.

## Node Structure (embedded JSON)

Each node in `nodes[]`:
```json
{ "id": "...", "type": "http_request", "name": "Call API", "position": {"x": 0, "y": 0}, "parameters": {} }
```

Each connection in `connections[]`:
```json
{ "id": "...", "source_node_id": "...", "target_node_id": "...", "source_handle": null, "target_handle": null }
```

## Export Format

Exported workflows use the "linkflow" format (`IWorkflowExport`): `format_version`, `exportedAt`, and the workflow definition. Can be imported via `POST /workspaces/{id}/workflows/import`.

## Settings

```ts
{ timeout_seconds?, retry_on_failure?, max_retries?, error_workflow_id? }
```

`error_workflow_id` points to another workflow that runs on failure — a powerful error-handling pattern.

## API Endpoints

| Method | Path | Action |
|--------|------|--------|
| GET | `/workspaces/{id}/workflows` | List with filters |
| POST | `/workspaces/{id}/workflows` | Create |
| POST | `/workspaces/{id}/workflows/build` | AI-assisted build |
| POST | `/workspaces/{id}/workflows/import` | Import from export JSON |
| GET | `/workspaces/{id}/workflows/{id}` | Get |
| PUT | `/workspaces/{id}/workflows/{id}` | Update |
| DELETE | `/workspaces/{id}/workflows/{id}` | Delete |
| POST | `/workspaces/{id}/workflows/{id}/activate` | Activate |
| POST | `/workspaces/{id}/workflows/{id}/deactivate` | Deactivate |
| POST | `/workspaces/{id}/workflows/{id}/duplicate` | Clone |
| POST | `/workspaces/{id}/workflows/{id}/execute` | Run manually |
| GET | `/workspaces/{id}/workflows/{id}/export` | Export as JSON |
| GET | `/workspaces/{id}/workflows/{id}/executions` | List executions |
| GET | `/workspaces/{id}/workflows/{id}/versions` | List versions |
| POST | `/workspaces/{id}/workflows/{id}/versions` | Save snapshot |
| GET | `/workspaces/{id}/workflows/{id}/versions/diff` | Compare versions |
| GET | `/workspaces/{id}/workflows/{id}/versions/{vid}` | Get version |
| POST | `/workspaces/{id}/workflows/{id}/versions/{vid}/publish` | Publish version |
| POST | `/workspaces/{id}/workflows/{id}/versions/{vid}/rollback` | Rollback to version |
| GET | `/workspaces/{id}/workflows/{id}/shares` | List share links |
| POST | `/workspaces/{id}/workflows/{id}/shares` | Create share link |
| PUT | `/workspaces/{id}/workflows/{id}/shares/{sid}` | Update share |
| DELETE | `/workspaces/{id}/workflows/{id}/shares/{sid}` | Delete share |
| GET | `/workspaces/{id}/workflows/{id}/sticky-notes` | List canvas notes |
| POST | `/workspaces/{id}/workflows/{id}/sticky-notes` | Add canvas note |
| PUT | `/workspaces/{id}/workflows/{id}/sticky-notes/{nid}` | Update note |
| DELETE | `/workspaces/{id}/workflows/{id}/sticky-notes/{nid}` | Delete note |
| GET | `/workspaces/{id}/workflows/{id}/pinned-data` | List pinned test data |
| POST | `/workspaces/{id}/workflows/{id}/pinned-data` | Pin test data to node |
| DELETE | `/workspaces/{id}/workflows/{id}/pinned-data/{pid}` | Delete pinned data |
| POST | `/workspaces/{id}/workflows/{id}/pinned-data/{pid}/toggle` | Enable/disable pin |
| POST | `/workspaces/{id}/workflows/{id}/polling-trigger` | Create polling trigger |
| GET | `/shared/{token}` | **Public** — view shared workflow |
| POST | `/workspaces/{id}/shared/{token}/clone` | Clone shared workflow |

Model: `backend/app/Models/Workflow.php`
Frontend types: `frontend/src/types/workflow.type.ts`
API module: `frontend/src/api/modules/workflows/`

---

## Sources

- `raw/api-routes-2026-05-09.txt` — confirms workflow routes incl. versions/shares/sticky-notes/pinned-data/polling-trigger/build/activate/deactivate
- `raw/frontend-api-modules-2026-05-09.txt` — confirms `workflows/` module with editor.hooks, editor.service, shares.hooks, shares.service sub-files
- `backend/app/Models/Workflow.php` — code reference
- *(no external sources yet — flag: workflow versioning design doc, share/clone policy)*
