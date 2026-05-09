---
type: entity
status: sourced
sources: 2
last_updated: 2026-05-09
tags: [entity, templates, sharing]
---

# Template

**TL;DR**: A pre-built, shareable workflow blueprint that users can fork into their workspace.

---

## Key Fields

| Field | Type | Notes |
|-------|------|-------|
| `id` | UUID | |
| `name` | string | Template display name |
| `description` | string? | What it does |
| `category` | string? | Use-case grouping |
| `nodes` | JSON | Pre-configured nodes |
| `connections` | JSON | Pre-configured edges |
| `settings` | JSON | Default settings |
| `tags` | string[] | Searchable tags |
| `icon` | string? | |
| `color` | string? | |
| `is_public` | bool | Available to all users vs workspace-private |
| `use_count` | int | How many times forked |

## Model

`WorkflowTemplate` in the backend. Templates are either global (managed by the LinkFlow team) or workspace-specific (user-created).

## Forking

When a user forks a template, a new `Workflow` is created in their workspace with the template's nodes, connections, and settings pre-populated. The user then customises it.

## Relationships

- belongs to `Workspace` (if workspace-private) or null (if global)
- referenced by `Workflow` after forking (no persistent FK — it's a copy)

## API

| Method | Path | Action |
|--------|------|--------|
| GET | `/templates` | List public templates |
| GET | `/templates/{id}` | Get public template |
| POST | `/workspaces/{id}/templates/{id}/use` | Fork template into workspace |

Model: `backend/app/Models/WorkflowTemplate.php`
Frontend types: `frontend/src/types/template.type.ts`
API module: `frontend/src/api/modules/templates/`

---

## Sources

- `raw/api-routes-2026-05-09.txt` — confirms public `GET /templates` + `GET /templates/{id}` + workspace-scoped `POST /workspaces/{id}/templates/{id}/use`
- `raw/frontend-api-modules-2026-05-09.txt` — confirms `templates/` API module
- `backend/app/Models/WorkflowTemplate.php` — code reference
- *(no external sources yet — flag: template marketplace design, fork-vs-clone semantics decision)*
