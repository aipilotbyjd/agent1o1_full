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

Model: `backend/app/Models/Agent.php`, `AgentTrigger.php`, `AgentToolConfig.php`
Frontend types: `frontend/src/types/agent.type.ts`
API module: `frontend/src/api/modules/agents/`
