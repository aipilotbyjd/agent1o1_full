# LinkFlow — Product Overview

**TL;DR**: A SaaS workflow automation platform where users visually connect nodes on a canvas to automate tasks across third-party services — similar to Gumloop, n8n, and Zapier.

---

## What It Is

LinkFlow lets users build automated workflows without code. A workflow is a directed graph of **nodes** connected by edges. When triggered, the system executes nodes in order, passing data between them.

Competitors for positioning:
- **Gumloop** — AI-first automation, very visual
- **n8n** — open-source, self-hostable, 400+ integrations
- **Zapier** — market leader, Zaps, simple 2-step triggers

LinkFlow is not an exact clone of any of these — it draws inspiration from all three. See [[competitors]] for a detailed breakdown.

---

## Core Concepts

- **[[workspace]]** — top-level tenant; a user can own or be a member of multiple workspaces
- **[[workflow]]** — the automation: a graph of nodes and connections
- **[[node]]** — a single step in a workflow (trigger, action, logic, AI, etc.)
- **[[execution]]** — one run of a workflow
- **[[credential]]** — stored auth tokens for third-party services
- **[[webhook]]** — inbound HTTP triggers for workflows
- **[[agent]]** — AI agent that can be embedded in workflows
- **[[template]]** — pre-built workflow a user can fork

---

## Stack at a Glance

| Layer | Technology |
|-------|-----------|
| Backend | Laravel 12 / PHP 8.5 |
| Queue | Laravel Horizon (Redis) |
| Billing | Laravel Cashier (Stripe) |
| Frontend | React 19 + TypeScript + Vite |
| UI theme | Boltify (purchased admin template) |
| Canvas | @xyflow/react (React Flow) |
| Styling | Tailwind CSS v4 |
| State/data | TanStack Query |

---

## Key Product Areas

1. **Workflow Builder** — visual canvas editor (`frontend/src/pages/editor/WorkflowEditor/`)
2. **Execution Engine** — runs workflows, tracks status, logs each node
3. **Node Registry** — catalog of available node types by category
4. **Credentials Vault** — encrypted storage for OAuth tokens, API keys
5. **Workspace & Teams** — multi-tenant, roles, invitations
6. **Billing & Credits** — plans, credit packs, usage snapshots
7. **Agents** — AI agents as first-class workflow participants
8. **Templates** — shareable workflow blueprints

---

## Current Status

Project is in active development. Backend has models, migrations, and API routes. Frontend has the Boltify theme wired up with API modules for all major entities.

*Last updated: 2026-05-09*
