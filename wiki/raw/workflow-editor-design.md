# Workflow Editor Design Document

> **TL;DR** — The Workflow Editor is Agent1o1's core product surface: a React Flow-based automation canvas with a node library, inspector, AI builder, run console, autosave, version save/publish, validation, import/export, hotkeys, and future live debugging capabilities.

---

## 1. Editor Product Overview

The Workflow Editor enables users to visually build automations by placing nodes on a canvas, connecting typed ports, configuring node settings, running tests, inspecting outputs, and publishing workflow versions.

### Primary Users

| User               | Needs                                                                         |
| ------------------ | ----------------------------------------------------------------------------- |
| Automation Builder | Fast node discovery, drag/drop creation, clear configuration, quick test runs |
| Technical Operator | Typed ports, validation, logs, JSON output, import/export, version control    |
| Workspace Admin    | Safe publish flow, credential awareness, permission-based actions             |
| AI-first User      | Prompt-to-workflow assistance, suggestions, guided template starts            |

### Editor Goals

- Make graph building fast, visual, and reversible.
- Make node configuration predictable and schema-driven.
- Make save, dirty, local draft, server version, and publish states explicit.
- Make run/debug behavior node-level, not only workflow-level.
- Keep the canvas responsive for large workflows.
- Provide keyboard shortcuts and accessible non-pointer alternatives.

---

## 2. Current Editor State

The editor currently exists at: `frontend/src/pages/editor/WorkflowEditor/`

### Current Capabilities

| Area | Current Implementation |
|---|---|
| Route Shell | `WorkflowEditor.page.tsx` wraps `BuildPage` in `WorkflowEditorLayout` |
| Canvas | `@xyflow/react` canvas with background, controls, minimap, typed edges, custom nodes |
| Left Panel | Resizable `NodeLibrary` with API categories and local fallback catalog |
| Right Panel | Resizable `Inspector` with Settings, Ports, Preview tabs |
| AI Panel | Resizable `AiBuilderPanel`, toggled from topbar/hotkey |
| Run Panel | Resizable bottom `RunPanel` with timeline, node outputs, logs |
| Topbar | Rename, home, undo/redo, auto-layout, save, publish, AI, run/stop, dark mode |
| Action Bar | Zoom, fit view, auto-layout, minimap, command palette, run console |
| Status Bar | Save state, validation error/warning counts, folder label |
| State | React reducer/context stores workflow, nodes, edges, run, UI, history |
| Persistence | Autosaves full workflow to localStorage after dirty debounce |
| API | Loads workflow/version; saves versions; publishes current version |
| Hotkeys | Undo, redo, run/stop, command palette, AI panel, panels, import/export, auto-layout, duplicate, delete |
| Validation | Node/edge validation appears in canvas and status bar |
| Import/Export | Dialog and helpers exist |

### Current Pain Points

| Issue | Impact | Recommendation |
|---|---|---|
| Save model mixes local autosave and API version save | Users may not know what is local vs server-persisted | Split UI into Local Draft, Saved Version, Published Version states |
| Editor state is one reducer context | Large graphs can cause broad re-renders | Move to selector-based store or split contexts by graph/UI/run |
| Mobile behavior is not explicit | Canvas editing is hard on phones | Provide read-only/run mode on mobile and full edit on tablet/desktop |
| Permission behavior is not visible | Publish/save/delete may appear to all authenticated users | Add permission-aware actions |
| Run panel is local-state oriented | Live execution debugging needs backend stream integration | Connect executions/log streaming APIs and SSE |
| Node catalog fallback is useful but subtle | Users may unknowingly build from local definitions | Make API fallback state clear and actionable |
| Canvas keyboard accessibility is incomplete | Pointer-only editing excludes keyboard users | Add keyboard node selection/move/connect patterns |

---

## 3. Future Editor Direction

The editor should evolve into a complete automation workbench with five operating modes.

### Editor Modes

| Mode      | Purpose                                                   |
| --------- | --------------------------------------------------------- |
| Build     | Add/connect/configure nodes on the canvas                 |
| Test      | Run workflow or selected node with sample input           |
| Debug     | Inspect timeline, logs, node outputs, failures            |
| Versions  | Compare, restore, save, publish workflow versions         |
| Share/API | Share workflow, export/import, expose trigger/API details |

### Target Editor Experience

```
Open workflow editor
  → Load workflow + current version
    → Existing graph?
        No → Empty canvas: template / AI / add first node
        Yes → Canvas with graph
  → Configure nodes in inspector
  → Validate graph
    → Valid?
        No → Show node issues + status bar errors → back to Configure
        Yes → Run test
  → Stream statuses/logs/output
    → Success?
        No → Open failed node + logs → back to Configure
        Yes → Save version → Publish
```

---

## 4. Editor Architecture

### Current File Structure

```
WorkflowEditor/
├── WorkflowEditor.page.tsx
├── Build/Build.page.tsx
├── _context/
│   ├── WorkflowEditorProvider.context.tsx
│   └── WorkflowEditorStore.context.tsx
├── _helper/
│   ├── apiNodeCatalog.helper.ts
│   ├── builder.constants.ts
│   ├── importExport.helper.ts
│   ├── layout.helper.ts
│   ├── nodeCatalog.constants.ts
│   ├── nodeGroups.constants.ts
│   ├── runGraph.helper.ts
│   ├── validation.helper.ts
│   ├── variables.helper.ts
│   └── workflowApiTransform.helper.ts
├── _hooks/
│   ├── useAutosave.hook.ts
│   ├── useCanvasDrop.hook.ts
│   ├── useEditorHotkeys.hook.ts
│   ├── useNodeSelection.hook.ts
│   ├── useRunWorkflow.hook.ts
│   ├── useWorkflowApiLoader.hook.ts
│   └── useWorkflowRouteParams.hook.ts
├── _layouts/
├── _partial/
│   ├── ai/
│   ├── canvas/
│   ├── dialogs/
│   ├── inspector/
│   ├── library/
│   ├── run/
│   └── shell/
└── _types/
```

### Recommended State Split

| Slice | Owns | Example Actions |
|---|---|---|
| Graph State | nodes, edges, viewport, selection | add node, move node, connect, delete, duplicate |
| UI State | panels, dialogs, active tabs, widths | toggle panel, open command palette |
| Persistence State | dirty/saving/saved/error, draft/version IDs | save draft, save version, publish |
| Run State | current run, node statuses, logs, outputs | run start, node running, append log, run finish |
| Catalog State | API node types, fallback definitions, search | load categories, filter nodes |
| History State | undo/redo snapshots or patches | undo, redo, clear history |

### Data Flow

```
Route (workspaceId/workflowId)
  → useWorkflowApiLoader → Editor Store
useNodeCategories → Node Library → Editor Store

Editor Store → Canvas (React Flow)
Editor Store → Inspector
Editor Store → Run Panel
Editor Store → Status Bar

Topbar → Workflow Version APIs → Editor Store
Topbar → Run Workflow Hook → Editor Store
```

---

## 5. Editor Layout and UI System

### Layout Regions

| Region | Component | Behavior |
|---|---|---|
| AI Panel | `AiBuilderPanel` | Optional left-most resizable panel |
| Topbar | `Topbar` | Global editor actions, save/publish/run |
| Node Library | `NodeLibrary` | Resizable left panel |
| Canvas | `Canvas` | Flexible center region |
| Action Bar | `ActionBar` | Floating canvas controls |
| Inspector | `Inspector` | Resizable right panel, opens when node selected |
| Run Panel | `RunPanel` | Resizable bottom panel |
| Status Bar | `StatusBar` | Validation and save feedback |

### Visual Rules

- Use dense application styling with zinc backgrounds and 8px radius surfaces.
- Use emerald/primary colors for successful actions.
- Use rose for destructive actions.
- Use amber for warnings.
- Use animated blue states for running flows.
- Keep node category colors visually consistent across the editor.

### Responsive Behavior

| Panel | Desktop | Tablet | Mobile |
|---|---|---|---|
| Node Library | Resizable left panel | Drawer/overlay | Hidden or read-only |
| Inspector | Resizable right panel | Drawer/overlay | Full-screen sheet |
| Run Panel | Bottom resizable panel | Bottom sheet | Full-screen debug view |
| AI Panel | Resizable side panel | Drawer | Separate guided flow |

---

## 6. Core Editor User Flows

### Create New Workflow

| Step | User Action | UI Response | State | API |
|---|---|---|---|---|
| 1 | Open new editor route | Empty canvas loads | Untitled workflow state | Optional create later |
| 2 | Select blank/template/AI | Nodes or AI panel appear | Graph dirty | Templates loaded |
| 3 | Rename workflow | Topbar updates | Metadata dirty | Deferred save |
| 4 | Save | Save state updates | Saving/saved/error | Create workflow/version |

### Add Node

| Step | User Action | UI Response | State | API |
|---|---|---|---|---|
| 1 | Search library | Results filtered | Local search state | Node categories loaded |
| 2 | Drag/click node | Node appears | Node added + selected | None |
| 3 | Configure fields | Inspector updates | Dirty state | None |

### Connect Nodes

| Step | User Action | UI Response | State | API |
|---|---|---|---|---|
| 1 | Drag output handle | Valid targets highlight | Temporary connection | None |
| 2 | Drop on input handle | Edge created | Edge added | None |
| 3 | Invalid connection | Error state shown | Validation issue | None |

### Save and Publish

| Step | User Action | UI Response | State | API |
|---|---|---|---|---|
| 1 | Graph becomes dirty | Dirty badge visible | Dirty | Local autosave |
| 2 | Save clicked | Loading state | Saving | Create version |
| 3 | Save succeeds | Version metadata updates | Saved | API response |
| 4 | Publish clicked | Publish loading | Pending publish | Publish endpoint |

### Run and Debug

| Step | User Action | UI Response | State | API |
|---|---|---|---|---|
| 1 | Run workflow | Run panel opens | RUN_START | Create execution |
| 2 | Node starts | Active styles shown | Running state | Stream event |
| 3 | Node completes | Output visible | Success/error | Result event |
| 4 | Workflow finishes | Timeline updates | Final state | Execution status |
| 5 | Inspect output | Logs/output visible | Output selected | Execution logs |

---

## 7. Component Documentation

### `WorkflowEditorPage`

| Item | Detail |
|---|---|
| Purpose | Route entry for editor |
| Children | `WorkflowEditorLayout`, `BuildPage` |
| Future | Add Suspense and route-level error boundary |

### `BuildPage`

| Item | Detail |
|---|---|
| Purpose | Main editor composition |
| Owns | Panel widths and run panel height |
| Hooks | autosave, hotkeys, route params, API loader |
| Error Handling | Local-mode fallback banner |

### `Topbar`

| Item | Detail |
|---|---|
| Purpose | Main command surface |
| Actions | save, publish, AI, run, undo/redo, auto-layout |
| Improvement | Explicit draft/version/publish states |

### `NodeLibrary`

| Item | Detail |
|---|---|
| Purpose | Node discovery and insertion |
| Improvement | Favorites, recents, keyboard insertion |

### `Canvas`

| Item | Detail |
|---|---|
| Purpose | Graph editing area |
| Engine | React Flow |
| Improvement | Multi-select, grouping, keyboard movement |

### `Inspector`

| Item | Detail |
|---|---|
| Purpose | Configure selected node |
| Tabs | Settings, Ports, Preview |
| Improvement | Schema validation, credential integration |

### `RunPanel`

| Item | Detail |
|---|---|
| Purpose | Display execution logs and output |
| Improvement | Virtualized logs and replay actions |

### `ActionBar`

| Item | Detail |
|---|---|
| Purpose | Floating canvas actions |
| Improvement | Stable zoom subscription updates |

### `StatusBar`

| Item | Detail |
|---|---|
| Purpose | Save/validation feedback |
| Improvement | Clickable issue details |

### `CommandPalette`

| Item | Detail |
|---|---|
| Purpose | Keyboard-first command execution |
| Future | Add node insertion and workflow search |

---

## 8. Editor State Management

### Current State Shape

| State | Examples |
|---|---|
| workflow | id, name, folder, version |
| nodes | React Flow node objects |
| edges | Edge metadata |
| run | logs, status, timestamps |
| ui | dialogs, selected node |
| history | undo/redo snapshots |

### Recommended Improvements

- Use patch-based history for large graphs.
- Separate UI state from graph state.
- Persist panel widths and viewport.
- Model save state explicitly:
  - `localDirty`
  - `localDraftSaved`
  - `serverSaving`
  - `serverSaved`
  - `publishPending`
  - `publishFailed`
  - `published`

---

## 9. Editor API Integration

### Current API Dependencies

| Feature | Module |
|---|---|
| Load Workflow | `useWorkflow` |
| Workflow Versions | `useWorkflowVersions` |
| Save Version | `useCreateWorkflowVersion` |
| Publish Version | `usePublishWorkflowVersion` |
| Node Catalog | `useNodeCategories` |

### Future API Requirements

| Feature | API Need |
|---|---|
| Workflow creation | Create workflow endpoint |
| Server draft autosave | Draft endpoint |
| Workflow execution | Test-run endpoint |
| Live logs | SSE/WebSocket stream |
| Version compare | Diff endpoint |
| Rollback | Restore endpoint |
| Share | Workflow sharing endpoints |

### Error Handling

- Fallback banner for node catalog failure.
- Blocking editor state for workflow load failure.
- Retry handling for save/publish failures.
- Automatic failed-node focus during execution errors.

---

## 10. Validation and Graph Rules

### Validation Categories

| Category | Rule |
|---|---|
| Required Fields | Required node settings must exist |
| Port Types | Ports must be compatible |
| Missing Input | Required ports need data |
| Invalid JSON/Code | Config must parse successfully |
| Cycles | Prevent unless explicitly supported |
| Orphan Nodes | Warn disconnected nodes |
| Credentials | Credentials must be selected and valid |

### UX Treatment

- Blocking errors prevent publish/run.
- Warnings allow save/run.
- Node-level issues appear visually on canvas.
- Global issues appear in status bar.

---

## 11. Responsive Strategy

| Viewport | Behavior |
|---|---|
| Mobile | Read-only graph and logs |
| Tablet | Overlay inspector/library |
| Desktop | Full editor experience |
| Large Desktop | Persistent multi-panel layout |

### Mobile Fallback

Mobile should provide:
- Workflow summary
- Publish status
- Last run status
- Run/stop controls
- Logs and outputs
- Desktop editing recommendation

---

## 12. Accessibility

### Required Improvements

- Proper ARIA tab structure.
- Keyboard node navigation.
- `aria-live` save/run announcements.
- Accessible node focus states.
- Non-color validation indicators.
- Reduced-motion support.

---

## 13. Performance

### Risks

- Whole-context re-rendering.
- Full workflow autosave serialization.
- Large validation recalculations.
- Large execution logs.
- Heavy initial bundle size.

### Optimizations

- Selector-based stores.
- Memoized validation.
- Batched drag updates.
- Virtualized logs.
- Lazy-loaded editor bundles.
- Persistent viewport without history pollution.

---

## 14. Animation and Interaction

| Interaction | Rule |
|---|---|
| Node Drag | Immediate response |
| Edge Running | Animated only during execution |
| Panel Transitions | 180ms reduced-motion aware |
| Validation Issues | Non-jarring appearance |
| Save Success | Lightweight status update |
| Run Failure | Failed node highlighted |
| Drag Overlay | Dashed canvas overlay |

---

## 15. Security

- Never expose secrets in logs or previews.
- Credential fields must hide raw values.
- Treat logs/output as untrusted content.
- Sanitize markdown and HTML.
- Protect publish/delete/share with permissions.
- Confirm destructive actions.
- Warn before exporting sensitive data.

---

## 16. Testing Strategy

### Unit Tests

- `validation.helper.ts`
- `variables.helper.ts`
- `importExport.helper.ts`
- `workflowApiTransform.helper.ts`
- `layout.helper.ts`
- Reducer action flows

### Component Tests

- NodeLibrary filtering
- Canvas empty state
- Inspector field updates
- Save/publish loading states
- StatusBar issue rendering
- RunPanel logs and outputs

### E2E Tests

- Open editor
- Add nodes
- Connect compatible ports
- Reject incompatible connections
- Configure fields
- Save version
- Run workflow
- Inspect logs/output
- Publish workflow
- Import/export workflow

---

## 17. Implementation Roadmap

### Phase 1 — Editor UX Clarity

| Task | Verification |
|---|---|
| Add explicit draft/version states | Persistence state becomes understandable |
| Improve loading/error states | Failures become actionable |
| Clickable status bar issues | Faster debugging |
| Persist layouts | Editor restores layout |

### Phase 2 — Run and Debug Integration

| Task | Verification |
|---|---|
| Connect execution APIs | Real runs supported |
| Stream logs/status | Live execution updates |
| Retry/replay actions | Faster debugging |
| Virtualized logs | Large runs remain performant |

### Phase 3 — Graph Editing Features

| Task | Verification |
|---|---|
| Multi-select/group move | Easier large graph editing |
| Copy/paste | Faster workflow duplication |
| Command palette insertion | Keyboard-first editing |
| Accessibility controls | Pointer-free editing works |

### Phase 4 — Versioning and Collaboration

| Task | Verification |
|---|---|
| Version diff UI | Compare changes |
| Restore functionality | Recover previous versions |
| Conflict handling | Draft conflicts visible |
| Collaboration boundaries | Realtime-ready architecture |

### Phase 5 — Production Polish

| Task | Verification |
|---|---|
| Full E2E suite | Critical paths covered |
| Visual regression tests | Safe UI iteration |
| Performance benchmark | 200-node workflows usable |
| Accessibility audit | Keyboard/screen-reader compliance |

---

*Last Updated: May 10, 2026 · Tags: workflow-editor, canvas, frontend, ux, react-flow*
