# Execution

**TL;DR**: One run of a workflow — tracks overall status, timing, and per-node results.

---

## Key Fields

| Field | Type | Notes |
|-------|------|-------|
| `id` | UUID | |
| `workflow_id` | FK | |
| `workspace_id` | FK | |
| `status` | enum | `pending`, `running`, `completed`, `failed`, `cancelled` |
| `mode` | enum | `manual`, `trigger`, `scheduled`, `test` — see `ExecutionMode` |
| `trigger_data` | JSON | Data that triggered the run |
| `input_data` | JSON | Manual input provided |
| `started_at` | timestamp | |
| `finished_at` | timestamp? | |
| `duration_ms` | int? | |
| `error` | string? | Top-level error message |

## Relationships

- belongs to `Workflow`
- belongs to `Workspace`
- has many `ExecutionNode` (per-node results)
- has many `ExecutionLog` (raw log lines)
- has many `ArchivedExecutionLog` (old logs moved off main table)
- has one `ExecutionRunbook` (AI-generated remediation)
- has many `ExecutionCheckpoint` (resume points)
- has one `ExecutionReplayPack` (data needed to replay)

## ExecutionNode

Each node in the workflow gets an `ExecutionNode` record:

| Field | Notes |
|-------|-------|
| `node_id` | References `IWorkflowNode.id` in the workflow JSON |
| `status` | `pending`, `running`, `success`, `error`, `skipped` — see `ExecutionNodeStatus` |
| `input` | Data passed in |
| `output` | Data produced |
| `error` | Error message if failed |
| `started_at`, `finished_at` | Timing |
| `retry_count` | How many retries were attempted |

## Execution Modes

From `ExecutionMode` enum: `manual`, `trigger`, `scheduled`, `test`. Test mode runs without side effects.

## Pruning & Archiving

The scheduler runs:
- `executions:prune` daily at 02:00 — deletes old executions
- `executions:archive --batch-size=500` daily at 02:30 — moves logs to archive table

## AI Features on Executions

- `AiFixSuggestion` — AI suggests how to fix a failed execution
- `ExecutionRunbook` — AI-generated step-by-step remediation guide
- `AiGenerationLog` — tracks AI usage during execution

## API

| Method | Path | Action |
|--------|------|--------|
| GET | `/workspaces/{id}/executions` | List |
| GET | `/workspaces/{id}/executions/{id}` | Get with node details |
| DELETE | `/workspaces/{id}/executions/{id}` | Cancel/delete |
| GET | `/workspaces/{id}/workflows/{id}/executions` | Executions for a workflow |
| POST | `/workspaces/{id}/workflows/{id}/execute` | Trigger a run |

Model: `backend/app/Models/Execution.php`, `ExecutionNode.php`
Frontend types: `frontend/src/types/execution.type.ts`
API module: `frontend/src/api/modules/executions/`
