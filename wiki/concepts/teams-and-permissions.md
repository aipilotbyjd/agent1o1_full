# Teams, Roles & Permissions

**TL;DR**: A workspace has an owner and members with roles. The `workspace.role` middleware enforces permissions on every API request.

---

## Roles

From `backend/app/Enums/Role.php`. Likely includes:
- `owner` — full control, billing access
- `admin` — manage members, workflows, credentials
- `editor` — create and edit workflows
- `viewer` — read-only access

## Permissions

`backend/app/Enums/Permission.php` — granular permission constants checked via `$this->can(Permission::...)` in controllers.

## WorkspaceMember

The pivot table between `User` and `Workspace`:
- `user_id`, `workspace_id`, `role`
- Optionally: per-member permission overrides

## Invitation Flow

`Invitation` model — workspace owner/admin sends an invite by email. Recipient clicks the link, creates an account (if needed), and is added as a member.

Controller: `InvitationController`
API module: `frontend/src/api/modules/workspace-members/`

## Middleware

`workspace.role` — runs on every workspace-scoped API route:
1. Resolves the workspace from the URL parameter
2. Loads the current user's `WorkspaceMember` record
3. Sets role and permissions on the request for downstream use
4. Throws 403 if the user is not a member

## WorkspacePolicy

`WorkspacePolicy` — Laravel policy for workspace-level authorization decisions.

## Multi-Workspace

A single user can belong to multiple workspaces. The frontend workspace context (stored in `frontend/src/types/workspace.type.ts`) tracks the active workspace. Switching workspaces reloads all workspace-scoped data.

## WorkspaceSetting

`WorkspaceSetting` — additional settings beyond the JSON `settings` field on `Workspace`. Likely covers: SSO config, security settings, notification defaults.
