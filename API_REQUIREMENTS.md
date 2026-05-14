# API Requirements For Backend

Base URL: `/api/v1`

This document is for backend implementation/testing. It explains which APIs the frontend needs, why they are needed, and what feature will use them.

## Priority Summary

| Priority | Area | Why Needed |
|---|---|---|
| P0 | Workflow editor | Main product screen. Users create, edit, run, save, publish, and test workflows here. |
| P0 | Auth + user | Required before authenticated workspace APIs can work. |
| P1 | Workflow support pages | Needed for workflow listing, folders, credentials, executions, variables, triggers, and logs. |
| P1 | Workspace/admin pages | Needed for workspace settings, members, notifications, billing, and activity logs. |
| P2 | Agent pages | Needed if agent builder/agent management is in scope for this release. |
| Pending product decision | Sales/customer/products/projects/invoices/chat/mail | These pages currently use static/demo frontend data, so exact backend APIs are not defined yet. |

## Workflow Editor

Purpose: this is the core canvas where users build automations with nodes and edges.

Frontend work enabled:

- Load available node categories and node definitions.
- Create and open workflows.
- Save workflow versions from canvas state.
- Publish or rollback workflow versions.
- Run a workflow manually from editor.
- Test a single node.
- Use AI build/generate workflow flow.
- Duplicate, import, export, activate, deactivate workflows.
- Save pinned test data for nodes.
- Create webhook and polling triggers from workflow.
- Show workflow execution history.
- Share public workflow links and clone shared workflows.

APIs needed:

```text
/auth/login
/auth/register
/auth/logout
/auth/refresh
/user

/node-categories
/node-categories/{category}
/nodes
/nodes/{node}

/workspaces/{workspace}/workflows
/workspaces/{workspace}/workflows/{workflow}
/workspaces/{workspace}/workflows/{workflow}/versions
/workspaces/{workspace}/workflows/{workflow}/versions/{version}
/workspaces/{workspace}/workflows/{workflow}/versions/{version}/publish
/workspaces/{workspace}/workflows/{workflow}/versions/{version}/rollback
/workspaces/{workspace}/workflows/{workflow}/versions/diff

/workspaces/{workspace}/workflows/{workflow}/execute
/workspaces/{workspace}/nodes/sandbox

/workspaces/{workspace}/workflows/build
/workspaces/{workspace}/workflows/{workflow}/duplicate
/workspaces/{workspace}/workflows/{workflow}/activate
/workspaces/{workspace}/workflows/{workflow}/deactivate
/workspaces/{workspace}/workflows/{workflow}/export
/workspaces/{workspace}/workflows/import

/workspaces/{workspace}/workflows/{workflow}/pinned-data
/workspaces/{workspace}/workflows/{workflow}/pinned-data/{pinnedData}
/workspaces/{workspace}/workflows/{workflow}/pinned-data/{pinnedData}/toggle

/workspaces/{workspace}/workflows/{workflow}/webhook
/workspaces/{workspace}/workflows/{workflow}/polling-trigger
/workspaces/{workspace}/workflows/{workflow}/executions

/workspaces/{workspace}/workflows/{workflow}/shares
/workspaces/{workspace}/workflows/{workflow}/shares/{share}
/shared/{token}
/workspaces/{workspace}/shared/{token}/clone
```

Backend notes:

- For node testing, frontend should use `/workspaces/{workspace}/nodes/sandbox`.
- Do not use `/workspaces/{workspace}/workflows/test-node`; that URL exists in old frontend constants but backend route is different.
- Workflow save should support version payload with `nodes`, `edges`, `viewport`, `settings`, and `change_summary`.
- Workflow create/update currently look metadata-only on backend. If frontend needs full create/update with canvas data, backend needs to accept those fields or frontend must always save canvas through versions.
- `/workspaces/{workspace}/workflows/build` is AI workflow generation, not validation. Frontend/backend should align the payload around natural language `description`.

## Workflow Related Pages

Purpose: these pages support workflow management outside the editor.

Frontend work enabled:

- Workflow listing and organization by folders/tags.
- Credential management for workflow nodes.
- Variables management.
- Webhook and polling trigger management.
- Execution dashboard, logs, retry/cancel/replay.

APIs needed:

```text
/workspaces/{workspace}/folders
/workspaces/{workspace}/folders/{folder}
/workspaces/{workspace}/folders/move-workflows

/workspaces/{workspace}/tags
/workspaces/{workspace}/tags/{tag}
/workspaces/{workspace}/tags/{tag}/workflows

/workspaces/{workspace}/credentials
/workspaces/{workspace}/credentials/{credential}
/workspaces/{workspace}/credentials/{credential}/test
/workspaces/{workspace}/credentials/{credential}/share

/credential-types
/credential-types/{credentialType}

/workspaces/{workspace}/variables
/workspaces/{workspace}/variables/{variable}

/workspaces/{workspace}/webhooks
/workspaces/{workspace}/webhooks/{webhook}

/workspaces/{workspace}/polling-triggers
/workspaces/{workspace}/polling-triggers/{pollingTrigger}

/workspaces/{workspace}/executions
/workspaces/{workspace}/executions/{execution}
/workspaces/{workspace}/executions/{execution}/logs
/workspaces/{workspace}/executions/{execution}/nodes
/workspaces/{workspace}/executions/{execution}/cancel
/workspaces/{workspace}/executions/{execution}/retry
/workspaces/{workspace}/executions/{execution}/replay
/workspaces/{workspace}/executions/stats
/workspaces/{workspace}/executions/compare
/workspaces/{workspace}/executions/bulk
```

Backend notes:

- Credential sharing should be aligned. Current backend uses `/credentials/{credential}/share`; old frontend code also references user-specific share URLs that are not registered.
- Webhook frontend fields should align with backend fields: backend uses `methods`, `auth_type`, `call_count`; old frontend types use `method`, `authentication`, `calls_count`.
- Polling trigger frontend fields should align with backend fields: backend returns `endpoint_url`, `http_method`, `headers`, `query_params`, `dedup_key`, etc.
- Execution statuses should align. Backend uses `pending`, `running`, `completed`, `failed`, `cancelled`, `waiting`.

## Other Main App Pages

Purpose: workspace/admin/product shell APIs used around the SaaS app.

Frontend work enabled:

- Workspace create/list/detail/update/delete.
- Workspace members and invitations.
- Workspace settings.
- Notifications and notification preferences.
- Notification channels.
- Credits and billing.
- Activity logs.
- Log streaming config.
- Git sync.
- Workflow templates.

APIs needed:

```text
/workspaces
/workspaces/{workspace}
/workspaces/{workspace}/members
/workspaces/{workspace}/members/{user}
/workspaces/{workspace}/invitations
/workspaces/{workspace}/invitations/{invitation}
/workspaces/{workspace}/settings
/workspaces/{workspace}/transfer-ownership
/workspaces/{workspace}/leave

/notifications
/notifications/unread-count
/notifications/read-all
/notifications/{notification}/read
/notifications/{notification}

/notification-preferences
/notification-channels
/notification-channels/{channel}
/notification-channels/{channel}/test

/workspaces/{workspace}/credits/balance
/workspaces/{workspace}/credits/transactions
/workspaces/{workspace}/billing/checkout
/workspaces/{workspace}/billing/credits
/workspaces/{workspace}/billing/portal

/workspaces/{workspace}/activity-logs
/workspaces/{workspace}/activity-logs/{activityLog}
/workspaces/{workspace}/activity-logs/export

/workspaces/{workspace}/log-streaming
/workspaces/{workspace}/log-streaming/{config}

/workspaces/{workspace}/git-sync/status
/workspaces/{workspace}/git-sync/export
/workspaces/{workspace}/git-sync/import

/templates
/templates/{template}
/workspaces/{workspace}/templates/{template}/use
```

Backend notes:

- These APIs mostly exist in backend routes.
- Sales, customer, products, projects, invoices, chat, and mail pages are not using real backend APIs yet.
- If those pages become product features, backend API contracts need to be defined separately.

## Agent Pages

Purpose: agent builder and agent management APIs.

Frontend work enabled:

- Create/list/update/delete agents.
- Duplicate agents.
- Attach/detach skills.
- Manage agent conversations and send messages.
- Manage and fire agent triggers.
- Manage reusable agent skills, references, and scripts.

APIs needed:

```text
/workspaces/{workspace}/agents
/workspaces/{workspace}/agents/{agent}
/workspaces/{workspace}/agents/{agent}/duplicate
/workspaces/{workspace}/agents/{agent}/skills/attach
/workspaces/{workspace}/agents/{agent}/skills/{skill}

/workspaces/{workspace}/agents/{agent}/conversations
/workspaces/{workspace}/agents/{agent}/conversations/{conversation}
/workspaces/{workspace}/agents/{agent}/conversations/{conversation}/messages

/workspaces/{workspace}/agents/{agent}/triggers
/workspaces/{workspace}/agents/{agent}/triggers/{trigger}
/workspaces/{workspace}/agents/{agent}/triggers/{trigger}/fire

/workspaces/{workspace}/agent-skills
/workspaces/{workspace}/agent-skills/{skill}
/workspaces/{workspace}/agent-skills/{skill}/references
/workspaces/{workspace}/agent-skills/{skill}/references/{reference}
/workspaces/{workspace}/agent-skills/{skill}/scripts
/workspaces/{workspace}/agent-skills/{skill}/scripts/{script}
```

Backend notes:

- These routes exist in backend.
- Frontend pages for these may still need wiring depending on release scope.

## Known Mismatches To Fix

```text
Wrong frontend URL:
/workspaces/{workspace}/workflows/test-node

Use backend URL:
/workspaces/{workspace}/nodes/sandbox
```

```text
Wrong/old frontend URL:
/workspaces/{workspace}/workflows/{workflow}/clone

Use backend URL:
/workspaces/{workspace}/workflows/{workflow}/duplicate
```

```text
Frontend references but backend route not found:
/workspaces/{workspace}/credentials/{credential}/refresh
/workspaces/{workspace}/credentials/{credential}/shares
/workspaces/{workspace}/credentials/{credential}/shares/{user}
/workspaces/{workspace}/credentials/{credential}/sharing-scope
/oauth/providers
/workspaces/{workspace}/oauth/authorize-url
/templates/featured
/templates/categories
/workspaces/{workspace}/dashboard
/workspaces/{workspace}/stats
/workspaces/{workspace}/variables/resolve/{name}
/workspaces/{workspace}/executions/{execution}/nodes/{node}
```

## Backend APIs Existing But Frontend May Still Need Wiring

```text
/workspaces/{workspace}/workflows/bulk-activate
/workspaces/{workspace}/workflows/bulk-deactivate
/workspaces/{workspace}/workflows/bulk
/workspaces/{workspace}/workflows/{workflow}/lock
/workspaces/{workspace}/workflows/{workflow}/unlock
/workspaces/{workspace}/workflows/{workflow}/contracts
/workspaces/{workspace}/workflows/{workflow}/contracts/generate
/workspaces/{workspace}/workflows/{workflow}/releases
/workspaces/{workspace}/executions/{execution}/pause
/workspaces/{workspace}/executions/{execution}/resume
/workspaces/{workspace}/executions/{execution}/autofix
/workspaces/{workspace}/approvals
/workspaces/{workspace}/environments
/workspaces/{workspace}/connector-metrics
```
