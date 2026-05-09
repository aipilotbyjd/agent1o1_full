---
type: concept
status: sourced
sources: 2
last_updated: 2026-05-09
tags: [notifications, observability, integrations]
---

# Notifications

**TL;DR**: LinkFlow's notification system alerts users about execution failures and other events, via configurable channels (email, Slack, webhook, etc.) with per-user preferences and an in-app notification feed.

---

## Three Related Surfaces

### 1. NotificationChannel

A configured delivery destination for a workspace — e.g. "Slack #alerts", "email ops@company.com", "webhook https://...".

| Method | Path | Action |
|--------|------|--------|
| GET | `/workspaces/{id}/notification-channels` | List channels |
| POST | `/workspaces/{id}/notification-channels` | Create channel |
| PUT | `/workspaces/{id}/notification-channels/{id}` | Update channel |
| DELETE | `/workspaces/{id}/notification-channels/{id}` | Delete channel |
| POST | `/workspaces/{id}/notification-channels/{id}/test` | Send test notification |

### 2. NotificationPreference

Per-user / per-workspace preference controlling which events route to which channels.

| Method | Path | Action |
|--------|------|--------|
| GET | `/workspaces/{id}/notification-preferences` | Get preferences |
| PUT | `/workspaces/{id}/notification-preferences` | Update preferences |

### 3. Notification (in-app feed)

User-scoped in-app notifications — these routes are **not** workspace-scoped, they're top-level:

| Method | Path | Action |
|--------|------|--------|
| GET | `/notifications` | List notifications |
| GET | `/notifications/unread-count` | Get unread count |
| POST | `/notifications/read-all` | Mark all read |
| DELETE | `/notifications` | Clear all |
| POST | `/notifications/{id}/read` | Mark one read |
| DELETE | `/notifications/{id}` | Delete one |

---

## Known Event Types

- `ExecutionFailedNotification` — sent when a workflow execution fails
- Other likely events: member invited, workflow activated/deactivated, credit balance low, credential expired

---

## Relationship to [[concepts/execution-engine]]

The execution engine sends `ExecutionFailedNotification` after a workflow fails. The notification system routes it to the configured channels based on the workspace's `NotificationPreference` settings.

---

## Sources

- `raw/api-routes-2026-05-09.txt` — confirms `notification-channels` CRUD + test, `notification-preferences` get/update, and top-level `notifications` feed routes
- `raw/frontend-api-modules-2026-05-09.txt` — confirms `notifications/`, `notification-channels/`, `notification-preferences/` as separate API modules
- *(no external sources yet — flag: supported channel types, notification event taxonomy, preference schema)*
