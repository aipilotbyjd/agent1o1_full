# LinkFlow — Wiki Schema

This is the schema document for the LinkFlow LLM Wiki. It tells Claude how the wiki is structured, what conventions to follow, and what workflows to use for each operation.

---

## Project

**LinkFlow** is a SaaS workflow automation builder — a spiritual clone of Gumloop, n8n, and Zapier. Users visually connect nodes on a canvas to automate tasks across services.

- **Backend**: Laravel 12 / PHP 8.5 — lives in `backend/`
- **Frontend**: React 19 + TypeScript + Vite, Boltify theme — lives in `frontend/`
- **Wiki**: LLM-maintained knowledge base — lives in `wiki/`

---

## Wiki Layout

```
wiki/
├── raw/                  # Immutable source documents — LLM reads, never modifies
├── index.md              # Master catalog — updated on every ingest or page creation
├── log.md                # Append-only chronological log
├── overview.md           # High-level product overview
├── entities/             # One page per backend model / domain entity
├── concepts/             # Cross-cutting concepts and subsystems
├── tech/                 # Technology stack and infrastructure
└── references/           # Competitor analysis, external research
```

---

## Page Conventions

Every wiki page has three required parts:

1. **YAML frontmatter** — machine-readable metadata
2. **`# Title` + one-line TL;DR** — the human/LLM hook
3. **`## Sources` section at the bottom** — provenance for every claim

### Frontmatter

```yaml
---
type: entity | concept | tech | reference | overview | query-output
status: sourced | derived-from-code | stale | hub
sources: <count of raw/ files this page draws on>
last_updated: YYYY-MM-DD
tags: [list, of, tags]
---
```

`status` values:
- **sourced** — backed by ≥1 file in `raw/`
- **derived-from-code** — bootstrapped from the codebase before any source was ingested; flag for re-grounding when sources arrive
- **stale** — newer source contradicts or supersedes; needs revision
- **hub** — aggregator page that re-summarises whenever new sources arrive (e.g. `references/competitors.md`)

### Body

- All wiki pages are markdown.
- Use `[[PageName]]` for wiki cross-references (Obsidian-style wikilinks).
- Entity pages: cover purpose, key fields, relationships, and notable behaviour.
- Concept pages: cover what it is, how it works, open questions.
- Tech pages: cover the tool, version, why it was chosen, key config.
- Query-output pages: file the answer + the question that produced it + date.

### Sources section

Every page ends with:

```
## Sources

- `raw/<file>` — what was drawn from it
- `backend/app/Models/Foo.php` — code reference (if no raw source yet)
- *(none — derived from code on YYYY-MM-DD)* — explicit unsourced flag
```

This is what makes lint possible. Without it, claims float free.

---

## Index Format

Each entry in `index.md` follows this pattern:

```
| [Page Title](path/page.md) | one-line summary | category |
```

The LLM reads `index.md` first when answering queries to locate relevant pages.

---

## Log Format

Each log entry starts with a consistent prefix so it is grep-parseable:

```
## [YYYY-MM-DD] <operation> | <title>
```

Operations: `ingest`, `query`, `lint`, `create`, `update`.

---

## Operations

The wiki has three operations: **ingest**, **query**, **lint**. Pages are *source-driven* — they're created or updated when a source is ingested, not preemptively to mirror the codebase.

### Ingest
When the user drops a source into `wiki/raw/` and says "ingest X":
1. Read the source document in full.
2. Discuss key takeaways with the user if needed.
3. Write a summary page (or update the existing one) under the appropriate folder.
4. Update all relevant entity, concept, and reference pages — including their `sources:` count and `## Sources` section.
5. Flip any `status: derived-from-code` pages that this source backs to `status: sourced`.
6. Update `index.md`.
7. Append an entry to `log.md`.

A single ingest may touch 10–15 pages. That's expected.

### Query
When the user asks a question:
1. Read `index.md` to locate relevant pages.
2. Read those pages in full.
3. Synthesize an answer with page citations (link back to the wiki page, not just the raw source).
4. **If the answer is reusable knowledge, offer to file it as a new page** under `wiki/concepts/` or `wiki/references/` with `type: query-output` and `status: sourced`. Good answers compound — they shouldn't disappear into chat history.

### Lint
When the user asks to health-check the wiki:
1. **Provenance gap** — list every page with `status: derived-from-code` and every page where `sources: 0`. These need backing.
2. **Stale claims** — pages where a newer raw source contradicts the body.
3. **Orphans** — pages with no inbound `[[links]]`.
4. **Missing cross-references** — pages that mention a concept that has its own page but don't link to it.
5. **Contradictions** — between pages.
6. **Suggested next sources** — what raw inputs would resolve the biggest gaps. *Don't suggest creating empty entity pages to mirror the codebase — wait for a source.*
7. Append a lint entry to `log.md`.

---

## Guardrails

- **Never modify files in `wiki/raw/`** — they are immutable source documents.
- **Don't create entity/concept pages preemptively to mirror the codebase.** The wiki is source-driven. If the user hasn't ingested a source about it and isn't actively asking about it, don't create the page. `git grep` already mirrors the codebase.
- **Always update `index.md`** when creating or significantly changing a page.
- **Always append to `log.md`** after any ingest, significant query, or lint pass.
- **Prefer updating existing pages** over creating new ones unless the topic is genuinely new.
- **Every claim needs a source line.** If you write something in a wiki page and can't point to a `raw/` file or a code path, mark the page `status: derived-from-code` and note it under `## Sources`.
- Do not create wiki pages for ephemeral decisions — those belong in git commit messages or PR descriptions.

---

## Quick Reference

### Backend

| Area | Key files |
|------|-----------|
| Models | `backend/app/Models/` |
| Controllers | `backend/app/Http/Controllers/Api/V1/` |
| Enums | `backend/app/Enums/` |
| Jobs | `backend/app/Jobs/` |
| Routes | `backend/routes/api.php` |
| Schedule | `backend/routes/console.php` |

### Frontend

| Area | Key files |
|------|-----------|
| Pages | `frontend/src/pages/` |
| API modules | `frontend/src/api/modules/` |
| Types | `frontend/src/types/` |
| Workflow editor | `frontend/src/pages/editor/WorkflowEditor/` |
| Theme components | `frontend/src/components/` |

---

## Coding Behaviour

These guidelines apply whenever Claude writes or edits code in this project.

### Think Before Coding

**Don't assume. Don't hide confusion. Surface tradeoffs.**

Before implementing:
- State your assumptions explicitly. If uncertain, ask.
- If multiple interpretations exist, present them — don't pick silently.
- If a simpler approach exists, say so. Push back when warranted.
- If something is unclear, stop. Name what's confusing. Ask.

### Simplicity First

**Minimum code that solves the problem. Nothing speculative.**

- No features beyond what was asked.
- No abstractions for single-use code.
- No "flexibility" or "configurability" that wasn't requested.
- No error handling for impossible scenarios.
- If you write 200 lines and it could be 50, rewrite it.

Ask yourself: "Would a senior engineer say this is overcomplicated?" If yes, simplify.

### Surgical Changes

**Touch only what you must. Clean up only your own mess.**

When editing existing code:
- Don't "improve" adjacent code, comments, or formatting.
- Don't refactor things that aren't broken.
- Match existing style, even if you'd do it differently.
- If you notice unrelated dead code, mention it — don't delete it.

When your changes create orphans:
- Remove imports/variables/functions that YOUR changes made unused.
- Don't remove pre-existing dead code unless asked.

The test: every changed line should trace directly to the user's request.

### Goal-Driven Execution

**Define success criteria. Loop until verified.**

Transform tasks into verifiable goals:
- "Add validation" → "Write tests for invalid inputs, then make them pass"
- "Fix the bug" → "Write a test that reproduces it, then make it pass"
- "Refactor X" → "Ensure tests pass before and after"

For multi-step tasks, state a brief plan:
```
1. [Step] → verify: [check]
2. [Step] → verify: [check]
3. [Step] → verify: [check]
```

Strong success criteria let you loop independently. Weak criteria ("make it work") require constant clarification.
