---
type: entity
status: sourced
sources: 2
last_updated: 2026-05-09
tags: [entity, agent, ai]
---

# Agent

**TL;DR**: An AI agent that can participate in workflows as a first-class node — with tools, skills, memory, and its own trigger conditions.

---

## Key Fields

| Field | Type | Notes |
|-------|------|-------|
| `id` | UUID | |
| `workspace_id` | FK | |
| `name` | string | |
| `description` | string? | |
| `system_prompt` | text? | The agent's system instructions |
| `model` | string | LLM model identifier |
| `is_active` | bool | |

## Relationships

- belongs to `Workspace`
- has many `AgentTrigger` — conditions that activate the agent
- has many `AgentToolConfig` — tools the agent can use
- has many `AgentSkill` — reusable skill definitions
- has many `AgentSkillReference` — references to skills from a library
- has many `AgentSkillScript` — custom code skills
- has many `AiAgentStep` — individual reasoning steps in an execution

## AgentTrigger

Defines when the agent activates: webhook, schedule, manual, or within a workflow node.

## AgentToolConfig

Configures which tools the agent can call — HTTP requests, workflow executions, database queries, etc.

## AgentSkill

Named, reusable instructions or capabilities. `AgentSkillReference` points to a shared skill; `AgentSkillScript` is a code-based skill.

## Relationship to Workflows

An agent can be embedded in a workflow via an `ai` category node. The agent runs as a step, receives input data, and returns output data — like any other node.

## Conversation Interface

Agents also support a direct conversation mode (`AgentConversationController`), separate from workflow executions. This allows a chat-style interface with the agent.

## AI Logging

`AiGenerationLog` tracks every LLM call made during agent execution for usage accounting and debugging.

## API

| Method | Path | Action |
|--------|------|--------|
| GET | `/workspaces/{id}/agents` | List |
| POST | `/workspaces/{id}/agents` | Create |
| GET | `/workspaces/{id}/agents/{id}` | Get |
| PUT | `/workspaces/{id}/agents/{id}` | Update |
| DELETE | `/workspaces/{id}/agents/{id}` | Delete |
| POST | `/workspaces/{id}/agents/{id}/duplicate` | Clone |
| GET | `/workspaces/{id}/agents/{id}/triggers` | List triggers |
| POST | `/workspaces/{id}/agents/{id}/triggers` | Create trigger |
| PUT | `/workspaces/{id}/agents/{id}/triggers/{tid}` | Update trigger |
| DELETE | `/workspaces/{id}/agents/{id}/triggers/{tid}` | Delete trigger |
| POST | `/workspaces/{id}/agents/{id}/triggers/{tid}/fire` | Manually fire trigger |
| GET | `/workspaces/{id}/agents/{id}/conversations` | List conversations |
| POST | `/workspaces/{id}/agents/{id}/conversations` | Start conversation |
| GET | `/workspaces/{id}/agents/{id}/conversations/{cid}` | Get conversation |
| DELETE | `/workspaces/{id}/agents/{id}/conversations/{cid}` | Delete conversation |
| POST | `/workspaces/{id}/agents/{id}/conversations/{cid}/messages` | Send message |
| GET | `/workspaces/{id}/agent-skills` | List workspace skills |
| POST | `/workspaces/{id}/agent-skills` | Create skill |
| GET | `/workspaces/{id}/agent-skills/{sid}` | Get skill |
| PUT | `/workspaces/{id}/agent-skills/{sid}` | Update skill |
| DELETE | `/workspaces/{id}/agent-skills/{sid}` | Delete skill |
| POST | `/workspaces/{id}/agents/{id}/skills/attach` | Attach skill to agent |
| DELETE | `/workspaces/{id}/agents/{id}/skills/{sid}` | Detach skill |
| POST | `/workspaces/{id}/agent-skills/{sid}/references` | Add reference to skill |
| PUT | `/workspaces/{id}/agent-skills/{sid}/references/{rid}` | Update reference |
| DELETE | `/workspaces/{id}/agent-skills/{sid}/references/{rid}` | Remove reference |
| POST | `/workspaces/{id}/agent-skills/{sid}/scripts` | Add script to skill |
| PUT | `/workspaces/{id}/agent-skills/{sid}/scripts/{scid}` | Update script |
| DELETE | `/workspaces/{id}/agent-skills/{sid}/scripts/{scid}` | Remove script |

Model: `backend/app/Models/Agent.php`, `AgentTrigger.php`, `AgentToolConfig.php`
Frontend types: `frontend/src/types/agent.type.ts`
API module: `frontend/src/api/modules/agents/`

---

## Sources

- `raw/api-routes-2026-05-09.txt` — confirms agent CRUD + skills attach/detach + agent-skills workspace-level CRUD with references and scripts sub-resources + conversations + triggers + fire endpoint
- `raw/frontend-api-modules-2026-05-09.txt` — confirms `agents/` API module
- `backend/app/Models/Agent.php`, `AgentTrigger.php`, `AgentToolConfig.php` — code references
- *(no external/customer sources yet — flag for ingestion: agent UX research, comparable-product agent docs)*
