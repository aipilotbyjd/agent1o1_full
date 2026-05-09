# Execution Engine

**TL;DR**: The backend system that runs workflows — processes nodes in order, passes data between them, retries on failure, and logs everything.

---

## How an Execution Starts

Triggers (any one of these creates an `Execution` record and dispatches a job):
1. **Manual** — user clicks Run in the editor or calls the execute API
2. **Webhook** — external service POSTs to `/webhooks/{token}`
3. **Scheduled** — `workflows:schedule-cron` runs every minute, queues due workflows
4. **Polling** — `workflows:poll` runs every minute, checks `PollingTrigger` records for new data
5. **Test mode** — from the editor, uses pinned data, skips side effects

## Execution Flow

```
Trigger → Create Execution (status: pending)
        → Dispatch job to Horizon queue
        → Job: mark running, iterate nodes in topological order
              → For each node:
                  - Create ExecutionNode (status: running)
                  - Call node handler with input data
                  - On success: mark completed, pass output to downstream nodes
                  - On failure: retry if configured, else mark failed
        → Mark Execution completed/failed
        → Send failure notification if configured
```

## Queue

Uses **Laravel Horizon** (Redis-backed). Execution jobs run on a dedicated queue. Horizon dashboard provides real-time monitoring.

## Data Flow Between Nodes

Each node receives a data object and produces a data object. The connection graph determines which output goes to which node's input. The engine resolves `{{ expressions }}` in node parameters using the accumulated output from upstream nodes and workspace [[variable]]s.

## Error Handling

- Per-workflow `max_retries` and `retry_on_failure` settings
- `error_workflow_id` — a separate workflow that runs when this one fails (for custom error handling)
- `ExecutionRunbook` — AI-generated step-by-step fix guide after failure
- `AiFixSuggestion` — AI suggests the most likely fix

## Checkpointing

`ExecutionCheckpoint` records let long-running executions resume from a known point (e.g. after a crash or restart).

## Notifications

`ExecutionFailedNotification` mail is sent on failure. `NotificationPreference` and `NotificationChannel` control delivery (email, Slack, webhook, etc.).

## Archiving

- Recent logs: `ExecutionLog`
- Archived logs (old executions): `ArchivedExecutionLog` (moved nightly to keep main table lean)

## Log Streaming

`LogStreamingConfig` — some users want real-time log streaming to external systems (Datadog, Papertrail, etc.). Config stored per workspace.

## Credits

Executions consume credits from the workspace's credit balance. Usage is tracked in `ConnectorCallAttempt` and aggregated in `ConnectorMetricDaily` and `UsageDailySnapshot`.
