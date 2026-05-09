---
type: entity
status: sourced
sources: 2
last_updated: 2026-05-09
tags: [entity, tenant, core]
---

# Workspace

**TL;DR**: Top-level multi-tenant container. Every resource (workflow, credential, member) belongs to a workspace.

---

## Key Fields

| Field | Type | Notes |
|-------|------|-------|
| `id` | UUID | `HasUuid` trait |
| `name` | string | Display name |
| `slug` | string | URL-safe identifier |
| `logo` | string | Logo URL |
| `settings` | array (JSON) | Workspace-wide config |
| `owner_id` | FK → User | The owner |

## Relationships

- `owner` → `User`
- `members` → `WorkspaceMember` (pivot with Role)
- `workflows` → many `Workflow`
- `credentials` → many `Credential`
- `webhooks` → many `Webhook`
- `folders` → many `Folder`
- `variables` → many `Variable`
- `tags` → many `Tag`
- `agents` → many `Agent`
- `subscription` → `Subscription` (via Cashier `Billable`)
- `environments` → many `WorkspaceEnvironment`
- `settings` → `WorkspaceSetting`

## Auth & Roles

The `workspace.role` middleware resolves the current user's role and permissions once per request. Controllers use `$this->can(Permission::...)`.

Roles: see [[entities/roles-and-permissions]].

## Billing

Workspace is the billable entity (uses Cashier `Billable` trait). Plans, credit packs, and usage snapshots are scoped to the workspace.

## API

All workspace-scoped API routes follow: `GET /workspaces/{workspace}/...`

| Method | Path | Action |
|--------|------|--------|
| GET | `/workspaces` | List user's workspaces |
| POST | `/workspaces` | Create |
| GET | `/workspaces/{id}` | Get |
| PUT | `/workspaces/{id}` | Update |
| DELETE | `/workspaces/{id}` | Delete |
| GET | `/workspaces/{id}/settings` | Get settings |
| PUT | `/workspaces/{id}/settings` | Update settings |
| POST | `/workspaces/{id}/transfer-ownership` | Transfer to another member |
| POST | `/workspaces/{id}/leave` | Current user leaves |
| GET | `/workspaces/{id}/activity-logs` | List audit log entries |
| GET | `/workspaces/{id}/activity-logs/export` | Export audit log |
| GET | `/workspaces/{id}/activity-logs/{id}` | Get single entry |
| GET | `/workspaces/{id}/git-sync/status` | Git sync state |
| POST | `/workspaces/{id}/git-sync/export` | Push to git |
| POST | `/workspaces/{id}/git-sync/import` | Pull from git |
| GET | `/workspaces/{id}/log-streaming` | List log streaming configs |
| POST | `/workspaces/{id}/log-streaming` | Create config |
| GET | `/workspaces/{id}/log-streaming/{id}` | Get config |
| PUT | `/workspaces/{id}/log-streaming/{id}` | Update config |
| DELETE | `/workspaces/{id}/log-streaming/{id}` | Delete config |

Model: `backend/app/Models/Workspace.php`

---

## Sources

- `raw/api-routes-2026-05-09.txt` — confirms workspace + nested-resource routing surface incl. git-sync, log-streaming, activity-logs, settings, transfer-ownership, leave
- `raw/frontend-api-modules-2026-05-09.txt` — confirms `workspaces/` API module
- `backend/app/Models/Workspace.php`, `backend/routes/api.php` — code references for `workspace.role` middleware + `scopeBindings()`
- *(no external sources yet — flag: tenant-isolation policy, billing-vs-membership boundary)*
