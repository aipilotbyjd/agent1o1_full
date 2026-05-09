# LinkFlow Wiki — Index

Master catalog. Updated on every ingest or page creation. The LLM reads this first when answering queries.

**Status legend** — `S` sourced · `C` derived-from-code (needs source backing) · `H` hub · `Q` query-output

---

## Overview

| Page | Summary | Status | Sources |
|------|---------|--------|---------|
| [overview](overview.md) | Product overview, stack at a glance, key product areas | S | 2 |

---

## Entities

| Page | Summary | Status | Sources |
|------|---------|--------|---------|
| [workspace](entities/workspace.md) | Top-level multi-tenant container | S | 2 |
| [workflow](entities/workflow.md) | The automation: versioned graph of nodes and connections | S | 2 |
| [node](entities/node.md) | Node type registry — what steps are available | S | 2 |
| [execution](entities/execution.md) | One run of a workflow with per-node results | S | 2 |
| [credential](entities/credential.md) | Encrypted storage for third-party auth secrets | S | 2 |
| [webhook](entities/webhook.md) | Inbound HTTP endpoints that trigger workflows | S | 2 |
| [agent](entities/agent.md) | AI agent as a first-class workflow participant | S | 2 |
| [template](entities/template.md) | Pre-built workflow blueprints users can fork | S | 2 |
| [variable](entities/variable.md) | Workspace-level key-value store for shared config | S | 2 |
| [folder](entities/folder.md) | Organizes workflows into named groups within a workspace | S | 2 |
| [tag](entities/tag.md) | Many-to-many labels applied to workflows for filtering | S | 2 |
| [activity-log](entities/activity-log.md) | Immutable audit trail of workspace actions | S | 2 |

---

## Concepts

| Page | Summary | Status | Sources |
|------|---------|--------|---------|
| [workflow-builder](concepts/workflow-builder.md) | Visual canvas editor — how the UI works | S | 2 |
| [execution-engine](concepts/execution-engine.md) | How workflows are run, queued, and logged | S | 2 |
| [billing-credits](concepts/billing-credits.md) | Plans, credit packs, usage metering | S | 2 |
| [teams-and-permissions](concepts/teams-and-permissions.md) | Roles, workspace members, invitation flow | S | 2 |
| [notifications](concepts/notifications.md) | In-app feed + channel-based alerts for executions and events | S | 2 |
| [git-sync](concepts/git-sync.md) | Push/pull workflow definitions to a git repository | S | 2 |
| [log-streaming](concepts/log-streaming.md) | Forward execution logs to Datadog, Papertrail, custom webhooks | S | 2 |
| [api-coverage](concepts/api-coverage.md) | Backend ↔ frontend API mapping (snapshot 2026-05-09) | Q | 2 |

---

## Tech

| Page | Summary | Status | Sources |
|------|---------|--------|---------|
| [backend](tech/backend.md) | Laravel 12 stack, packages, conventions | S | 2 |
| [frontend](tech/frontend.md) | React 19 + Boltify theme, key libraries | S | 2 |

---

## References

| Page | Summary | Status | Sources |
|------|---------|--------|---------|
| [competitors](references/competitors.md) | Gumloop, n8n, Zapier analysis and positioning table | H | 0 |

---

## Raw Sources

| File | Type | Date |
|------|------|------|
| [api-routes-2026-05-09.txt](raw/api-routes-2026-05-09.txt) | Backend route dump | 2026-05-09 |
| [frontend-api-modules-2026-05-09.txt](raw/frontend-api-modules-2026-05-09.txt) | Frontend API module tree | 2026-05-09 |
| [README.md](raw/README.md) | Folder convention guide | — |

**Wanted next** (suggested ingests, ordered by lint priority):
1. Gumloop / n8n / Zapier pricing pages → updates [competitors](references/competitors.md)
2. Internal pricing/credit-cost decision doc → backs [billing-credits](concepts/billing-credits.md)
3. Customer interview transcripts → seeds new pages, drives roadmap
4. Boltify theme docs → backs [frontend](tech/frontend.md)
5. Queue/deployment/infra notes → backs [backend](tech/backend.md)

---

## Health snapshot

- **23 pages** total (1 overview, 12 entities, 8 concepts, 2 tech, 1 reference) + this index
- **22 pages** are `sourced` — backed by ≥1 raw file
- **1 page** is a `query-output` (api-coverage)
- **1 page** is a `hub` (competitors) — starved, no external competitor sources ingested yet
- **3 files** in `raw/` (2 code-snapshots + 1 README)

Last full ingest: 2026-05-09 — both raw sources processed, all pre-existing pages flipped to `sourced`, 6 new pages created (folder, tag, activity-log, notifications, git-sync, log-streaming).
