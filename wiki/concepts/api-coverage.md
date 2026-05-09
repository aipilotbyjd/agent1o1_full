---
type: query-output
status: sourced
sources: 2
last_updated: 2026-05-09
tags: [api, coverage, audit, frontend, backend]
---

# Backend ↔ Frontend API Coverage

**TL;DR**: Snapshot of how every backend route maps to a frontend API module, captured 2026-05-09 after a coverage audit closed all known gaps. Use this page to find the frontend hook for a given backend route — and to spot drift on future audits.

---

## Why this page exists

Filed as a query output from the *2026-05-09 lint*: *"check my backend and check all APIs are included in my frontend."* The audit closed 13 module-level gaps and 15+ endpoint-level gaps. This page is the durable artifact.

For the file-level deltas in that audit, see the log entry for 2026-05-09 update.

---

## Module map

| Backend route group | Frontend module | Module status |
|---------------------|-----------------|---------------|
| `auth/*`, `user/*`, `verify-email/*` | `auth/` | Full |
| `workspaces` | `workspaces/` | Full |
| `workspaces/{ws}/members`, `invitations` (manage) | `workspace-members/` | Full |
| `workspaces/{ws}/folders` | `folders/` | Full |
| `workspaces/{ws}/tags` | `tags/` | Full |
| `workspaces/{ws}/workflows` (+ versions, shares, sticky-notes, pinned-data) | `workflows/` (incl. `editor.*`, `shares.*`) | Full |
| `workspaces/{ws}/executions` (+ archived sub-resource) | `executions/`, `archived-executions/` | Full |
| `workspaces/{ws}/credentials`, `oauth/*` | `credentials/` | Full |
| `credential-types` (global catalog) | `credential-types/` | Full |
| `workspaces/{ws}/variables` | `variables/` | Full |
| `templates` (global), `workspaces/{ws}/templates/{id}/use` | `templates/` | Full |
| `nodes`, `node-categories` (global) | `node-types/` | Full |
| `workspaces/{ws}/sticky-notes` | `notes/` | Full |
| `workspaces/{ws}/agents` (+ conversations, triggers, skills attach/detach) | `agents/` | Full |
| `workspaces/{ws}/agent-skills` (+ references, scripts) | `agents/` (combined module) | Full |
| `workspaces/{ws}/webhooks` (manage) | `webhooks/` | Full |
| `workspaces/{ws}/polling-triggers` | `polling-triggers/` | Full |
| `notifications`, `notifications/{id}/*` | `notifications/` | Full |
| `notification-preferences` | `notification-preferences/` | Full |
| `notification-channels` | `notification-channels/` | Full |
| `invitations/{token}/*` (token-based accept/decline) | `invitations/` | Full |
| `workspaces/{ws}/activity-logs` | `activity-logs/` | Full |
| `workspaces/{ws}/credits` | `credits/` | Full |
| `workspaces/{ws}/billing` | `billing/` | Full |
| `workspaces/{ws}/log-streaming` | `log-streaming/` | Full |
| `workspaces/{ws}/git-sync` | `git-sync/` | Full |
| `workspaces/{ws}/dashboard`, `stats` | `dashboard/` | Full |

---

## Module file convention

Every frontend module under `frontend/src/api/modules/<name>/` contains:

| File | Role |
|------|------|
| `<name>.endpoints.ts` | URL builders only (no HTTP) |
| `<name>.service.ts` | `axiosClient` calls, response unwrapping |
| `<name>.keys.ts` | TanStack Query key factories |
| `<name>.hooks.ts` | `useQuery` / `useMutation` wrappers + `notify.*` toasts |
| `index.ts` | Barrel re-export |

The barrel `frontend/src/api/modules/index.ts` re-exports all modules.

---

## Public-facing routes (no auth, not in any workspace-scoped module)

These are external integration surfaces, not user-driven UI:

- `webhook/{uuid}` — public webhook receiver (handled by `WebhookReceiverController`, not a frontend concern)
- `webhook-wait/{uuid}` — wait-node resume (external systems POST here)
- `stripe/webhook` — Stripe events (server-to-server)
- `git-sync/webhook/{workspaceSlug}` — Git provider events
- `shared/{token}` — public workflow viewing (frontend can navigate to it, but the data is public)
- `oauth/callback` — OAuth provider redirect

---

## Server-Sent Events (SSE)

Two SSE endpoints stream live execution events. The frontend module exposes the URLs but consumers wire `EventSource` directly:

- `executions/{id}/stream` — single execution
- `executions/stream-all` — workspace-wide

See [[execution-engine]] for the runtime side.

---

## Open questions / next ingests

- Are there any hidden routes registered outside `routes/api.php` (e.g. via package service providers)? Worth a `php artisan route:list` re-dump on each audit to confirm.
- Public SSE protocol details — what events does the backend emit? Needs a source doc dropped into `raw/`.
- Connector calls (`ConnectorCallAttempt`, `ConnectorMetricDaily`) — appear in execution telemetry but not in any user-facing API. Internal-only?

---

## Sources

- `raw/api-routes-2026-05-09.txt` — backend route dump (194 lines, all `v1.*`)
- `raw/frontend-api-modules-2026-05-09.txt` — frontend module file tree
- *(this page is a `status: sourced` query output. Re-run the audit and re-snapshot both raw files when significant route changes land.)*
