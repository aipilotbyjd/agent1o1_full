# 📘 LinkFlow Platform - Complete Project Overview

**Your Workflow Automation Platform - Everything You Need to Know**

---

## 🎯 What Is LinkFlow?

LinkFlow (also known as Agent1o1) is a **powerful workflow automation platform** similar to n8n, Zapier, or Make.com. It allows users to:

- **Build visual workflows** using a drag-and-drop node-based editor
- **Connect to 100+ services** (APIs, databases, AI models)
- **Automate business processes** without writing code
- **Create AI agents** that can have conversations and perform tasks
- **Schedule and trigger** workflows via webhooks, polling, or cron
- **Monitor executions** in real-time with detailed logs
- **Collaborate with teams** using workspaces and role-based access

### Real-World Use Cases

**Marketing Automation:**
- Send welcome email sequence when user signs up
- Post to social media on a schedule
- Generate reports and send to Slack

**Customer Support:**
- AI chatbot that answers common questions
- Auto-create tickets from emails
- Send satisfaction surveys after ticket resolution

**Data Processing:**
- Sync data between databases
- Process CSV files and generate reports
- Enrich CRM data from multiple sources

**Sales Automation:**
- Qualify leads automatically
- Schedule demos and send reminders
- Update CRM when deals close

---

## 🏗️ Architecture Overview

### High-Level Architecture

```
┌─────────────────────────────────────────────────────────────┐
│                     React Frontend                          │
│  (User builds workflows visually in the browser)            │
└───────────────────────┬─────────────────────────────────────┘
                        │ REST API (JSON)
                        ↓
┌─────────────────────────────────────────────────────────────┐
│                  Laravel Backend API                        │
│  - Authentication (JWT/Passport)                            │
│  - Workspace Management                                     │
│  - Workflow CRUD Operations                                 │
│  - Execution Triggers                                       │
└───────────────────────┬─────────────────────────────────────┘
                        │
                        ↓
┌─────────────────────────────────────────────────────────────┐
│              Workflow Engine (Native PHP)                   │
│  - Graph Compiler (converts workflow to execution graph)    │
│  - Node Executor (runs each node with dependencies)         │
│  - Suspension Handler (waits for webhooks/approvals)        │
│  - Checkpoint System (saves state for resume)               │
└───────────────────────┬─────────────────────────────────────┘
                        │
        ┌───────────────┼───────────────┐
        ↓               ↓               ↓
┌──────────────┐ ┌─────────────┐ ┌─────────────┐
│  PostgreSQL  │ │    Redis    │ │  Horizon    │
│  (Main DB)   │ │   (Cache)   │ │  (Queues)   │
└──────────────┘ └─────────────┘ └─────────────┘
```

### Technology Stack

**Backend:**
- **Laravel 12** (PHP 8.3) - Modern PHP framework
- **PostgreSQL** - Main database with pgvector extension for AI
- **Redis** - Caching and queue backend
- **Horizon** - Queue monitoring dashboard
- **Passport** - OAuth2 authentication
- **Cashier** - Stripe billing integration

**Frontend (Separate):**
- **React 18** - UI framework
- **React Flow** - Visual workflow canvas
- **React Query** - Data fetching and caching
- **Tailwind CSS** - Styling
- **Vite** - Build tool

**Infrastructure:**
- **Docker** - Containerization
- **Supervisor** - Process management
- **Nginx** - Web server
- **Stripe** - Payment processing

---

## 📂 Project Structure Explained

```
/app/
├── 📁 app/                         # Main application code
│   ├── 📁 Agents/                  # AI Agent system (LLM integrations)
│   ├── 📁 Console/                 # CLI commands (cron jobs, maintenance)
│   ├── 📁 Engine/                  # ⭐ Workflow execution engine
│   │   ├── 📁 Execution/           # Node execution, scheduling, state
│   │   ├── 📁 Graph/               # Workflow graph compiler
│   │   ├── 📁 Nodes/               # All workflow node implementations
│   │   │   ├── 📁 Apps/            # App integrations (HTTP, Email, etc.)
│   │   │   ├── 📁 Core/            # Core nodes (Trigger, Merge, etc.)
│   │   │   ├── 📁 Flow/            # Flow control (If, Loop, Switch)
│   │   │   └── 📁 Concerns/        # Shared node traits
│   │   ├── WorkflowEngine.php      # Main engine orchestrator
│   │   └── RunContext.php          # Execution context/state
│   ├── 📁 Enums/                   # Type-safe enums (Status, Role, etc.)
│   ├── 📁 Events/                  # Laravel events
│   ├── 📁 Exceptions/              # Custom exceptions
│   ├── 📁 Http/                    # Controllers, Middleware, Requests
│   │   ├── 📁 Controllers/Api/V1/  # REST API endpoints
│   │   ├── 📁 Middleware/          # Auth, workspace context, etc.
│   │   └── 📁 Requests/            # Form validation requests
│   ├── 📁 Jobs/                    # Background jobs (queued tasks)
│   │   ├── ExecuteWorkflowJob.php  # Main workflow execution job
│   │   └── ResumeWorkflowJob.php   # Resume suspended workflows
│   ├── 📁 Listeners/               # Event listeners
│   ├── 📁 Mail/                    # Email templates
│   ├── 📁 Models/                  # Eloquent models (database tables)
│   │   ├── Workflow.php            # Workflow definition
│   │   ├── Execution.php           # Workflow run instance
│   │   ├── Workspace.php           # Multi-tenant workspace
│   │   ├── User.php                # User accounts
│   │   ├── Credential.php          # Stored API keys/secrets
│   │   └── ...                     # 30+ other models
│   ├── 📁 Notifications/           # User notifications
│   ├── 📁 Observers/               # Model observers (auto-actions)
│   ├── 📁 Providers/               # Service providers
│   ├── 📁 Services/                # Business logic services
│   │   ├── ExecutionService.php    # Execution management
│   │   ├── WorkflowService.php     # Workflow CRUD
│   │   ├── WebhookService.php      # Webhook handling
│   │   └── ...                     # 20+ other services
│   └── 📁 Traits/                  # Reusable PHP traits
│
├── 📁 config/                      # Configuration files
│   ├── workflow.php                # Workflow engine config
│   ├── ai.php                      # AI/LLM settings
│   ├── database.php                # DB connections
│   └── ...                         # Laravel configs
│
├── 📁 database/                    # Database related
│   ├── 📁 migrations/              # Database schema migrations
│   ├── 📁 seeders/                 # Data seeders
│   │   └── NodeSeeder.php          # Seeds all available nodes
│   └── 📁 factories/               # Test data factories
│
├── 📁 routes/                      # API and web routes
│   ├── api.php                     # ⭐ All REST API endpoints
│   ├── web.php                     # Web routes (minimal)
│   └── console.php                 # Scheduled tasks
│
├── 📁 docs/                        # 📚 Documentation (200+ pages!)
│   ├── REACT_FRONTEND_INTEGRATION.md
│   ├── WORKFLOW_ENGINE_GUIDE.md
│   ├── WEBHOOK_ARCHITECTURE.md
│   └── frontend-integration/       # 15 detailed modules
│
├── 📁 tests/                       # Automated tests
│   ├── 📁 Feature/                 # Integration tests
│   └── 📁 Unit/                    # Unit tests
│
├── 📁 storage/                     # Runtime files
│   ├── 📁 app/                     # Uploaded files
│   ├── 📁 logs/                    # Application logs
│   └── 📁 framework/               # Cache, sessions
│
└── 📁 public/                      # Web root (entry point)
    └── index.php                   # Laravel entry point
```

---

## 🔄 How a Workflow Executes (Step-by-Step)

### Example: "Send Welcome Email When User Signs Up"

**Step 1: User Creates Workflow in Frontend**
```
User drags nodes onto canvas:
  [Webhook Trigger] → [HTTP Request: Get User Data] → [Send Email]
  
User configures:
  - Webhook URL gets generated
  - HTTP node points to user API
  - Email node has template and credentials
  
User clicks "Save" and "Activate"
```

**Step 2: Workflow Gets Stored in Database**
```sql
workflows table:
  id: uuid
  name: "User Welcome Flow"
  definition: {nodes: [...], connections: [...]}
  is_active: true
  workspace_id: ...
```

**Step 3: External Service Calls Webhook**
```bash
POST https://yourapp.com/api/v1/webhook/abc123
Body: {"user_id": 12345, "email": "user@example.com"}
```

**Step 4: Webhook Receives Request**
```php
// WebhookReceiverController.php
public function handle($uuid) {
    $webhook = Webhook::where('uuid', $uuid)->firstOrFail();
    $workflow = $webhook->workflow;
    
    // Create execution record
    ExecutionService::trigger(
        workflow: $workflow,
        mode: ExecutionMode::Webhook,
        input: request()->all()
    );
}
```

**Step 5: Execution Job Gets Dispatched**
```php
// ExecutionService.php
public function trigger($workflow, $mode, $input) {
    $execution = Execution::create([
        'workflow_id' => $workflow->id,
        'mode' => $mode,
        'input_data' => $input,
        'status' => ExecutionStatus::Running
    ]);
    
    ExecuteWorkflowJob::dispatch($execution);
}
```

**Step 6: Workflow Engine Runs**
```php
// WorkflowEngine.php
public function run(Execution $execution) {
    // 1. Compile workflow into execution graph
    $graph = GraphCompiler::compile($execution->workflow->definition);
    
    // 2. Create run context (holds state)
    $context = new RunContext($execution, $graph);
    
    // 3. Schedule nodes based on dependencies
    while ($node = $this->scheduler->nextNode($context)) {
        // 4. Execute node
        $result = $this->executeNode($node, $context);
        
        // 5. Save result to database
        $this->batchWriter->write($node->id, $result);
        
        // 6. Check if node suspended (e.g., Wait node)
        if ($result->isSuspended()) {
            $this->suspend($context);
            return;
        }
    }
    
    // 7. All nodes complete
    $this->finalizer->complete($execution);
}
```

**Step 7: Each Node Executes**

**Node 1: Webhook Trigger**
```php
class WebhookTriggerNode extends BaseNode {
    public function execute($input, $context) {
        // Webhook trigger just passes input through
        return [
            'user_id' => $input['user_id'],
            'email' => $input['email']
        ];
    }
}
```

**Node 2: HTTP Request**
```php
class HttpRequestNode extends BaseNode {
    public function execute($input, $context) {
        $url = "https://api.example.com/users/{$input['user_id']}";
        $response = Http::get($url);
        
        return [
            'user' => $response->json(),
            'status_code' => $response->status()
        ];
    }
}
```

**Node 3: Send Email**
```php
class EmailNode extends BaseNode {
    public function execute($input, $context) {
        $credential = $this->getCredential('sendgrid');
        
        Mail::to($input['user']['email'])
            ->send(new WelcomeEmail($input['user']));
        
        return [
            'sent' => true,
            'timestamp' => now()
        ];
    }
}
```

**Step 8: Execution Completes**
```php
$execution->update([
    'status' => ExecutionStatus::Success,
    'finished_at' => now(),
    'output_data' => $finalOutput
]);

// Send notification to user
ExecutionCompletedEvent::dispatch($execution);
```

---

## 🧩 Core Concepts

### 1. **Workspaces** (Multi-Tenancy)

Each user belongs to one or more **workspaces**. Everything is scoped to a workspace:
- Workflows
- Executions
- Credentials
- Variables
- Team members

```php
// Example: User in multiple workspaces
User::find(1)->workspaces
  → ["Acme Corp", "Personal", "Client Project"]

// All data is workspace-scoped
Workspace::find(1)->workflows
  → Only workflows in "Acme Corp"
```

### 2. **Workflows** (Automation Blueprints)

A **workflow** is a saved configuration that defines:
- **Nodes**: The steps to execute (HTTP request, send email, if condition, etc.)
- **Connections**: How data flows between nodes
- **Triggers**: How the workflow starts (webhook, schedule, manual)
- **Settings**: Timeout, error handling, etc.

```json
{
  "nodes": [
    {"id": "node1", "type": "webhook_trigger", "position": {"x": 100, "y": 100}},
    {"id": "node2", "type": "http_request", "config": {"url": "..."}},
    {"id": "node3", "type": "email", "config": {"to": "..."}}
  ],
  "connections": [
    {"from": "node1", "to": "node2"},
    {"from": "node2", "to": "node3"}
  ]
}
```

### 3. **Executions** (Workflow Runs)

Each time a workflow runs, an **execution** record is created:
- Tracks status (running, success, failed)
- Stores input data
- Saves output from each node
- Records errors and logs
- Measures duration and credits used

```php
Execution {
  id: uuid,
  workflow_id: uuid,
  status: 'success',
  mode: 'webhook',
  input_data: {...},
  output_data: {...},
  started_at: '2024-01-15 10:00:00',
  finished_at: '2024-01-15 10:00:12',
  duration_seconds: 12
}
```

### 4. **Nodes** (Building Blocks)

**Nodes** are the individual steps in a workflow. LinkFlow has 60+ built-in nodes:

**Trigger Nodes:**
- Webhook Trigger
- Schedule Trigger (Cron)
- Polling Trigger
- Manual Trigger

**App Nodes:**
- HTTP Request
- Email (SMTP/SendGrid)
- Slack
- Discord
- Database Query (SQL)
- OpenAI (GPT)
- Google Sheets
- Stripe

**Flow Control Nodes:**
- If Condition
- Switch (multiple branches)
- Loop/Iterator
- Merge (combine data)
- Wait (delay or wait for webhook)

**Data Nodes:**
- JSON Parser
- Filter Array
- Transform Data
- Set Variable
- Code (JavaScript/Python)

**AI Nodes:**
- LLM Chat (OpenAI, Anthropic, Gemini)
- Document Loader
- Text Chunker
- Vector Store (RAG)
- RAG Query

### 5. **Credentials** (Secure API Keys)

**Credentials** store API keys and OAuth tokens securely:
- Encrypted at rest in database
- Referenced by name in nodes
- Support OAuth2 flow
- Can be tested before use

```php
Credential {
  id: uuid,
  name: "My SendGrid Account",
  type: "sendgrid",
  data: {
    api_key: "SG.encrypted..."
  },
  is_oauth: false
}
```

### 6. **Variables** (Workspace-Wide Values)

**Variables** are key-value pairs available to all workflows:
- Environment-specific values (API URLs, thresholds)
- Can be secrets (passwords, tokens)
- Referenced in nodes using `{{$vars.variable_name}}`

```php
Variable {
  key: "api_base_url",
  value: "https://api.prod.example.com",
  is_secret: false
}
```

### 7. **Tags** (Workflow Organization)

**Tags** help organize workflows:
- Production vs Testing
- By department (Marketing, Sales, Support)
- By customer/project

### 8. **AI Agents** (Conversational Bots)

**Agents** are AI-powered chatbots that can:
- Have multi-turn conversations
- Use skills (API calls, RAG search, trigger workflows)
- Be triggered by webhooks or events
- Remember conversation context

```php
Agent {
  name: "Support Bot",
  model: "gpt-4o",
  system_prompt: "You are a helpful support agent...",
  skills: ["Order Lookup", "Create Ticket", "KB Search"],
  temperature: 0.7
}
```

---

## 🚀 Key Features

### ✅ Visual Workflow Editor
- Drag-and-drop node canvas
- Real-time validation
- Connection routing
- Zoom and pan
- Sticky notes for documentation

### ✅ 60+ Built-in Nodes
- HTTP requests with auth
- Email (SMTP, SendGrid)
- Databases (PostgreSQL, MySQL, MongoDB)
- AI/LLM (OpenAI, Anthropic, Gemini)
- Cloud storage (S3, Google Drive)
- Messaging (Slack, Discord, Telegram)
- Payment (Stripe, PayPal)
- And many more...

### ✅ Execution Monitoring
- Real-time execution dashboard
- Detailed logs for each node
- Retry failed executions
- Cancel running executions
- Compare execution results
- Export execution data

### ✅ Multiple Trigger Types
- **Webhooks**: Receive HTTP requests
- **Schedules**: Cron-based timing
- **Polling**: Check APIs periodically
- **Manual**: Click-to-run
- **Events**: Internal system events

### ✅ Advanced Features
- **Versioning**: Save workflow versions, rollback
- **Sharing**: Public links to workflows
- **Templates**: Pre-built workflow library
- **Import/Export**: JSON format
- **Collaboration**: Team workspaces, roles
- **Credentials**: Secure OAuth2 integration

### ✅ AI & RAG Integration
- Document processing pipeline
- Vector embeddings (pgvector)
- Semantic search
- LLM orchestration
- Conversational agents

### ✅ Enterprise Features
- Multi-workspace (SaaS)
- Role-based access control
- Activity audit logs
- Git sync (backup to GitHub)
- Log streaming (Datadog, Splunk)
- Credit-based billing (Stripe)

---

## 🎮 User Journey

### First-Time User

1. **Sign Up** → Create account at `/register`
2. **Create Workspace** → "My First Workspace"
3. **Browse Templates** → Clone "Send Slack Alert" template
4. **Configure Nodes** → Add Slack credential
5. **Test Workflow** → Click "Execute" manually
6. **View Execution** → See real-time logs
7. **Activate Workflow** → Turn on webhook trigger
8. **Integrate** → Use webhook URL in external app

### Power User

1. **Complex Workflows** → Multi-branch conditions, loops
2. **Custom Credentials** → OAuth2 integrations
3. **Variables** → Environment-specific configs
4. **Versioning** → Save checkpoints, rollback
5. **Team Collaboration** → Invite team members
6. **Monitoring** → Analytics dashboard, alerts
7. **AI Agents** → Deploy conversational bots
8. **Git Sync** → Backup workflows to GitHub

---

## 💰 Business Model

### Credit-Based Pricing

- **Free Tier**: 1,000 credits/month
- **Starter**: $49/mo - 10,000 credits
- **Pro**: $199/mo - 50,000 credits
- **Enterprise**: Custom pricing

### Credit Costs

- **Simple Node** (HTTP, Email): 1-5 credits
- **AI/LLM Node**: 10-50 credits (based on tokens)
- **Database Query**: 2 credits
- **Code Execution**: 5 credits

### Stripe Integration

- Buy credits via Stripe Checkout
- Subscription billing
- Usage-based pricing
- Billing portal for invoices

---

## 🔐 Security

### Authentication
- Laravel Passport (OAuth2)
- JWT tokens with refresh
- Rate limiting on auth endpoints

### Authorization
- Role-based access control (RBAC)
- Workspace-level permissions
- Owner, Admin, Editor, Viewer roles

### Data Protection
- Credentials encrypted at rest (AES-256)
- HTTPS only
- CORS configured
- SQL injection prevention (Eloquent ORM)
- XSS protection
- CSRF tokens

### Multi-Tenancy
- Complete data isolation per workspace
- No cross-workspace data leaks
- Scoped queries with middleware

---

## 📊 Database Schema Highlights

### Core Tables

```sql
users              → User accounts
workspaces         → Multi-tenant workspaces
workspace_members  → User-workspace relationships

workflows          → Workflow definitions
executions         → Workflow run instances
node_executions    → Individual node results

credentials        → Stored API keys (encrypted)
variables          → Workspace variables
tags               → Workflow organization

webhooks           → Webhook endpoints
polling_triggers   → Polling configurations

agents             → AI conversational agents
agent_skills       → Agent capabilities
agent_conversations → Chat history

activity_logs      → Audit trail
notifications      → User notifications
```

### Relationships

```
Workspace
  → has many Workflows
  → has many Credentials
  → has many Variables
  → has many Members (Users)

Workflow
  → belongs to Workspace
  → has many Executions
  → has many Versions
  → has one Webhook (optional)

Execution
  → belongs to Workflow
  → has many NodeExecutions
  → belongs to User (who triggered)
```

---

## 🔧 Development Workflow

### Local Development Setup

```bash
# 1. Clone repository
git clone <repo>

# 2. Install dependencies
composer install

# 3. Copy environment file
cp .env.example .env

# 4. Generate app key
php artisan key:generate

# 5. Run migrations
php artisan migrate

# 6. Seed nodes and data
php artisan db:seed

# 7. Start queues (required for workflow execution)
php artisan horizon

# 8. Start server
php artisan serve
```

### Testing

```bash
# Run all tests
php artisan test

# Run specific test
php artisan test --filter=WorkflowExecutionTest

# Run with coverage
php artisan test --coverage
```

### Adding a New Node

```bash
# 1. Create node class
php artisan make:node MyCustomNode

# 2. Implement execute() method
# Edit: app/Engine/Nodes/Apps/MyCustomNode.php

# 3. Register in seeder
# Edit: database/seeders/NodeSeeder.php

# 4. Run seeder
php artisan db:seed --class=NodeSeeder
```

---

## 📚 Documentation Index

### For Developers
- **[WORKFLOW_ENGINE_GUIDE.md](../core/03-workflow-engine.md)** - Deep dive into engine
- **[WEBHOOK_ARCHITECTURE.md](../guides/webhooks.md)** - Webhook system
- **[AGENTS.md](../AGENTS.md)** - AI Agent system

### For Frontend Integration
- **[REACT_FRONTEND_INTEGRATION.md](../frontend/README.md)** - Main guide
- **[frontend-integration/](../frontend/modules/)** - 15 detailed modules

### For Product/Business
- **[PRICING_AND_ROLES.md](../planning/pricing-and-roles.md)** - Pricing structure
- **[PLATFORM_ENHANCEMENT_ROADMAP.md](../planning/roadmap.md)** - Future features

---

## 🎯 Next Steps

To get started understanding the codebase:

1. **Read this overview** - You're here! ✅
2. **Read [ARCHITECTURE_DEEP_DIVE.md](../core/02-architecture.md)** - Technical details
3. **Read [WORKFLOW_ENGINE_GUIDE.md](../core/03-workflow-engine.md)** - How workflows execute
4. **Explore [DEVELOPER_HANDBOOK.md](../core/04-developer-handbook.md)** - Common tasks
5. **Check [API_REFERENCE.md](../reference/api.md)** - All endpoints

---

**Your platform is production-ready and highly scalable! 🚀**
