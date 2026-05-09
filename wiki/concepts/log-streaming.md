---
type: concept
status: sourced
sources: 2
last_updated: 2026-05-09
tags: [logging, observability, devops, integrations]
---

# Log Streaming

**TL;DR**: Log streaming forwards execution logs in real-time to external observability systems (Datadog, Papertrail, Splunk, custom webhooks) via a per-workspace configuration.

---

## Model: LogStreamingConfig

One or more configs per workspace, each pointing to an external log destination.

| Field | Type | Notes |
|-------|------|-------|
| `id` | UUID | |
| `workspace_id` | FK | |
| `type` | string | Destination type e.g. `datadog`, `papertrail`, `webhook` |
| `endpoint` | string | Target URL or host |
| `api_key` | string (encrypted?) | Auth key for the destination |
| `is_active` | bool | Enable/disable without deleting |
| `event_types` | array? | Filter which log events to forward |

---

## API

| Method | Path | Action |
|--------|------|--------|
| GET | `/workspaces/{id}/log-streaming` | List configs |
| POST | `/workspaces/{id}/log-streaming` | Create config |
| GET | `/workspaces/{id}/log-streaming/{id}` | Get config |
| PUT | `/workspaces/{id}/log-streaming/{id}` | Update config |
| DELETE | `/workspaces/{id}/log-streaming/{id}` | Delete config |

---

## How It Relates to Execution Logs

When a workflow executes, `ExecutionLog` records are created. Log streaming picks these up and forwards them to configured destinations. This is separate from the in-product log viewer (`GET /executions/{id}/logs`).

---

## Why It Exists

Enterprise users want centralized observability — all automation logs alongside infrastructure logs in one platform. Log streaming means users don't have to visit LinkFlow to diagnose failures; they can use their existing observability stack.

---

## Open Questions

- Which destination types are supported at launch?
- Is delivery guaranteed (at-least-once) or best-effort?
- Is there buffering/batching to handle destination downtime?

---

## Sources

- `raw/api-routes-2026-05-09.txt` — confirms `log-streaming` CRUD routes under workspace scope
- `raw/frontend-api-modules-2026-05-09.txt` — confirms `log-streaming/` API module
- *(no external sources yet — flag: supported destination types, delivery semantics, auth scheme per destination)*
