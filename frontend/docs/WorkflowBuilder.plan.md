# Gumloop Workflow Builder Clone — Full Plan

Goal: turn the placeholder at `src/pages/editor/WorkflowEditor/WorkflowEditor.page.tsx` into a production-grade visual workflow builder with feature parity to Gumloop, following the conventions codified in `Structure.md`.

---

## 1. Feature Parity Checklist (What "Exact Same" Means)

### Canvas (center)

- Infinite pan/zoom canvas with dotted/grid background
- Drag-and-drop nodes from the left library onto the canvas
- Bezier edges with typed sockets (string / number / list / file / any)
- Port hover-to-connect, click empty canvas to deselect
- Multi-select (marquee + shift-click), copy / paste / duplicate, group move
- Undo / redo stack (hotkeys + buttons)
- Auto-layout (dagre), fit-view, zoom-to-selection
- Minimap, zoom controls, breadcrumb, background dots
- Per-node status badge (idle / running / success / error / skipped)
- Per-node execution timing and token/cost readout
- Inline node rename, collapse/expand, lock
- Sticky-notes / comment nodes
- Subflow node (embeds another workflow)

### Left panel — Node Library

- Search bar with fuzzy match
- Categorized, collapsible groups: **Inputs**, **AI**, **Extract**, **Scrape**, **Data**, **Logic**, **Loops**, **Integrations** (Gmail, Sheets, Slack, Notion, HTTP…), **Output**, **Subflows**, **Custom**
- Drag-to-canvas and click-to-insert at cursor
- Favorites / recents section

### Right panel — Node Inspector

- Dynamic form driven by each node's schema (JSON-Schema-ish)
- Variable picker: `{{Node Name.output}}` autocomplete pulled from upstream nodes
- Per-field types: text, long-text, code, key-value, select, multi-select, number, toggle, file, credential picker, model picker
- Test-this-node button, live output preview
- Docs / help drawer per node

### Top bar

- Workflow name + folder breadcrumb, inline rename
- Save state indicator (saved / saving / error), autosave
- Run / Stop / Test-one-row buttons
- Input config modal ("Run with inputs")
- History / Versions dropdown (snapshot + diff + restore)
- Share modal (link, team, public template)
- Tabs: **Build**, **Runs**, **Logs**, **Schedule**, **API**
- Avatars of collaborators (optional later)

### Bottom panel — Execution Console

- Resizable drawer
- Per-node streaming logs, tabs: Inputs, Outputs, Logs, Raw
- Table view for bulk runs (row per input, column per node output)
- Error inspector with stack + retry

### Runs / History pages

- Runs list, filter by status / date / workflow
- Run detail: replay timeline, download outputs as CSV/JSON
- Compare runs

### Sidebar (app-level)

- New top-level entry: **Builder** (opens editor for a given workflow id)

### Collaboration (phase 2, optional)

- Live cursors, presence avatars, soft-locking of nodes, commenting threads

---

## 2. Tech Choices (fits existing stack)

- **React Flow (`@xyflow/react`)** — node canvas engine. Industry standard; matches Gumloop's feel out of the box.
- **Zustand** — editor store (React Flow's own recommendation); avoids bloating React Query.
- **dagre** — auto-layout.
- **React Query** (already present) — persistence via `src/api/modules/workflows`.
- **Formik + Yup** (already present) — dynamic inspector forms.
- **Slate** (already present) — long-text/rich-text prompt fields and variable chips.
- **Framer Motion** (already present) — side-panel animations.
- **react-hotkeys-hook** — editor hotkeys.
- **nanoid** — node / edge ids.
- **immer** — inside Zustand for ergonomic undo / redo patches.
- **fuse.js** — library fuzzy search.
- **socket.io-client** — later, for live logs + presence.

New deps to add: `@xyflow/react`, `zustand`, `immer`, `dagre`, `@types/dagre`, `react-hotkeys-hook`, `nanoid`, `fuse.js`.

---

## 3. Data Model

```ts
// src/types/workflow.type.ts additions
type TPortType = 'string' | 'number' | 'boolean' | 'list' | 'file' | 'json' | 'any';

type TNodePort = {
	id: string;
	name: string;
	type: TPortType;
	required?: boolean;
};

type TNodeSchemaField = {
	key: string;
	label: string;
	kind:
		| 'text'
		| 'longtext'
		| 'code'
		| 'number'
		| 'toggle'
		| 'select'
		| 'multiselect'
		| 'kv'
		| 'credential'
		| 'model'
		| 'file';
	options?: { label: string; value: string }[];
	default?: unknown;
	required?: boolean;
	help?: string;
	supportsVariables?: boolean;
};

type TNodeType = {
	key: string; // 'ai.chat', 'scrape.url', …
	category:
		| 'input'
		| 'ai'
		| 'extract'
		| 'scrape'
		| 'data'
		| 'logic'
		| 'loop'
		| 'integration'
		| 'output'
		| 'subflow'
		| 'custom';
	label: string;
	description: string;
	icon: string;
	inputs: TNodePort[];
	outputs: TNodePort[];
	fields: TNodeSchemaField[];
	color?: string;
};

type TWorkflowNode = {
	id: string;
	type: string;
	position: { x: number; y: number };
	data: {
		label: string;
		values: Record<string, unknown>;
		notes?: string;
		locked?: boolean;
	};
};

type TWorkflowEdge = {
	id: string;
	source: string;
	sourceHandle: string;
	target: string;
	targetHandle: string;
};

type TWorkflowGraph = {
	nodes: TWorkflowNode[];
	edges: TWorkflowEdge[];
	viewport?: { x: number; y: number; zoom: number };
};
```

Workflow persistence shape: `{ id, name, folderId, graph: TWorkflowGraph, version, updatedAt }`.

---

## 4. File Layout (follows `Structure.md` §4.4 Pattern B)

Everything lives under `src/pages/editor/WorkflowEditor/` because it's a large multi-page feature (Build / Runs / Logs / Schedule / API tabs).

```
src/pages/editor/WorkflowEditor/
├── WorkflowEditor.page.tsx            # route shell, loads workflow by :id
├── Build/
│   ├── Build.page.tsx                 # the canvas view (tab content)
│   ├── _partial/
│   │   ├── Canvas.partial.tsx         # <ReactFlow/> wrapper
│   │   ├── CanvasControls.partial.tsx # zoom, fit, layout
│   │   ├── Minimap.partial.tsx
│   │   ├── NodeLibrary.partial.tsx    # left panel
│   │   ├── NodeLibrarySearch.partial.tsx
│   │   ├── NodeLibraryGroup.partial.tsx
│   │   ├── Inspector.partial.tsx      # right panel
│   │   ├── Inspector.Field.partial.tsx
│   │   ├── Inspector.VariablePicker.partial.tsx
│   │   ├── Inspector.TestRun.partial.tsx
│   │   ├── Topbar.partial.tsx
│   │   ├── Topbar.RunButton.partial.tsx
│   │   ├── Topbar.SaveBadge.partial.tsx
│   │   ├── Topbar.VersionsMenu.partial.tsx
│   │   ├── Topbar.ShareMenu.partial.tsx
│   │   ├── Console.partial.tsx        # bottom drawer
│   │   ├── Console.NodeLogs.partial.tsx
│   │   ├── Console.Table.partial.tsx
│   │   ├── ContextMenu.partial.tsx
│   │   ├── nodes/                     # React Flow custom node renderers
│   │   │   ├── BaseNode.partial.tsx
│   │   │   ├── InputNode.partial.tsx
│   │   │   ├── AIChatNode.partial.tsx
│   │   │   ├── ScrapeUrlNode.partial.tsx
│   │   │   ├── ExtractDataNode.partial.tsx
│   │   │   ├── LoopNode.partial.tsx
│   │   │   ├── IfNode.partial.tsx
│   │   │   ├── CategorizerNode.partial.tsx
│   │   │   ├── HttpNode.partial.tsx
│   │   │   ├── OutputNode.partial.tsx
│   │   │   ├── StickyNote.partial.tsx
│   │   │   └── SubflowNode.partial.tsx
│   │   └── edges/
│   │       └── BezierEdge.partial.tsx
│   ├── _helper/
│   │   ├── builder.constants.ts       # categories, colors, hotkeys
│   │   ├── graph.helper.ts            # add/remove/duplicate node, connect
│   │   ├── layout.helper.ts           # dagre auto-layout
│   │   ├── variables.helper.ts        # {{x}} parsing, autocomplete source
│   │   ├── validate.helper.ts         # graph validation (cycles, required)
│   │   └── serialize.helper.ts        # to/from persisted shape
│   ├── _hooks/
│   │   ├── useEditorHotkeys.hook.ts
│   │   ├── useAutosave.hook.ts
│   │   ├── useHistory.hook.ts         # undo/redo
│   │   └── useClipboard.hook.ts
│   ├── _context/
│   │   └── EditorStore.context.tsx    # Zustand provider + selectors
│   └── _types/
│       └── editor.type.ts
│
├── Runs/
│   ├── Runs.page.tsx
│   └── _partial/
├── Logs/
│   ├── Logs.page.tsx
│   └── _partial/
├── Schedule/
│   ├── Schedule.page.tsx
│   └── _partial/
├── Api/
│   ├── Api.page.tsx
│   └── _partial/
│
├── _shared/
│   ├── _partial/
│   │   ├── EditorLayout.partial.tsx
│   │   └── TabBar.partial.tsx
│   └── _helper/
│       └── editor.constants.ts
└── _layouts/
    └── WorkflowEditorLayout.layout.tsx
```

API modules (`src/api/modules/`) touched — no new suffixes, just extra siblings per §3.3:

```
src/api/modules/workflows/
├── workflows.endpoints.ts   (already)
├── workflows.service.ts     (already)
├── workflows.hooks.ts       (already)
├── workflows.keys.ts        (already)
├── editor.service.ts        # save-graph, validate, pinned-data, versions
├── editor.hooks.ts
├── runs.service.ts          # trigger run, stream logs (SSE/WS), stop
├── runs.hooks.ts
├── shares.service.ts
└── shares.hooks.ts
```

Plus `src/api/modules/node-types/` (exists) fleshed out to serve the full node catalog.

---

## 5. Editor Store (Zustand)

Single source of truth for the canvas; React Flow is a controlled component on top of it.

State slices:

- **graph**: `nodes`, `edges`, `viewport`
- **selection**: selected node/edge ids
- **inspector**: open node id, dirty form values
- **history**: past / future patches (immer), capped at 100
- **run**: current run id, per-node status map, streamed logs
- **ui**: left panel width, right panel width, console height, zoom, showMinimap
- **meta**: workflow id, name, saving state, lastSavedAt

Actions: `addNode`, `removeNodes`, `connect`, `disconnect`, `updateNodeData`, `duplicate`, `paste`, `undo`, `redo`, `autoLayout`, `applyServerSnapshot`, `markDirty`.

---

## 6. Rendering Pipeline

1. Route `/editor/workflows/:id` → `WorkflowEditor.page.tsx` fetches workflow via `useWorkflow(id)`.
2. On success → hydrate Zustand store via `applyServerSnapshot`, mount `EditorLayout`.
3. Layout: `Topbar` (top), `NodeLibrary` (left, resizable), `Canvas` + `Console` (center, vertical split), `Inspector` (right, resizable).
4. Canvas passes `nodeTypes` and `edgeTypes` maps to React Flow; each custom node reads its own slice via a selector for perf.
5. Inspector subscribes only to `selection.nodeId` + the chosen node's `data.values`.
6. Autosave hook diffs the graph every 1.5s idle, calls `editor.service.saveGraph`.

---

## 7. Execution Flow

- `Run` → `runs.service.trigger(workflowId, inputs)` returns a `runId`.
- Open WebSocket / SSE stream to `/runs/:runId/events`.
- Events update `run.statusByNode` and append to `run.logs[nodeId]`.
- Nodes re-render based on their status (border color + badge).
- Console bottom drawer shows live output; selecting a node filters to that node.
- Stop → `runs.service.stop(runId)`.

Backend contract (to agree with API team):

```
POST /workflows/:id/runs          → { runId }
GET  /runs/:id                    → snapshot
GET  /runs/:id/stream  (SSE)      → node events
POST /runs/:id/stop
POST /workflows/:id/test-node     → run single node w/ inputs
```

---

## 8. Node Catalog (v1 minimum)

Matches Gumloop's most-used blocks.

| Category    | Node                | Purpose                     |
| ----------- | ------------------- | --------------------------- |
| Input       | Ask AI Input        | Prompt the user at run time |
| Input       | File Input          | Upload CSV / PDF / image    |
| Input       | Google Sheet Input  | Read rows                   |
| AI          | Ask AI              | Single LLM call with prompt |
| AI          | Categorizer         | Pick one of N buckets       |
| AI          | Summarizer          | Long-text → short-text      |
| AI          | Extract Data        | LLM + schema → JSON         |
| Scrape      | Website Scraper     | Fetch URL → markdown/text   |
| Scrape      | Website Crawler     | BFS from seed URL           |
| Data        | HTTP Request        | Generic REST call           |
| Data        | JSON Transform      | JMESPath / jq               |
| Data        | Merge / Join        | Combine streams             |
| Logic       | If / Else           | Branch on expression        |
| Logic       | Switch              | Multi-branch                |
| Loop        | For Each            | Iterate list → sub-graph    |
| Integration | Gmail Send          | Send email                  |
| Integration | Google Sheets Write | Append rows                 |
| Integration | Slack Message       | Post to channel             |
| Integration | Notion Page         | Create page                 |
| Output      | Display             | Render output to UI         |
| Output      | File Output         | Zip / CSV download          |
| Misc        | Sticky Note         | Comments                    |
| Misc        | Subflow             | Embed another workflow      |

Each is defined once in `node-types.service.ts` as a `TNodeType`; the builder reads them and renders automatically — adding a new node later = adding one config object.

---

## 9. Hotkeys

| Key                        | Action                    |
| -------------------------- | ------------------------- |
| `Space` (hold) + drag      | Pan                       |
| `Cmd/Ctrl + Z` / `Shift+Z` | Undo / Redo               |
| `Cmd/Ctrl + C / V / D`     | Copy / Paste / Duplicate  |
| `Delete` / `Backspace`     | Remove selection          |
| `Cmd/Ctrl + A`             | Select all                |
| `Cmd/Ctrl + S`             | Force save                |
| `Cmd/Ctrl + Enter`         | Run workflow              |
| `/`                        | Focus node library search |
| `F`                        | Fit view                  |
| `L`                        | Auto-layout               |

---

## 10. Milestones

### M1 — Skeleton (1–2 days)

- Install deps, wire route, `EditorLayout` with empty topbar / sidebars / console.
- Zustand store, empty React Flow canvas with grid + minimap + controls.

### M2 — Graph editing (3–4 days)

- Custom `BaseNode`, drag-from-library, connect, delete, multi-select, undo/redo, copy/paste, hotkeys.
- Autosave to backend (stub service OK).

### M3 — Node catalog + Inspector (4–5 days)

- `TNodeType` schema, 8 real node types, dynamic Inspector form, variable picker with upstream resolution.
- Graph validation (cycles, missing required fields, type mismatch on edges).

### M4 — Execution (4–5 days)

- Run button, SSE/WS stream, per-node status + logs, Console drawer, stop, retry.
- Test-single-node.

### M5 — Polish (3 days)

- Auto-layout, versions, share modal, sticky notes, subflow node, schedule tab, API tab.

### M6 — Runs & Logs pages (2 days)

- Runs list, run detail timeline, bulk-run table, CSV export.

### M7 — Nice-to-haves (ongoing)

- Real-time presence, comments, templates gallery, marketplace nodes.

---

## 11. Rules (Don't Break)

1. Every file obeys the suffix → slot mapping in `Structure.md` §4.2.
2. No React Query hooks under `pages/editor/` — they live in `src/api/modules/workflows/`.
3. All toasts go through `@/api/core/notify`.
4. All barrels use `export *`.
5. Node type catalog is data-driven: **never hard-code a node component in a switch statement outside the `nodes/` folder**.
6. Zustand store is the only mutable source of truth for the graph; React Flow is controlled.
7. Autosave never fires during drag / connect operations — only on idle.
8. Variable references are always `{{NodeId.outputKey}}` internally; UI renders `{{Node Label.outputKey}}` via a lookup.

---

## 12. Open Questions For Backend

- Execution engine: sync HTTP or job queue + SSE stream? (prefer the latter)
- Credential encryption: per-workspace KMS?
- Versioning: snapshot-per-save or branch model?
- File inputs: direct-to-S3 presigned URLs?
- Rate limits per node type (esp. LLMs / scrapers).

Answering these shapes the `runs.service` and `credentials` modules.
