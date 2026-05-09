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

## API Endpoints (frontend reference)

| Method | Path | Action |
|--------|------|--------|
| GET | `/workspaces/{id}/workflows` | List with filters |
| POST | `/workspaces/{id}/workflows` | Create |
| GET | `/workspaces/{id}/workflows/{id}` | Get |
| PUT | `/workspaces/{id}/workflows/{id}` | Update |
| DELETE | `/workspaces/{id}/workflows/{id}` | Delete |
| POST | `/workspaces/{id}/workflows/{id}/execute` | Run |
| POST | `/workspaces/{id}/workflows/{id}/duplicate` | Clone |
| POST | `/workspaces/{id}/workflows/import` | Import |

Model: `backend/app/Models/Workflow.php`
Frontend types: `frontend/src/types/workflow.type.ts`
API module: `frontend/src/api/modules/workflows/`
