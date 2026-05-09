---
type: entity
status: sourced
sources: 2
last_updated: 2026-05-09
tags: [entity, organization, workflow]
---

# Tag

**TL;DR**: Tags are workspace-level labels that can be applied to workflows for filtering and organization.

---

## Key Fields

| Field | Type | Notes |
|-------|------|-------|
| `id` | UUID | |
| `workspace_id` | FK | |
| `name` | string | Label text |
| `color` | string? | Hex color for visual distinction |
| `workflows_count` | int | Cached count of tagged workflows |

## Relationships

- belongs to `Workspace`
- belongs to many `Workflow` (many-to-many pivot)

## Usage

Tags vs Folders: Tags are many-to-many (a workflow can have multiple tags); folders are one-to-many (a workflow belongs to at most one folder). Tags are the multi-label system; folders are the hierarchical grouping system.

Tag-workflow associations are managed through dedicated sub-endpoints rather than inline on the workflow update endpoint.

## API

| Method | Path | Action |
|--------|------|--------|
| GET | `/workspaces/{id}/tags` | List |
| POST | `/workspaces/{id}/tags` | Create |
| GET | `/workspaces/{id}/tags/{id}` | Get |
| PUT | `/workspaces/{id}/tags/{id}` | Update |
| DELETE | `/workspaces/{id}/tags/{id}` | Delete |
| POST | `/workspaces/{id}/tags/{id}/workflows` | Attach tag to workflows |
| DELETE | `/workspaces/{id}/tags/{id}/workflows` | Detach tag from workflows |

Model: `backend/app/Models/Tag.php` (inferred)
Frontend module: `frontend/src/api/modules/tags/`

---

## Sources

- `raw/api-routes-2026-05-09.txt` — confirms tag CRUD + `tags/{id}/workflows` attach/detach endpoints
- `raw/frontend-api-modules-2026-05-09.txt` — confirms `tags/` API module
- *(no code-level sources yet — flag for grounding when Tag model is inspected)*
