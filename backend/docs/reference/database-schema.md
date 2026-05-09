# 🗄️ Database Schema Reference

**Complete database schema documentation for LinkFlow**

---

## Overview

**Database:** PostgreSQL 14+  
**Extensions:** pgvector (for AI/RAG features)  
**ORM:** Laravel Eloquent  
**Migration System:** Laravel Migrations

**Total Tables:** 40+  
**Data Isolation:** Row-level via `workspace_id`

---

## Table of Contents

1. [Core Tables](#core-tables)
2. [Workflow Tables](#workflow-tables)
3. [Execution Tables](#execution-tables)
4. [Credential & Security Tables](#credential--security-tables)
5. [Team & Collaboration Tables](#team--collaboration-tables)
6. [AI & Agent Tables](#ai--agent-tables)
7. [Notification Tables](#notification-tables)
8. [Billing Tables](#billing-tables)
9. [System Tables](#system-tables)
10. [Indexes & Performance](#indexes--performance)
11. [Relationships Diagram](#relationships-diagram)

---

## Core Tables

### users

**Purpose:** User accounts and authentication

```sql
CREATE TABLE users (
    id UUID PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    email VARCHAR(255) UNIQUE NOT NULL,
    email_verified_at TIMESTAMP NULL,
    password VARCHAR(255) NOT NULL,
    avatar TEXT NULL,
    timezone VARCHAR(50) DEFAULT 'UTC',
    locale VARCHAR(10) DEFAULT 'en',
    remember_token VARCHAR(100) NULL,
    created_at TIMESTAMP,
    updated_at TIMESTAMP
);

CREATE INDEX idx_users_email ON users(email);
```

**Key Fields:**
- `id` - UUID primary key
- `email` - Unique, used for login
- `email_verified_at` - NULL until email is verified
- `avatar` - Profile picture URL
- `timezone` - User's timezone for scheduling

---

### workspaces

**Purpose:** Multi-tenant workspace containers

```sql
CREATE TABLE workspaces (
    id UUID PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    slug VARCHAR(255) UNIQUE NOT NULL,
    description TEXT NULL,
    settings JSONB DEFAULT '{}',
    is_active BOOLEAN DEFAULT true,
    created_at TIMESTAMP,
    updated_at TIMESTAMP
);

CREATE INDEX idx_workspaces_slug ON workspaces(slug);
CREATE INDEX idx_workspaces_is_active ON workspaces(is_active);
```

**Settings JSONB Structure:**
```json
{
  "timezone": "America/New_York",
  "default_workflow_timeout": 300,
  "execution_retention_days": 30,
  "allow_public_workflows": false,
  "require_2fa": false,
  "ip_whitelist": ["192.168.1.0/24"],
  "error_workflow_id": "uuid"
}
```

---

### workspace_members

**Purpose:** User-workspace relationships with roles

```sql
CREATE TABLE workspace_members (
    id UUID PRIMARY KEY,
    workspace_id UUID NOT NULL REFERENCES workspaces(id) ON DELETE CASCADE,
    user_id UUID NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    role VARCHAR(50) NOT NULL, -- owner, admin, editor, viewer
    permissions JSONB DEFAULT '{}',
    joined_at TIMESTAMP NOT NULL,
    created_at TIMESTAMP,
    updated_at TIMESTAMP,
    
    UNIQUE(workspace_id, user_id)
);

CREATE INDEX idx_workspace_members_workspace ON workspace_members(workspace_id);
CREATE INDEX idx_workspace_members_user ON workspace_members(user_id);
CREATE INDEX idx_workspace_members_role ON workspace_members(workspace_id, role);
```

**Roles:**
- `owner` - Full control, can delete workspace
- `admin` - Manage workspace, members, billing
- `editor` - Create/edit workflows and credentials
- `viewer` - Read-only access

---

## Workflow Tables

### workflows

**Purpose:** Workflow definitions (automation blueprints)

```sql
CREATE TABLE workflows (
    id UUID PRIMARY KEY,
    workspace_id UUID NOT NULL REFERENCES workspaces(id) ON DELETE CASCADE,
    name VARCHAR(255) NOT NULL,
    description TEXT NULL,
    definition JSONB NOT NULL,
    settings JSONB DEFAULT '{}',
    is_active BOOLEAN DEFAULT false,
    created_by UUID REFERENCES users(id) ON DELETE SET NULL,
    updated_by UUID REFERENCES users(id) ON DELETE SET NULL,
    created_at TIMESTAMP,
    updated_at TIMESTAMP,
    deleted_at TIMESTAMP NULL
);

CREATE INDEX idx_workflows_workspace ON workflows(workspace_id);
CREATE INDEX idx_workflows_is_active ON workflows(workspace_id, is_active);
CREATE INDEX idx_workflows_created_by ON workflows(created_by);
CREATE INDEX idx_workflows_created_at ON workflows(workspace_id, created_at DESC);

-- GIN index for JSONB queries
CREATE INDEX idx_workflows_definition ON workflows USING GIN(definition);
```

**Definition JSONB Structure:**
```json
{
  "nodes": [
    {
      "id": "node_uuid",
      "type": "trigger.webhook",
      "position": {"x": 100, "y": 100},
      "config": {
        "http_method": "POST"
      }
    }
  ],
  "connections": [
    {
      "from": "node1",
      "to": "node2",
      "output_index": 0
    }
  ]
}
```

**Settings JSONB Structure:**
```json
{
  "timeout": 300,
  "max_retries": 0,
  "error_workflow_id": null,
  "save_execution_data": true
}
```

---

### workflow_versions

**Purpose:** Workflow version history

```sql
CREATE TABLE workflow_versions (
    id UUID PRIMARY KEY,
    workflow_id UUID NOT NULL REFERENCES workflows(id) ON DELETE CASCADE,
    version_number INTEGER NOT NULL,
    name VARCHAR(255) NULL,
    definition JSONB NOT NULL,
    is_published BOOLEAN DEFAULT false,
    created_by UUID REFERENCES users(id) ON DELETE SET NULL,
    created_at TIMESTAMP,
    
    UNIQUE(workflow_id, version_number)
);

CREATE INDEX idx_workflow_versions_workflow ON workflow_versions(workflow_id, version_number DESC);
```

---

### tags

**Purpose:** Workflow organization

```sql
CREATE TABLE tags (
    id UUID PRIMARY KEY,
    workspace_id UUID NOT NULL REFERENCES workspaces(id) ON DELETE CASCADE,
    name VARCHAR(100) NOT NULL,
    color VARCHAR(7) DEFAULT '#3B82F6',
    created_at TIMESTAMP,
    
    UNIQUE(workspace_id, name)
);

CREATE TABLE workflow_tag (
    workflow_id UUID REFERENCES workflows(id) ON DELETE CASCADE,
    tag_id UUID REFERENCES tags(id) ON DELETE CASCADE,
    PRIMARY KEY (workflow_id, tag_id)
);
```

---

## Execution Tables

### executions

**Purpose:** Workflow execution instances (runs)

```sql
CREATE TABLE executions (
    id UUID PRIMARY KEY,
    workflow_id UUID NOT NULL REFERENCES workflows(id) ON DELETE CASCADE,
    workspace_id UUID NOT NULL REFERENCES workspaces(id) ON DELETE CASCADE,
    triggered_by UUID REFERENCES users(id) ON DELETE SET NULL,
    mode VARCHAR(50) NOT NULL, -- webhook, manual, schedule, polling
    status VARCHAR(50) NOT NULL, -- running, success, failed, cancelled, waiting
    input_data JSONB NULL,
    output_data JSONB NULL,
    error_data JSONB NULL,
    checkpoint_data JSONB NULL, -- For resumption
    started_at TIMESTAMP NULL,
    finished_at TIMESTAMP NULL,
    duration_seconds INTEGER NULL,
    credits_used INTEGER DEFAULT 0,
    created_at TIMESTAMP,
    updated_at TIMESTAMP
);

CREATE INDEX idx_executions_workflow ON executions(workflow_id, created_at DESC);
CREATE INDEX idx_executions_workspace ON executions(workspace_id, created_at DESC);
CREATE INDEX idx_executions_status ON executions(status, created_at DESC);
CREATE INDEX idx_executions_triggered_by ON executions(triggered_by);
CREATE INDEX idx_executions_workspace_status ON executions(workspace_id, status);
```

**Status Values:**
- `running` - Currently executing
- `success` - Completed successfully
- `failed` - Error occurred
- `cancelled` - Manually cancelled
- `waiting` - Suspended (waiting for webhook/approval)

**Mode Values:**
- `webhook` - Triggered by webhook
- `manual` - User clicked execute
- `schedule` - Cron schedule
- `polling` - Polling trigger found new data

---

### execution_nodes (or node_executions)

**Purpose:** Individual node execution results

```sql
CREATE TABLE execution_nodes (
    id UUID PRIMARY KEY,
    execution_id UUID NOT NULL REFERENCES executions(id) ON DELETE CASCADE,
    node_id VARCHAR(255) NOT NULL,
    node_type VARCHAR(100) NOT NULL,
    input_data JSONB NULL,
    output_data JSONB NULL,
    error_data JSONB NULL,
    status VARCHAR(50) NOT NULL,
    started_at TIMESTAMP NULL,
    finished_at TIMESTAMP NULL,
    duration_ms INTEGER NULL,
    credits_used INTEGER DEFAULT 0,
    created_at TIMESTAMP
);

CREATE INDEX idx_execution_nodes_execution ON execution_nodes(execution_id, created_at);
CREATE INDEX idx_execution_nodes_node ON execution_nodes(node_id);
```

---

### execution_logs

**Purpose:** Detailed execution logs for debugging

```sql
CREATE TABLE execution_logs (
    id UUID PRIMARY KEY,
    execution_id UUID NOT NULL REFERENCES executions(id) ON DELETE CASCADE,
    node_id VARCHAR(255) NULL,
    level VARCHAR(20) NOT NULL, -- debug, info, warning, error
    message TEXT NOT NULL,
    context JSONB NULL,
    created_at TIMESTAMP
);

CREATE INDEX idx_execution_logs_execution ON execution_logs(execution_id, created_at);
CREATE INDEX idx_execution_logs_level ON execution_logs(level, created_at DESC);
```

---

## Credential & Security Tables

### credentials

**Purpose:** Encrypted API keys and OAuth tokens

```sql
CREATE TABLE credentials (
    id UUID PRIMARY KEY,
    workspace_id UUID NOT NULL REFERENCES workspaces(id) ON DELETE CASCADE,
    name VARCHAR(255) NOT NULL,
    type VARCHAR(100) NOT NULL, -- sendgrid, stripe, oauth_google, etc.
    data TEXT NOT NULL, -- Encrypted JSON
    is_oauth BOOLEAN DEFAULT false,
    oauth_data JSONB NULL,
    created_at TIMESTAMP,
    updated_at TIMESTAMP
);

CREATE INDEX idx_credentials_workspace ON credentials(workspace_id);
CREATE INDEX idx_credentials_type ON credentials(workspace_id, type);
```

**Data Field:** Encrypted using Laravel's `encrypted` cast

---

### variables

**Purpose:** Workspace-wide key-value variables

```sql
CREATE TABLE variables (
    id UUID PRIMARY KEY,
    workspace_id UUID NOT NULL REFERENCES workspaces(id) ON DELETE CASCADE,
    key VARCHAR(255) NOT NULL,
    value TEXT NOT NULL,
    type VARCHAR(50) DEFAULT 'string', -- string, number, boolean, json
    is_secret BOOLEAN DEFAULT false,
    description TEXT NULL,
    created_at TIMESTAMP,
    updated_at TIMESTAMP,
    
    UNIQUE(workspace_id, key)
);

CREATE INDEX idx_variables_workspace ON variables(workspace_id);
CREATE INDEX idx_variables_key ON variables(workspace_id, key);
```

---

## Team & Collaboration Tables

### invitations

**Purpose:** Pending workspace invitations

```sql
CREATE TABLE invitations (
    id UUID PRIMARY KEY,
    workspace_id UUID NOT NULL REFERENCES workspaces(id) ON DELETE CASCADE,
    email VARCHAR(255) NOT NULL,
    role VARCHAR(50) NOT NULL,
    token VARCHAR(255) UNIQUE NOT NULL,
    invited_by UUID REFERENCES users(id) ON DELETE SET NULL,
    expires_at TIMESTAMP NOT NULL,
    accepted_at TIMESTAMP NULL,
    created_at TIMESTAMP,
    updated_at TIMESTAMP
);

CREATE INDEX idx_invitations_workspace ON invitations(workspace_id);
CREATE INDEX idx_invitations_email ON invitations(email);
CREATE INDEX idx_invitations_token ON invitations(token);
```

---

### activity_logs

**Purpose:** Audit trail of all user actions

```sql
CREATE TABLE activity_logs (
    id UUID PRIMARY KEY,
    workspace_id UUID NOT NULL REFERENCES workspaces(id) ON DELETE CASCADE,
    user_id UUID REFERENCES users(id) ON DELETE SET NULL,
    action VARCHAR(100) NOT NULL, -- created, updated, deleted, executed
    resource_type VARCHAR(100) NOT NULL, -- workflow, execution, credential, etc.
    resource_id UUID NULL,
    resource_name VARCHAR(255) NULL,
    description TEXT NULL,
    metadata JSONB NULL,
    ip_address VARCHAR(45) NULL,
    user_agent TEXT NULL,
    created_at TIMESTAMP
);

CREATE INDEX idx_activity_logs_workspace ON activity_logs(workspace_id, created_at DESC);
CREATE INDEX idx_activity_logs_user ON activity_logs(user_id, created_at DESC);
CREATE INDEX idx_activity_logs_resource ON activity_logs(resource_type, resource_id);
```

---

## AI & Agent Tables

### agents

**Purpose:** AI conversational agents

```sql
CREATE TABLE agents (
    id UUID PRIMARY KEY,
    workspace_id UUID NOT NULL REFERENCES workspaces(id) ON DELETE CASCADE,
    name VARCHAR(255) NOT NULL,
    description TEXT NULL,
    model VARCHAR(100) NOT NULL, -- gpt-4o, claude-sonnet-4, etc.
    system_prompt TEXT NULL,
    temperature DECIMAL(3,2) DEFAULT 0.7,
    max_tokens INTEGER DEFAULT 1000,
    is_active BOOLEAN DEFAULT true,
    config JSONB DEFAULT '{}',
    created_at TIMESTAMP,
    updated_at TIMESTAMP
);

CREATE INDEX idx_agents_workspace ON agents(workspace_id);
```

---

### agent_skills

**Purpose:** Agent capabilities (API calls, RAG, workflows)

```sql
CREATE TABLE agent_skills (
    id UUID PRIMARY KEY,
    workspace_id UUID NOT NULL REFERENCES workspaces(id) ON DELETE CASCADE,
    name VARCHAR(255) NOT NULL,
    type VARCHAR(50) NOT NULL, -- api_call, vector_search, workflow, script
    description TEXT NULL,
    config JSONB NOT NULL,
    created_at TIMESTAMP,
    updated_at TIMESTAMP
);

CREATE TABLE agent_agent_skill (
    agent_id UUID REFERENCES agents(id) ON DELETE CASCADE,
    agent_skill_id UUID REFERENCES agent_skills(id) ON DELETE CASCADE,
    PRIMARY KEY (agent_id, agent_skill_id)
);
```

---

### agent_conversations

**Purpose:** Agent chat conversations

```sql
CREATE TABLE agent_conversations (
    id UUID PRIMARY KEY,
    agent_id UUID NOT NULL REFERENCES agents(id) ON DELETE CASCADE,
    workspace_id UUID NOT NULL REFERENCES workspaces(id) ON DELETE CASCADE,
    session_id VARCHAR(255) NOT NULL,
    messages JSONB NOT NULL DEFAULT '[]',
    metadata JSONB NULL,
    started_at TIMESTAMP NOT NULL,
    last_message_at TIMESTAMP NULL,
    created_at TIMESTAMP
);

CREATE INDEX idx_agent_conversations_agent ON agent_conversations(agent_id, created_at DESC);
CREATE INDEX idx_agent_conversations_session ON agent_conversations(session_id);
```

**Messages JSONB Structure:**
```json
[
  {
    "role": "user",
    "content": "What's the status of order #12345?",
    "created_at": "2024-01-15T10:00:00Z"
  },
  {
    "role": "assistant",
    "content": "Let me check that for you...",
    "skill_used": "Order Lookup",
    "created_at": "2024-01-15T10:00:05Z"
  }
]
```

---

### document_embeddings

**Purpose:** RAG vector storage (using pgvector)

```sql
CREATE EXTENSION IF NOT EXISTS vector;

CREATE TABLE document_embeddings (
    id UUID PRIMARY KEY,
    workspace_id UUID NOT NULL REFERENCES workspaces(id) ON DELETE CASCADE,
    collection VARCHAR(255) NOT NULL,
    content TEXT NOT NULL,
    metadata JSONB NULL,
    embedding vector(1536), -- OpenAI text-embedding-3-small dimension
    created_at TIMESTAMP
);

CREATE INDEX idx_document_embeddings_workspace ON document_embeddings(workspace_id);
CREATE INDEX idx_document_embeddings_collection ON document_embeddings(workspace_id, collection);

-- Vector similarity search index (HNSW for performance)
CREATE INDEX idx_document_embeddings_vector 
ON document_embeddings 
USING hnsw (embedding vector_cosine_ops);
```

---

## Notification Tables

### notifications

**Purpose:** User notifications

```sql
CREATE TABLE notifications (
    id UUID PRIMARY KEY,
    user_id UUID NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    type VARCHAR(100) NOT NULL,
    title VARCHAR(255) NOT NULL,
    message TEXT NOT NULL,
    data JSONB NULL,
    is_read BOOLEAN DEFAULT false,
    read_at TIMESTAMP NULL,
    created_at TIMESTAMP
);

CREATE INDEX idx_notifications_user ON notifications(user_id, created_at DESC);
CREATE INDEX idx_notifications_unread ON notifications(user_id, is_read);
```

---

## Billing Tables

### subscriptions

**Purpose:** Stripe subscriptions

```sql
CREATE TABLE subscriptions (
    id UUID PRIMARY KEY,
    workspace_id UUID NOT NULL REFERENCES workspaces(id) ON DELETE CASCADE,
    stripe_subscription_id VARCHAR(255) UNIQUE NOT NULL,
    stripe_customer_id VARCHAR(255) NOT NULL,
    stripe_price_id VARCHAR(255) NOT NULL,
    status VARCHAR(50) NOT NULL,
    trial_ends_at TIMESTAMP NULL,
    ends_at TIMESTAMP NULL,
    created_at TIMESTAMP,
    updated_at TIMESTAMP
);

CREATE INDEX idx_subscriptions_workspace ON subscriptions(workspace_id);
```

---

### credit_transactions

**Purpose:** Credit purchases and usage

```sql
CREATE TABLE credit_transactions (
    id UUID PRIMARY KEY,
    workspace_id UUID NOT NULL REFERENCES workspaces(id) ON DELETE CASCADE,
    type VARCHAR(50) NOT NULL, -- purchase, usage, refund
    amount INTEGER NOT NULL, -- Positive for purchase, negative for usage
    description TEXT NOT NULL,
    execution_id UUID REFERENCES executions(id) ON DELETE SET NULL,
    metadata JSONB NULL,
    created_at TIMESTAMP
);

CREATE INDEX idx_credit_transactions_workspace ON credit_transactions(workspace_id, created_at DESC);
```

---

## System Tables

### nodes

**Purpose:** Available node types catalog

```sql
CREATE TABLE nodes (
    id UUID PRIMARY KEY,
    type VARCHAR(255) UNIQUE NOT NULL,
    category_id UUID REFERENCES node_categories(id),
    name VARCHAR(255) NOT NULL,
    description TEXT NULL,
    icon VARCHAR(100) NULL,
    color VARCHAR(7) NULL,
    config_schema JSONB NOT NULL,
    output_schema JSONB NULL,
    is_active BOOLEAN DEFAULT true,
    version VARCHAR(20) DEFAULT '1.0.0',
    created_at TIMESTAMP,
    updated_at TIMESTAMP
);

CREATE INDEX idx_nodes_type ON nodes(type);
CREATE INDEX idx_nodes_category ON nodes(category_id);
```

---

### webhooks

**Purpose:** Webhook trigger endpoints

```sql
CREATE TABLE webhooks (
    id UUID PRIMARY KEY,
    workflow_id UUID UNIQUE NOT NULL REFERENCES workflows(id) ON DELETE CASCADE,
    workspace_id UUID NOT NULL REFERENCES workspaces(id) ON DELETE CASCADE,
    uuid VARCHAR(255) UNIQUE NOT NULL, -- Public webhook identifier
    method VARCHAR(10) DEFAULT 'POST',
    authentication JSONB NULL,
    is_active BOOLEAN DEFAULT true,
    request_count INTEGER DEFAULT 0,
    last_triggered_at TIMESTAMP NULL,
    created_at TIMESTAMP,
    updated_at TIMESTAMP
);

CREATE INDEX idx_webhooks_uuid ON webhooks(uuid);
CREATE INDEX idx_webhooks_workspace ON webhooks(workspace_id);
```

---

## Indexes & Performance

### Critical Indexes

**Most Queried Tables:**
1. `executions` - Filtered by workspace, workflow, status
2. `workflows` - Filtered by workspace, active status
3. `activity_logs` - Sorted by date DESC
4. `notifications` - Filtered by user, read status

**Index Strategy:**
- Composite indexes for common query patterns
- GIN indexes for JSONB columns frequently searched
- HNSW indexes for vector similarity (pgvector)

**Example Queries:**
```sql
-- Fast with idx_executions_workspace_status
SELECT * FROM executions 
WHERE workspace_id = 'uuid' 
AND status = 'running'
ORDER BY created_at DESC;

-- Fast with idx_workflows_definition (GIN)
SELECT * FROM workflows 
WHERE definition @> '{"nodes": [{"type": "apps.email"}]}'::jsonb;

-- Fast with idx_document_embeddings_vector (HNSW)
SELECT * FROM document_embeddings 
WHERE workspace_id = 'uuid' 
ORDER BY embedding <=> '[0.1, 0.2, ...]' 
LIMIT 5;
```

---

## Relationships Diagram

```
users
  │
  ├──▶ workspace_members ◀── workspaces
  │                              │
  │                              ├──▶ workflows
  │                              │      ├──▶ workflow_versions
  │                              │      ├──▶ webhooks
  │                              │      └──▶ workflow_tag ◀── tags
  │                              │
  │                              ├──▶ executions
  │                              │      ├──▶ execution_nodes
  │                              │      └──▶ execution_logs
  │                              │
  │                              ├──▶ credentials
  │                              ├──▶ variables
  │                              ├──▶ invitations
  │                              ├──▶ activity_logs
  │                              ├──▶ agents
  │                              │      ├──▶ agent_conversations
  │                              │      └──▶ agent_agent_skill ◀── agent_skills
  │                              │
  │                              └──▶ document_embeddings
  │
  └──▶ notifications
```

---

## Data Retention

**Execution Data:**
- Kept for 30 days by default (configurable per workspace)
- Archived executions moved to cold storage
- Logs can be streamed to external systems (Datadog, Splunk)

**Activity Logs:**
- Kept for 90 days
- Exported for compliance (GDPR, SOC2)

**Credentials:**
- Never deleted, only soft deleted
- Encrypted at rest with workspace-specific keys

---

## Backup Strategy

**PostgreSQL:**
- Daily full backups
- Point-in-time recovery (PITR)
- Cross-region replication

**Critical Tables:**
- `workflows` - Version controlled
- `credentials` - Encrypted backups
- `executions` - Archived to S3 after retention period

---

## Migration Commands

```bash
# Run all migrations
php artisan migrate

# Rollback last batch
php artisan migrate:rollback

# Fresh migration (DANGER: drops all tables)
php artisan migrate:fresh

# Seed database
php artisan db:seed

# Create new migration
php artisan make:migration create_my_table
```

---

**For query optimization, see [ARCHITECTURE_DEEP_DIVE.md](./ARCHITECTURE_DEEP_DIVE.md#database-architecture)**
