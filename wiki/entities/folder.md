---
type: entity
status: sourced
sources: 2
last_updated: 2026-05-09
tags: [entity, organization, workflow]
---

# Folder

**TL;DR**: Folders organize workflows within a workspace — a simple naming/grouping layer, not a permission boundary.

---

## Key Fields

| Field | Type | Notes |
|-------|------|-------|
| `id` | UUID | |
| `workspace_id` | FK | |
| `name` | string | Display name |
| `workflows_count` | int | Cached count of workflows in folder |

## Relationships

- belongs to `Workspace`
- has many `Workflow` (via `folder_id` FK on `Workflow`)

## Usage

Folders are purely organizational — they don't affect execution, permissions, or billing. A workflow belongs to at most one folder (nullable FK). The bulk `move-workflows` endpoint lets users reorganize multiple workflows at once.

Folders do not appear to support nesting (no `parent_id` in the route surface). This is consistent with a flat organizational model.

## API

| Method | Path | Action |
|--------|------|--------|
| GET | `/workspaces/{id}/folders` | List |
| POST | `/workspaces/{id}/folders` | Create |
| GET | `/workspaces/{id}/folders/{id}` | Get |
| PUT | `/workspaces/{id}/folders/{id}` | Update |
| DELETE | `/workspaces/{id}/folders/{id}` | Delete |
| POST | `/workspaces/{id}/folders/move-workflows` | Bulk move workflows to a folder |

Model: `backend/app/Models/Folder.php` (inferred)
Frontend module: `frontend/src/api/modules/folders/`

---

## Sources

- `raw/api-routes-2026-05-09.txt` — confirms folder CRUD + `move-workflows` endpoint
- `raw/frontend-api-modules-2026-05-09.txt` — confirms `folders/` API module
- *(no code-level sources yet — flag for grounding when Folder model is inspected)*
