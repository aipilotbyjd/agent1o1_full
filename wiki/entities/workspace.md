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

Model: `backend/app/Models/Workspace.php`
