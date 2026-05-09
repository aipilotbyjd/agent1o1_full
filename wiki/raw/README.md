# raw/ — Source documents

**Immutable.** The LLM reads from these but never modifies them. This is the source of truth that backs every claim in the wiki.

---

## What goes here

Anything you want the wiki to accumulate from:

- **External**: articles, blog posts, papers, podcast transcripts, competitor docs (Gumloop, n8n, Zapier release notes / pricing pages / changelogs), customer interview transcripts
- **Internal**: product specs, design docs, ADRs, meeting notes, Slack thread exports, support tickets, screenshots
- **Code-derived snapshots**: `php artisan route:list` output, frontend module trees, schema dumps. These are valid sources because they freeze a fact-of-the-codebase at a point in time, with provenance.

Naming convention: `<topic>-<YYYY-MM-DD>.<ext>` so dates are visible in `ls`.

---

## Workflow

1. Drop a file here.
2. Tell the agent: *"ingest `raw/<file>`"*.
3. The agent reads it, summarises with you, then updates entity/concept pages and `index.md`, and appends to `log.md`.
4. The page's `sources:` count goes up. If it was `status: derived-from-code`, it flips to `status: sourced`.

---

## Don't

- Don't edit files here after they're added. If the source updated, drop the new version with a new date suffix and ingest it — the wiki preserves what each version said.
- Don't mix this folder with the wiki body. Pages live in `entities/`, `concepts/`, `tech/`, `references/`. This folder holds the *inputs*.
