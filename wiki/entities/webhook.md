# Webhook

**TL;DR**: An inbound HTTP endpoint that triggers a workflow when called by an external service.

---

## Key Fields

| Field | Type | Notes |
|-------|------|-------|
| `id` | UUID | |
| `workspace_id` | FK | |
| `workflow_id` | FK | The workflow to trigger |
| `name` | string | User label |
| `token` | string | Secret token in the URL path |
| `method` | string | `GET`, `POST`, `PUT`, etc. |
| `is_active` | bool | |
| `last_triggered_at` | timestamp? | |
| `trigger_count` | int | |

## How It Works

External services POST to `/webhooks/{token}`. The public route (no auth) receives the request, looks up the webhook by token, and dispatches an execution of the linked workflow — passing the HTTP request body as `trigger_data`.

## Health Checking

`webhooks:health-check` runs daily at 03:00 — likely checks that webhooks still point to active workflows.

## Relationships

- belongs to `Workspace`
- belongs to `Workflow`
- triggers `Execution` records

## API

| Method | Path | Action |
|--------|------|--------|
| GET | `/workspaces/{id}/webhooks` | List |
| POST | `/workspaces/{id}/webhooks` | Create |
| PUT | `/workspaces/{id}/webhooks/{id}` | Update |
| DELETE | `/workspaces/{id}/webhooks/{id}` | Delete |
| POST | `/webhooks/{token}` | **Public** — trigger endpoint |

Model: `backend/app/Models/Webhook.php`
Frontend types: `frontend/src/types/webhook.type.ts`
API module: `frontend/src/api/modules/webhooks/`
