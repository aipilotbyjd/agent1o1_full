# LinkFlow Wiki — Log

Append-only chronological record of wiki operations.

Parse last 5 entries: `grep "^## \[" log.md | tail -5`

---

## [2026-05-09] create | Wiki initialised

Initial wiki structure created by Claude Code from codebase exploration.

**Pages created:**
- `overview.md`
- `entities/workspace.md`
- `entities/workflow.md`
- `entities/node.md`
- `entities/execution.md`
- `entities/credential.md`
- `entities/webhook.md`
- `entities/agent.md`
- `entities/template.md`
- `entities/variable.md`
- `concepts/workflow-builder.md`
- `concepts/execution-engine.md`
- `concepts/billing-credits.md`
- `concepts/teams-and-permissions.md`
- `tech/backend.md`
- `tech/frontend.md`
- `references/competitors.md`

**Source**: Derived from codebase — `backend/app/Models/`, `backend/routes/api.php`, `frontend/src/types/`, `frontend/src/api/modules/`, `frontend/package.json`.

**Schema**: `CLAUDE.md` created at project root.

---

## [2026-05-09] update | Frontend API parity audit + 13 new modules

**Pages affected**: none directly (code-only change). Filed as a query-output page on the same date.

**What happened**: Audited every backend route in `backend/routes/api.php` against `frontend/src/api/modules/`. Closed 13 module-level gaps and 15+ endpoint-level gaps in existing modules.

**New frontend modules** (each with `endpoints/service/hooks/keys/index.ts`):
notifications, notification-preferences, notification-channels, invitations (token-based), polling-triggers, archived-executions, activity-logs, credits, billing, log-streaming, git-sync, credential-types.

**Patched modules**: auth (resend-verification, verify-email, delete-account), executions (replay, single+bulk delete, compare, stats, nodes, SSE URL builders), workflows (createWebhook, createPollingTrigger), agents (full conversations, triggers, skill references, skill scripts), node-types (categoryDetail).

**New types files**: notification, invitation, pollingTrigger, activityLog, credit, billing, logStreaming, gitSync, credentialType. Extended agent.type.ts.

**Verify**: `npx tsc --noEmit -p tsconfig.app.json` clean for all new files; pre-existing WIP errors in user's mid-edit `webhooks/templates/variables/credentials/editor` hooks remain (out of scope for this audit).

---

## [2026-05-09] lint | Realign wiki to Karpathy LLM-wiki pattern

**Trigger**: User confirmed the wiki follows Karpathy's LLM Wiki pattern. Previous lint was wrong-shaped (suggested completionist entity pages — that's anti-pattern under source-driven accumulation).

**Findings**:
- `raw/` was empty — no source-of-truth, no provenance for any claim
- Pages had no frontmatter, no Sources section — couldn't lint for stale claims
- Today's API audit conversation was at risk of disappearing into chat history (good answers should compound, per spec)
- `references/competitors.md` is a hub but had nothing feeding it

**Fixes applied**:
- `CLAUDE.md` schema tightened: added frontmatter convention (`type`, `status`, `sources`, `last_updated`, `tags`), required `## Sources` section, source-driven creation guardrail, query-output filing in operations
- All 17 existing pages got frontmatter + Sources section. 15 marked `status: derived-from-code`, 1 hub (competitors), 1 new query-output (api-coverage)
- `raw/` seeded with `README.md`, `api-routes-2026-05-09.txt` (`php artisan route:list` output), `frontend-api-modules-2026-05-09.txt` (module file tree)
- `concepts/api-coverage.md` created as a `query-output` page filing today's audit
- `index.md` rewritten: status column, source counts, raw-sources table, "wanted next" ingest queue, health snapshot

**Pages still wanting source backing**: 15 entity/concept/tech pages flagged `status: derived-from-code`. These aren't broken — they just need real sources (specs, articles, customer notes) ingested before they earn `status: sourced`.

**Suggested next ingests** (ranked):
1. Competitor pricing/changelog pages → unsticks `competitors` hub
2. Internal pricing decision doc → backs `billing-credits`
3. Customer interviews / support tickets → drives new pages organically
4. Boltify theme docs → backs `frontend`
5. Queue/deployment notes → backs `backend`

---

## [2026-05-09] ingest | api-routes-2026-05-09.txt + frontend-api-modules-2026-05-09.txt

**Source files ingested:**
- `raw/api-routes-2026-05-09.txt` — `php artisan route:list` dump, 190 routes
- `raw/frontend-api-modules-2026-05-09.txt` — frontend API module file tree, 27 modules

**Status flips** (derived-from-code → sourced): all 16 pre-existing pages now carry both raw files as sources.

**Sources count updates**: all pages updated from `sources: 1` → `sources: 2` (both raw files apply to every entity/concept/tech page).

**Pages updated** (content expanded):
- `entities/workspace.md` — added full API table including git-sync, log-streaming, activity-logs, settings, transfer-ownership, leave
- `entities/workflow.md` — expanded API table to all 34 workflow-related endpoints (versions, shares, sticky-notes, pinned-data, polling-trigger, build, activate, deactivate, export, shared)
- `entities/execution.md` — expanded API table to all execution endpoints (stats, compare, stream-all, bulk-delete, cancel, logs, nodes, replay, retry, stream, archived lifecycle)
- `entities/credential.md` — fixed OAuth endpoint to `workspaces/{id}/oauth/initiate`; added credential test endpoint; added credential-types `/{id}` show route
- `entities/webhook.md` — corrected API table (no POST create — webhooks created programmatically); added webhook-wait route
- `entities/agent.md` — added full API table (28 endpoints) covering CRUD, conversations, triggers, agent-skills workspace CRUD with references and scripts
- `entities/node.md` — added note about frontend naming mismatch (`node-types` module vs `nodes` routes)
- `entities/template.md` — corrected API table to match actual routes (`/use` not `/fork`)
- `tech/backend.md` — added `billing:expire-credit-packs` and `billing:reset-monthly-credits` to scheduled commands table
- `tech/frontend.md` — corrected API module pattern (endpoints/service/hooks/keys, not queries/mutations); added full 27-module inventory
- `overview.md` — added 4 new core concepts to list; added 5 new key product areas (Folders & Tags, Activity Logs, Notifications, Git Sync, Log Streaming)
- All concept pages — sources section updated

**New pages created** (6):
- `entities/folder.md` — workflow organization; `move-workflows` bulk endpoint
- `entities/tag.md` — many-to-many workflow labels; attach/detach endpoints
- `entities/activity-log.md` — immutable audit trail, read-only API, export endpoint
- `concepts/notifications.md` — notification-channels, notification-preferences, in-app notification feed
- `concepts/git-sync.md` — export/import to git repo + public webhook for auto-sync
- `concepts/log-streaming.md` — LogStreamingConfig CRUD for forwarding logs to external systems

**Index updated**: 23 total pages; all `C` statuses flipped to `S`; 3 new entity rows + 3 new concept rows added; health snapshot updated.
