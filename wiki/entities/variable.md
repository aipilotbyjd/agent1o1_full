# Variable

**TL;DR**: Workspace-level key-value store for environment variables and shared config accessible across all workflows in a workspace.

---

## Key Fields

| Field | Type | Notes |
|-------|------|-------|
| `id` | UUID | |
| `workspace_id` | FK | |
| `key` | string | Variable name (e.g. `BASE_URL`, `API_TIMEOUT`) |
| `value` | string (encrypted?) | The value |
| `type` | string | `string`, `number`, `boolean`, `secret` |
| `description` | string? | |

## Purpose

Variables avoid hardcoding values inside workflow nodes. A node can reference `{{ $vars.API_KEY }}` and the execution engine resolves it at runtime from the workspace variable store.

This is the equivalent of n8n's "Credential" for static values — or environment variables in code.

## Secret Variables

Variables of type `secret` are encrypted and masked in the UI — never returned in full by the API (only `***` placeholders shown after creation).

## Relationships

- belongs to `Workspace`
- referenced at runtime by any workflow in the workspace

## WorkspaceEnvironment

`WorkspaceEnvironment` and `WorkflowEnvironmentRelease` suggest variables can be scoped to environments (e.g. `staging`, `production`). This allows the same workflow to behave differently across environments.

## API

| Method | Path | Action |
|--------|------|--------|
| GET | `/workspaces/{id}/variables` | List |
| POST | `/workspaces/{id}/variables` | Create |
| PUT | `/workspaces/{id}/variables/{id}` | Update |
| DELETE | `/workspaces/{id}/variables/{id}` | Delete |

Model: `backend/app/Models/Variable.php`
Frontend types: `frontend/src/types/variable.type.ts`
API module: `frontend/src/api/modules/variables/`
