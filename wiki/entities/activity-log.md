---
type: entity
status: sourced
sources: 2
last_updated: 2026-05-09
tags: [entity, observability, audit]
---

# Activity Log

**TL;DR**: Immutable audit trail of actions taken within a workspace — who did what and when.

---

## Key Fields

| Field | Type | Notes |
|-------|------|-------|
| `id` | UUID | |
| `workspace_id` | FK | |
| `user_id` | FK? | Actor (null for system events) |
| `action` | string | Event slug e.g. `workflow.created`, `member.invited` |
| `subject_type` | string | Polymorphic type of the affected entity |
| `subject_id` | UUID | ID of the affected entity |
| `metadata` | JSON? | Extra context (IP address, before/after values, etc.) |
| `created_at` | timestamp | When the action occurred |

## Relationships

- belongs to `Workspace`
- belongs to `User` (actor — nullable for system events)
- polymorphic `subject` → any entity (Workflow, Member, Credential, etc.)

## Usage

Activity logs are **read-only** from the API — the system creates them automatically when tracked events occur. There is no create/update/delete endpoint for entries.

The export endpoint (`/activity-logs/export`) allows downloading the full log for compliance or external auditing purposes.

## API

| Method | Path | Action |
|--------|------|--------|
| GET | `/workspaces/{id}/activity-logs` | List (paginated) |
| GET | `/workspaces/{id}/activity-logs/export` | Download full log |
| GET | `/workspaces/{id}/activity-logs/{id}` | Get single entry |

Model: `backend/app/Models/ActivityLog.php` (inferred)
Frontend module: `frontend/src/api/modules/activity-logs/`

---

## Sources

- `raw/api-routes-2026-05-09.txt` — confirms `GET /activity-logs`, `GET /activity-logs/export`, `GET /activity-logs/{id}` routes (read-only, no write endpoints)
- `raw/frontend-api-modules-2026-05-09.txt` — confirms `activity-logs/` API module
- *(no code-level sources yet — flag for grounding when ActivityLog model is inspected)*
