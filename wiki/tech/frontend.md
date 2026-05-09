# Frontend Tech Stack

**TL;DR**: React 19 + TypeScript + Vite SPA, built on the Boltify admin theme, with React Flow for the workflow canvas and TanStack Query for data fetching.

---

## Core Stack

| Technology | Version | Role |
|-----------|---------|------|
| React | 19 | UI framework |
| TypeScript | 5.9 | Type safety |
| Vite | 7 | Build tool |
| Tailwind CSS | v4 | Styling |
| React Router | v7 | Client-side routing |
| TanStack Query | v5 | Server state / data fetching |
| Axios | v1 | HTTP client |

## Key Libraries

| Library | Purpose |
|---------|---------|
| `@xyflow/react` v12 | Workflow canvas (React Flow) |
| `framer-motion` | Animations |
| `@tanstack/react-table` | Data tables |
| `@hello-pangea/dnd` | Drag and drop (non-canvas) |
| `re-resizable` | Resizable panels in the editor |
| `formik` + `yup` | Forms and validation |
| `react-toastify` | Toast notifications |
| `apexcharts` | Charts (dashboard) |
| `slate` + `slate-react` | Rich text editor |
| `react-markdown` | Markdown rendering |
| `date-fns` + `dayjs` | Date handling |
| `i18next` | Internationalisation (EN, ES, AR) |
| `rc-tree` | Tree view component |
| `is-hotkey` | Keyboard shortcuts |
| `@floating-ui/react` | Popover / floating UI positioning |

## Boltify Theme

The purchased admin template provides:
- Layout system: `Wrapper`, `Container`, `Aside`, `Header`, `Subheader`
- Base components: `Button`, `Badge`, `Avatar`, `Card`, `Modal`, `Dropdown`, `Tooltip`, `Accordion`, `Tabs`, `Offcanvas`, `Skeleton`, etc.
- Form components: `Input`, `Select`, `Checkbox`, `Radio`, `FileInput`, `Textarea`, `RangeSlider`
- Chart wrappers via `react-apexcharts`
- Icon system via `HugeIcons` + custom SVG icons

Theme components live in `frontend/src/components/` and `frontend/src/examples/`.

## Directory Structure

```
frontend/src/
├── api/
│   ├── client/         # Axios instance, interceptors
│   ├── core/           # Base API utilities
│   └── modules/        # One folder per entity (workflows, executions, etc.)
├── components/
│   ├── ui/             # Boltify base components
│   ├── form/           # Form components
│   ├── layout/         # Navigation, Portal, User
│   └── icon/           # SVG icons and HugeIcons
├── constants/          # App-wide constants
├── context/            # React contexts
├── hooks/              # Custom React hooks
├── pages/
│   ├── _auth/          # Login, register, forgot password
│   ├── editor/WorkflowEditor/  # The canvas editor
│   ├── apps/           # CRM-style example pages from theme
│   ├── LandingPage/    # Marketing landing page
│   └── agent/          # Agent management pages
├── Providers/          # App-level providers (Query, Router, etc.)
├── Routes/
│   ├── agent1o1Pages/  # LinkFlow app routes
│   └── infoPages/      # Static/marketing routes
├── types/              # TypeScript type definitions
└── templates/          # Layout templates (aside, header, etc.)
```

## API Module Pattern

Each feature has its own module under `frontend/src/api/modules/`:
```
workflows/
├── index.ts        # Exported functions
├── queries.ts      # TanStack Query hooks (useWorkflows, useWorkflow, etc.)
└── mutations.ts    # Mutations (useCreateWorkflow, useUpdateWorkflow, etc.)
```

## Auth

- Passport Bearer tokens stored in localStorage/cookie
- Axios interceptor attaches token to every request
- 401 responses trigger redirect to login

## Build

- `yarn dev` — development server with HMR
- `yarn build` — TypeScript compile + Vite build
- `yarn lint` — ESLint

## i18n

Supported locales: English (`en`), Spanish (`es`), Arabic (`ar`). Translations in `frontend/src/locales/`. Uses `i18next` + `react-i18next`.
