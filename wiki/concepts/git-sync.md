---
type: concept
status: sourced
sources: 2
last_updated: 2026-05-09
tags: [git, version-control, devops, workflow]
---

# Git Sync

**TL;DR**: Git sync lets workspaces push and pull workflow definitions to/from a git repository — enabling version control, code review, and CI/CD for automations. Inspired by n8n's git sync feature.

---

## How It Works

Workflows are serialized to the linkflow export format (JSON — same as `GET /workflows/{id}/export`) and pushed to a configured git repository. Importing pulls definitions back and creates or updates workflows in the workspace.

---

## Three API Surfaces

### Workspace Management

| Method | Path | Action |
|--------|------|--------|
| GET | `/workspaces/{id}/git-sync/status` | Current sync state (last synced at, branch, remote URL, pending changes) |
| POST | `/workspaces/{id}/git-sync/export` | Serialize and push workflows to git |
| POST | `/workspaces/{id}/git-sync/import` | Pull from git and apply to workspace |

### Incoming Webhook (auto-sync)

| Method | Path | Action |
|--------|------|--------|
| POST | `/git-sync/webhook/{workspaceSlug}` | **Public** — triggered by git push events to auto-pull |

The webhook endpoint allows CI/CD pipelines to automatically sync changes when a branch is merged — workflows-as-code.

---

## Export Format

Uses the same `IWorkflowExport` format as manual workflow export:
- `format_version` — schema version
- `exportedAt` — timestamp
- Workflow definition (nodes, connections, settings)

---

## Use Cases

- **Dev/staging/prod parity**: maintain the same workflow definitions across environments via git branches
- **Code review for automations**: PRs for workflow changes before they reach production
- **Disaster recovery**: workflow definitions versioned alongside application code
- **Audit trail**: git history provides immutable record of workflow changes

---

## Relationship to [[entities/workflow]]

Git sync operates on the same JSON that `export`/`import` endpoints use. The `import` endpoint creates or updates workflows based on the exported JSON.

See also [[references/competitors]] — n8n pioneered git sync as a feature for developer-focused automation platforms.

---

## Sources

- `raw/api-routes-2026-05-09.txt` — confirms `GET /git-sync/status`, `POST /git-sync/export`, `POST /git-sync/import`, and public `POST /git-sync/webhook/{workspaceSlug}` routes
- `raw/frontend-api-modules-2026-05-09.txt` — confirms `git-sync/` API module
- *(no external sources yet — flag: supported git providers, auth mechanism for git remote, branch strategy)*
