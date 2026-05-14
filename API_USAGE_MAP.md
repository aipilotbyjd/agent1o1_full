# API Usage Map For Backend Param Check

Base URL: `/api/v1`

This file maps each API to the frontend place/feature where we plan to use it. Backend can use this to verify request params, query params, response shape, and permissions.

## Workflow Editor

| API | Planned frontend use |
|---|---|
| `POST /auth/login` | Login page before entering app/editor. |
| `POST /auth/register` | Signup page before entering app/editor. |
| `POST /auth/logout` | Logout button from app shell/sidebar. |
| `POST /auth/refresh` | Auto refresh access token on expired session. |
| `GET /user` | Load current user for auth state, header/sidebar, protected app state. |
| `GET /node-categories` | Workflow editor left node library, grouped categories with nodes. |
| `GET /node-categories/{category}` | Category detail if user opens a category-specific node browser/detail panel. |
| `GET /nodes` | Node catalog fallback/search/list. |
| `GET /nodes/{node}` | Node detail/docs/config schema in editor inspector. |
| `GET /workspaces/{workspace}/workflows` | Workflow list page and workflow picker/sidebar. |
| `POST /workspaces/{workspace}/workflows` | Create a new workflow shell before saving versions. |
| `GET /workspaces/{workspace}/workflows/{workflow}` | Open existing workflow in editor. |
| `PUT /workspaces/{workspace}/workflows/{workflow}` | Update workflow metadata like name, description, icon, color, active/favorite if supported. |
| `DELETE /workspaces/{workspace}/workflows/{workflow}` | Delete workflow from workflow list/settings. |
| `GET /workspaces/{workspace}/workflows/{workflow}/versions` | Load version history and choose current/published version for editor canvas. |
| `POST /workspaces/{workspace}/workflows/{workflow}/versions` | Save editor canvas as a new workflow version. |
| `GET /workspaces/{workspace}/workflows/{workflow}/versions/{version}` | Open a specific saved version. |
| `POST /workspaces/{workspace}/workflows/{workflow}/versions/{version}/publish` | Publish selected saved version from editor topbar. |
| `POST /workspaces/{workspace}/workflows/{workflow}/versions/{version}/rollback` | Roll back workflow to an older version. |
| `GET /workspaces/{workspace}/workflows/{workflow}/versions/diff` | Version comparison screen/modal. |
| `POST /workspaces/{workspace}/workflows/{workflow}/execute` | Run button in workflow editor. |
| `POST /workspaces/{workspace}/nodes/sandbox` | Test one node from node inspector without running whole workflow. |
| `POST /workspaces/{workspace}/workflows/build` | AI prompt-to-workflow builder. |
| `POST /workspaces/{workspace}/workflows/{workflow}/duplicate` | Duplicate/clone workflow action. |
| `POST /workspaces/{workspace}/workflows/{workflow}/activate` | Turn workflow on from editor/list. |
| `POST /workspaces/{workspace}/workflows/{workflow}/deactivate` | Turn workflow off from editor/list. |
| `GET /workspaces/{workspace}/workflows/{workflow}/export` | Export workflow JSON. |
| `POST /workspaces/{workspace}/workflows/import` | Import workflow JSON. |
| `GET /workspaces/{workspace}/workflows/{workflow}/pinned-data` | Load pinned test data for nodes. |
| `POST /workspaces/{workspace}/workflows/{workflow}/pinned-data` | Pin node output/input data for repeat testing. |
| `DELETE /workspaces/{workspace}/workflows/{workflow}/pinned-data/{pinnedData}` | Remove pinned data. |
| `POST /workspaces/{workspace}/workflows/{workflow}/pinned-data/{pinnedData}/toggle` | Enable/disable pinned data. |
| `POST /workspaces/{workspace}/workflows/{workflow}/webhook` | Create webhook trigger from trigger node/editor action. |
| `POST /workspaces/{workspace}/workflows/{workflow}/polling-trigger` | Create polling trigger from trigger node/editor action. |
| `GET /workspaces/{workspace}/workflows/{workflow}/executions` | Show execution history for the selected workflow. |
| `GET /workspaces/{workspace}/workflows/{workflow}/shares` | Share modal: list existing share links. |
| `POST /workspaces/{workspace}/workflows/{workflow}/shares` | Create public/private share link. |
| `PUT /workspaces/{workspace}/workflows/{workflow}/shares/{share}` | Update share permissions/expiry. |
| `DELETE /workspaces/{workspace}/workflows/{workflow}/shares/{share}` | Revoke share link. |
| `GET /shared/{token}` | Public shared workflow view. |
| `POST /workspaces/{workspace}/shared/{token}/clone` | Clone public shared workflow into user's workspace. |

## Workflow Management Pages

| API | Planned frontend use |
|---|---|
| `GET /workspaces/{workspace}/folders` | Workflow sidebar/list folder tree. |
| `POST /workspaces/{workspace}/folders` | Create workflow folder. |
| `GET /workspaces/{workspace}/folders/{folder}` | Folder detail page/panel. |
| `PUT /workspaces/{workspace}/folders/{folder}` | Rename/update folder. |
| `DELETE /workspaces/{workspace}/folders/{folder}` | Delete folder. |
| `POST /workspaces/{workspace}/folders/move-workflows` | Move selected workflows into folder/root. |
| `GET /workspaces/{workspace}/tags` | Workflow filter/tag manager. |
| `POST /workspaces/{workspace}/tags` | Create tag. |
| `GET /workspaces/{workspace}/tags/{tag}` | Tag detail. |
| `PUT /workspaces/{workspace}/tags/{tag}` | Update tag name/color. |
| `DELETE /workspaces/{workspace}/tags/{tag}` | Delete tag. |
| `POST /workspaces/{workspace}/tags/{tag}/workflows` | Attach workflows to tag. |
| `DELETE /workspaces/{workspace}/tags/{tag}/workflows` | Detach workflows from tag. |
| `GET /workspaces/{workspace}/credentials` | Credential manager and node credential dropdown. |
| `POST /workspaces/{workspace}/credentials` | Create credential. |
| `GET /workspaces/{workspace}/credentials/{credential}` | Credential detail/edit form. |
| `PUT /workspaces/{workspace}/credentials/{credential}` | Update credential. |
| `DELETE /workspaces/{workspace}/credentials/{credential}` | Delete credential. |
| `POST /workspaces/{workspace}/credentials/{credential}/test` | Test credential connection. |
| `POST /workspaces/{workspace}/credentials/{credential}/share` | Share/unshare credential for workflow use. |
| `GET /credential-types` | Credential type picker. |
| `GET /credential-types/{credentialType}` | Credential type schema/details. |
| `GET /workspaces/{workspace}/variables` | Variables page and editor variable picker. |
| `POST /workspaces/{workspace}/variables` | Create variable. |
| `GET /workspaces/{workspace}/variables/{variable}` | Variable detail. |
| `PUT /workspaces/{workspace}/variables/{variable}` | Update variable. |
| `DELETE /workspaces/{workspace}/variables/{variable}` | Delete variable. |
| `GET /workspaces/{workspace}/webhooks` | Webhook management page. |
| `GET /workspaces/{workspace}/webhooks/{webhook}` | Webhook detail. |
| `PUT /workspaces/{workspace}/webhooks/{webhook}` | Update webhook config. |
| `DELETE /workspaces/{workspace}/webhooks/{webhook}` | Delete webhook. |
| `GET /workspaces/{workspace}/polling-triggers` | Polling trigger management page. |
| `GET /workspaces/{workspace}/polling-triggers/{pollingTrigger}` | Polling trigger detail. |
| `PUT /workspaces/{workspace}/polling-triggers/{pollingTrigger}` | Update polling trigger config/status. |
| `DELETE /workspaces/{workspace}/polling-triggers/{pollingTrigger}` | Delete polling trigger. |
| `GET /workspaces/{workspace}/executions` | Execution dashboard list. |
| `GET /workspaces/{workspace}/executions/{execution}` | Execution detail page. |
| `DELETE /workspaces/{workspace}/executions/{execution}` | Delete execution record. |
| `GET /workspaces/{workspace}/executions/{execution}/logs` | Execution logs panel. |
| `GET /workspaces/{workspace}/executions/{execution}/nodes` | Per-node execution details. |
| `POST /workspaces/{workspace}/executions/{execution}/cancel` | Cancel running execution. |
| `POST /workspaces/{workspace}/executions/{execution}/retry` | Retry failed execution. |
| `POST /workspaces/{workspace}/executions/{execution}/replay` | Replay execution. |
| `GET /workspaces/{workspace}/executions/stats` | Execution dashboard stats cards. |
| `GET /workspaces/{workspace}/executions/compare` | Compare two executions. |
| `DELETE /workspaces/{workspace}/executions/bulk` | Bulk delete executions. |

## Workspace And Admin Pages

| API | Planned frontend use |
|---|---|
| `GET /workspaces` | Workspace switcher/list. |
| `POST /workspaces` | Create workspace. |
| `GET /workspaces/{workspace}` | Workspace detail/settings shell. |
| `PUT /workspaces/{workspace}` | Update workspace info. |
| `DELETE /workspaces/{workspace}` | Delete workspace. |
| `GET /workspaces/{workspace}/members` | Members settings page. |
| `PUT /workspaces/{workspace}/members/{user}` | Change member role. |
| `DELETE /workspaces/{workspace}/members/{user}` | Remove member. |
| `GET /workspaces/{workspace}/invitations` | Invitations list. |
| `POST /workspaces/{workspace}/invitations` | Send invitation. |
| `DELETE /workspaces/{workspace}/invitations/{invitation}` | Cancel invitation. |
| `GET /workspaces/{workspace}/settings` | Workspace settings page. |
| `PUT /workspaces/{workspace}/settings` | Save workspace settings. |
| `POST /workspaces/{workspace}/transfer-ownership` | Transfer workspace ownership. |
| `POST /workspaces/{workspace}/leave` | Leave workspace. |
| `GET /notifications` | Notification center/list. |
| `GET /notifications/unread-count` | Header unread badge. |
| `POST /notifications/read-all` | Mark all notifications read. |
| `POST /notifications/{notification}/read` | Mark one notification read. |
| `DELETE /notifications/{notification}` | Delete one notification. |
| `DELETE /notifications` | Delete all notifications. |
| `GET /notification-preferences` | Notification preference settings. |
| `PUT /notification-preferences` | Save notification preferences. |
| `GET /notification-channels` | Notification channel settings. |
| `POST /notification-channels` | Create notification channel. |
| `PUT /notification-channels/{channel}` | Update notification channel. |
| `DELETE /notification-channels/{channel}` | Delete notification channel. |
| `POST /notification-channels/{channel}/test` | Send test notification. |
| `GET /workspaces/{workspace}/credits/balance` | Billing/credits balance widget. |
| `GET /workspaces/{workspace}/credits/transactions` | Credit transaction history. |
| `POST /workspaces/{workspace}/billing/checkout` | Start subscription checkout. |
| `POST /workspaces/{workspace}/billing/credits` | Buy credits. |
| `GET /workspaces/{workspace}/billing/portal` | Open billing portal. |
| `GET /workspaces/{workspace}/activity-logs` | Audit/activity log page. |
| `GET /workspaces/{workspace}/activity-logs/{activityLog}` | Activity log detail. |
| `GET /workspaces/{workspace}/activity-logs/export` | Export activity logs. |
| `GET /workspaces/{workspace}/log-streaming` | Log streaming config list. |
| `POST /workspaces/{workspace}/log-streaming` | Create log streaming config. |
| `GET /workspaces/{workspace}/log-streaming/{config}` | Log streaming detail. |
| `PUT /workspaces/{workspace}/log-streaming/{config}` | Update log streaming config. |
| `DELETE /workspaces/{workspace}/log-streaming/{config}` | Delete log streaming config. |
| `GET /workspaces/{workspace}/git-sync/status` | Git sync settings/status. |
| `POST /workspaces/{workspace}/git-sync/export` | Export workflows to git sync payload. |
| `POST /workspaces/{workspace}/git-sync/import` | Import workflows from git sync payload. |
| `GET /templates` | Template gallery. |
| `GET /templates/{template}` | Template detail/preview. |
| `POST /workspaces/{workspace}/templates/{template}/use` | Create workflow from template. |

## Agent Pages

| API | Planned frontend use |
|---|---|
| `GET /workspaces/{workspace}/agents` | Agent list page. |
| `POST /workspaces/{workspace}/agents` | Create agent. |
| `GET /workspaces/{workspace}/agents/{agent}` | Agent detail/builder. |
| `PUT /workspaces/{workspace}/agents/{agent}` | Update agent config. |
| `DELETE /workspaces/{workspace}/agents/{agent}` | Delete agent. |
| `POST /workspaces/{workspace}/agents/{agent}/duplicate` | Duplicate agent. |
| `POST /workspaces/{workspace}/agents/{agent}/skills/attach` | Attach skill to agent. |
| `DELETE /workspaces/{workspace}/agents/{agent}/skills/{skill}` | Detach skill from agent. |
| `GET /workspaces/{workspace}/agents/{agent}/conversations` | Agent conversation list. |
| `POST /workspaces/{workspace}/agents/{agent}/conversations` | Start new conversation. |
| `GET /workspaces/{workspace}/agents/{agent}/conversations/{conversation}` | Conversation detail. |
| `DELETE /workspaces/{workspace}/agents/{agent}/conversations/{conversation}` | Delete conversation. |
| `POST /workspaces/{workspace}/agents/{agent}/conversations/{conversation}/messages` | Send message to agent. |
| `GET /workspaces/{workspace}/agents/{agent}/triggers` | Agent trigger list. |
| `POST /workspaces/{workspace}/agents/{agent}/triggers` | Create agent trigger. |
| `PUT /workspaces/{workspace}/agents/{agent}/triggers/{trigger}` | Update agent trigger. |
| `DELETE /workspaces/{workspace}/agents/{agent}/triggers/{trigger}` | Delete agent trigger. |
| `POST /workspaces/{workspace}/agents/{agent}/triggers/{trigger}/fire` | Manually fire/test trigger. |
| `GET /workspaces/{workspace}/agent-skills` | Agent skill library. |
| `POST /workspaces/{workspace}/agent-skills` | Create skill. |
| `GET /workspaces/{workspace}/agent-skills/{skill}` | Skill detail. |
| `PUT /workspaces/{workspace}/agent-skills/{skill}` | Update skill. |
| `DELETE /workspaces/{workspace}/agent-skills/{skill}` | Delete skill. |
| `POST /workspaces/{workspace}/agent-skills/{skill}/references` | Add skill reference. |
| `PUT /workspaces/{workspace}/agent-skills/{skill}/references/{reference}` | Update skill reference. |
| `DELETE /workspaces/{workspace}/agent-skills/{skill}/references/{reference}` | Remove skill reference. |
| `POST /workspaces/{workspace}/agent-skills/{skill}/scripts` | Add skill script. |
| `PUT /workspaces/{workspace}/agent-skills/{skill}/scripts/{script}` | Update skill script. |
| `DELETE /workspaces/{workspace}/agent-skills/{skill}/scripts/{script}` | Remove skill script. |

## Static/Demo Pages For Now

These pages currently use mostly static frontend data. Backend APIs are not finalized in the current codebase:

```text
Sales
Customers
Products
Projects
Invoices
Chat
Mail
```

## Mismatches Backend Should Check

| Frontend/old expectation | Backend route to use or action |
|---|---|
| `/workspaces/{workspace}/workflows/test-node` | Use `POST /workspaces/{workspace}/nodes/sandbox`. |
| `/workspaces/{workspace}/workflows/{workflow}/clone` | Use `POST /workspaces/{workspace}/workflows/{workflow}/duplicate`. |
| `/workspaces/{workspace}/credentials/{credential}/refresh` | Backend route not found. Confirm if needed. |
| `/workspaces/{workspace}/credentials/{credential}/shares` | Backend route not found. Current backend has `/share` with `is_shared`. |
| `/oauth/providers` | Backend route not found. Confirm OAuth provider list requirement. |
| `/workspaces/{workspace}/oauth/authorize-url` | Backend route not found. Current backend has `/workspaces/{workspace}/oauth/initiate`. |
| `/templates/featured` | Backend route not found. Confirm template gallery requirement. |
| `/templates/categories` | Backend route not found. Confirm template gallery requirement. |
| `/workspaces/{workspace}/dashboard` | Backend route not found. Confirm dashboard scope. |
| `/workspaces/{workspace}/stats` | Backend route not found. Confirm dashboard scope. |
| `/workspaces/{workspace}/variables/resolve/{name}` | Backend route not found. Confirm if editor variable resolution needs this. |
| `/workspaces/{workspace}/executions/{execution}/nodes/{node}` | Backend route not found. Current backend has all nodes at `/executions/{execution}/nodes`. |
