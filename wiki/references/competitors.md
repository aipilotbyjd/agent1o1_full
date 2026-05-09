---
type: reference
status: hub
sources: 0
last_updated: 2026-05-09
tags: [reference, competitive, gumloop, n8n, zapier]
---

# Competitor Analysis: Gumloop, n8n, Zapier

**TL;DR**: LinkFlow draws inspiration from all three. n8n is the closest technical reference. Gumloop defines the AI-first vision. Zapier sets the usability benchmark.

---

## Gumloop

**Positioning**: AI-native automation builder. Natural language to workflow. Strong AI/LLM node types.

**Key Differentiators**:
- AI-first UX: describe what you want, it builds the workflow
- Strong built-in AI processing nodes (scrape + summarize, etc.)
- Simple, clean canvas UI
- Aimed at non-technical users

**What LinkFlow borrows from Gumloop**:
- AI node category with LLM capabilities
- Clean visual aesthetic
- Agent system as first-class workflow participant

**What Gumloop lacks** (LinkFlow opportunities):
- No self-host option
- Limited enterprise/team features
- Smaller integration catalog

---

## n8n

**Positioning**: The developer-first, open-source automation platform. Self-hostable. 400+ integrations.

**Key Differentiators**:
- Open source (fair-code license)
- Self-hostable
- Code nodes (JavaScript/Python)
- Sub-workflows
- Sticky notes on canvas
- Pinned data for testing
- Version history
- Environments (staging/prod)
- Approval nodes (human-in-the-loop)
- Git sync

**What LinkFlow borrows from n8n** (most similar technically):
- [[concepts/workflow-builder]] visual canvas
- Node categories (trigger, action, logic, transform, etc.)
- [[entities/execution]] with per-node status
- Pinned node data for testing
- Sticky notes
- Workflow versioning
- [[entities/variable]] / environment system
- Git sync (`GitSyncController` present in backend)
- Approval nodes (`WorkflowApproval`)
- Polling triggers

**What n8n lacks** (LinkFlow opportunities):
- Native AI agents as first-class entities
- Better UX for non-technical users
- Managed cloud with credits-based pricing

---

## Zapier

**Positioning**: Market leader. Simplest UX. "Zaps" = trigger + 1-N actions.

**Key Differentiators**:
- Massive integration catalog (6000+)
- Dead-simple linear workflow model (no branching)
- Strong brand recognition
- Non-technical user base

**What LinkFlow borrows from Zapier**:
- [[entities/template]] marketplace
- Clear execution history (Zap history = [[entities/execution]])
- Credential management UX

**What Zapier lacks** (LinkFlow opportunities):
- No visual canvas (linear only)
- No self-host
- Very expensive at scale
- No AI agents
- No code nodes
- No branching / complex logic

---

## LinkFlow's Positioning

| Feature | Gumloop | n8n | Zapier | LinkFlow |
|---------|---------|-----|--------|---------|
| Visual canvas | ✅ | ✅ | ❌ | ✅ |
| AI-native | ✅ | Partial | ❌ | ✅ |
| Code nodes | ❌ | ✅ | ❌ | ✅ |
| Self-host | ❌ | ✅ | ❌ | TBD |
| Pinned test data | ❌ | ✅ | ❌ | ✅ |
| Versioning | ❌ | ✅ | ❌ | ✅ |
| Approval nodes | ❌ | ✅ | ❌ | ✅ |
| Credit-based billing | ✅ | ❌ | ❌ | ✅ |
| Agent system | Partial | ❌ | ❌ | ✅ |
| Templates | ✅ | ✅ | ✅ | ✅ |

LinkFlow targets the gap between n8n (powerful but complex) and Gumloop (AI-first but limited) — with SaaS convenience, credits-based pricing, and a strong AI/agent story.

---

## Sources

- *(none — this page is a `status: hub` aggregator. Drop competitor blog posts, pricing pages, changelogs, screenshots into `raw/` and re-ingest. Each ingest should: refresh the comparison table, add a row under the relevant competitor section, and bump `sources:`)*

### Suggested raw inputs to ingest

- `gumloop.com/pricing` archive
- `n8n.io/changelog` recent entries
- `zapier.com/blog` AI-related posts
- Screenshots of each editor (use Obsidian Web Clipper + image download)
