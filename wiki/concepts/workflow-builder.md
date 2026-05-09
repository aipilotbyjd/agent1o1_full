---
type: concept
status: sourced
sources: 2
last_updated: 2026-05-09
tags: [ui, canvas, frontend]
---

# Workflow Builder (Visual Canvas Editor)

**TL;DR**: The core UI — a React Flow canvas where users drag, drop, and connect nodes to build automations visually.

---

## Technology

- **@xyflow/react** (React Flow v12) — the canvas engine
- Lives at: `frontend/src/pages/editor/WorkflowEditor/`

## Canvas Structure

```
WorkflowEditor/
├── Build/          # Main build canvas
├── _context/       # Editor context providers
├── _helper/        # Utility helpers
├── _hooks/         # Custom hooks
├── _layouts/       # Layout wrappers
├── _partial/       # Partial components
└── _types/         # Editor-specific types
```

## Key Interactions

1. **Node Palette** — panel showing available [[node]] types grouped by category
2. **Canvas** — drag nodes from palette, drop onto canvas, position freely
3. **Edge Drawing** — draw a line from one node's output handle to another's input handle
4. **Node Config Panel** — click a node to open its parameter form
5. **Run/Test** — trigger a test [[execution]] from the editor
6. **Pinned Data** — pin sample output to a node for testing downstream nodes without re-running upstream (`PinnedNodeData`)
7. **Sticky Notes** — freeform annotations on the canvas (`StickyNote`)

## Versioning in the Editor

The editor saves `WorkflowVersion` snapshots. Users can view, compare, and restore previous versions. Version comparison shows added/removed/modified nodes and connections.

## Execution Panel

While a workflow is running (or after), the canvas shows live node statuses (`success`, `error`, `running`, `skipped`) overlaid on the node UI. Execution logs are accessible per node.

## Pinned Data for Testing

Users can pin test JSON output to any node. When running in test mode, downstream nodes use the pinned data instead of actually executing the upstream node. Critical for testing integrations without side effects.

## Keyboard / UX

- `re-resizable` for resizable panels (config side panel)
- `framer-motion` for animations
- `is-hotkey` for keyboard shortcuts

## Planned / Reference Features from Competitors

- n8n: sticky notes, sub-workflows, versioning, pinned data — all implemented
- Gumloop: AI-first node suggestions, natural language to workflow — potential roadmap item
- Zapier: "Zap history" (execution log) — implemented as [[execution]] history

See [[references/competitors]] for the full competitive breakdown.

---

## Sources

- `raw/frontend-api-modules-2026-05-09.txt` — confirms `workflows/` has `editor.hooks.ts`, `editor.service.ts`, `shares.hooks.ts`, `shares.service.ts` in addition to standard module files
- `raw/api-routes-2026-05-09.txt` — confirms all canvas-related sub-resources: versions (with diff/publish/rollback), shares, sticky-notes, pinned-data (with toggle), polling-trigger, build endpoint
- `frontend/src/pages/editor/WorkflowEditor/`, `frontend/package.json` — React Flow and supporting libs
- *(no external sources yet — flag: UX research, n8n editor screenshots for comparison)*
