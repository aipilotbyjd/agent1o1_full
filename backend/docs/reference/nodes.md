# 🧩 Node Reference - All Workflow Nodes

**Complete reference for all 60+ built-in workflow nodes**

---

## Table of Contents

1. [Trigger Nodes](#trigger-nodes)
2. [App Integration Nodes](#app-integration-nodes)
3. [Flow Control Nodes](#flow-control-nodes)
4. [Data Transformation Nodes](#data-transformation-nodes)
5. [AI & LLM Nodes](#ai--llm-nodes)
6. [RAG Nodes](#rag-nodes)
7. [Debug & Utility Nodes](#debug--utility-nodes)

---

## Node Categories

| Category | Count | Purpose |
|----------|-------|---------|
| Triggers | 4 | Start workflows |
| Apps | 15+ | External service integrations |
| Flow Control | 8 | Conditional logic, loops |
| Data | 10+ | Transform, filter, manipulate data |
| AI/LLM | 5 | AI model integrations |
| RAG | 4 | Document processing, vector search |
| Debug | 3 | Testing and logging |

---

## Trigger Nodes

Trigger nodes start workflow execution.

### 🔴 Webhook Trigger

**Type:** `trigger.webhook`  
**Category:** Triggers  
**Color:** #F59E0B

**Description:** Starts the workflow when an HTTP request is received.

**Configuration:**
```json
{
  \"http_method\": \"POST\",  // GET, POST, PUT, DELETE
  \"path\": \"/custom-path\",  // Optional custom path
  \"authentication\": \"none\"  // none, header, basic
}
```

**Output:**
```json
{
  \"headers\": { ... },  // Request headers
  \"body\": { ... },     // Request body
  \"query\": { ... }     // Query parameters
}
```

**Use Cases:**
- Receive webhooks from external services (Stripe, GitHub, etc.)
- API endpoints for mobile apps
- Integration points for third-party systems

---

### ⏰ Schedule Trigger

**Type:** `trigger.schedule`  
**Category:** Triggers  
**Color:** #F59E0B

**Description:** Starts the workflow on a recurring cron schedule.

**Configuration:**
```json
{
  \"cron\": \"*/5 * * * *\",  // Every 5 minutes
  \"timezone\": \"UTC\"
}
```

**Cron Examples:**
- `*/5 * * * *` - Every 5 minutes
- `0 * * * *` - Every hour
- `0 9 * * *` - Every day at 9 AM
- `0 9 * * 1` - Every Monday at 9 AM
- `0 0 1 * *` - First day of every month

**Output:**
```json
{
  \"triggered_at\": \"2024-01-15T10:00:00Z\"
}
```

**Use Cases:**
- Generate daily reports
- Clean up old data
- Send periodic emails
- Check APIs for updates

---

### 👆 Manual Trigger

**Type:** `trigger.manual`  
**Category:** Triggers  
**Color:** #F59E0B

**Description:** Starts the workflow manually when user clicks \"Execute\".

**Configuration:**
```json
{
  \"input_fields\": [
    {
      \"name\": \"user_id\",
      \"type\": \"number\",
      \"required\": true
    },
    {
      \"name\": \"email\",
      \"type\": \"string\"
    }
  ]
}
```

**Use Cases:**
- Testing workflows
- One-time operations
- User-initiated processes

---

### 🔄 Polling Trigger

**Type:** `trigger.polling`  
**Category:** Triggers  
**Color:** #F59E0B

**Description:** Polls an API endpoint periodically and triggers when new items are found.

**Configuration:**
```json
{
  \"url\": \"https://api.example.com/new-items\",
  \"interval\": 300,  // Check every 5 minutes
  \"id_field\": \"id\"  // Track items by this field
}
```

**Use Cases:**
- Monitor RSS feeds
- Check for new database records
- Poll APIs without webhook support

---

## App Integration Nodes

### 🌐 HTTP Request

**Type:** `apps.http_request`  
**Category:** Apps  
**Color:** #3B82F6

**Description:** Makes HTTP requests to any API endpoint.

**Configuration:**
```json
{
  \"url\": \"https://api.example.com/users/{{$node.trigger.body.user_id}}\",
  \"method\": \"POST\",  // GET, POST, PUT, PATCH, DELETE
  \"headers\": {
    \"Authorization\": \"Bearer {{$credential.api_token}}\",
    \"Content-Type\": \"application/json\"
  },
  \"body\": {
    \"name\": \"{{$node.trigger.body.name}}\"
  },
  \"timeout\": 30,
  \"follow_redirects\": true,
  \"verify_ssl\": true
}
```

**Output:**
```json
{
  \"status_code\": 200,
  \"headers\": { ... },
  \"body\": { ... },
  \"cookies\": [ ... ]
}
```

**Advanced Features:**
- Variable interpolation (`{{...}}`)
- Credential support
- Query parameters
- Form data
- File uploads
- Response parsing (JSON, XML, text)

---

### 📧 Email (SMTP/SendGrid)

**Type:** `apps.email`  
**Category:** Apps  
**Color:** #10B981

**Description:** Sends emails via SMTP or SendGrid.

**Configuration:**
```json
{
  \"to\": \"user@example.com\",
  \"cc\": \"manager@example.com\",
  \"bcc\": \"archive@example.com\",
  \"subject\": \"Welcome {{$node.trigger.body.name}}!\",
  \"body\": \"<h1>Welcome!</h1><p>Thanks for signing up.</p>\",
  \"body_type\": \"html\",  // html or text
  \"attachments\": [
    {
      \"filename\": \"report.pdf\",
      \"content\": \"{{$node.generate_pdf.output}}\"
    }
  ]
}
```

**Credentials Required:** `sendgrid` or `smtp`

---

### 💬 Slack

**Type:** `apps.slack`  
**Category:** Apps  
**Color:** #4A154B

**Description:** Sends messages to Slack channels.

**Configuration:**
```json
{
  \"channel\": \"#general\",
  \"text\": \"New order received: {{$node.trigger.body.order_id}}\",
  \"username\": \"OrderBot\",
  \"icon_emoji\": \":shopping_cart:\",
  \"attachments\": [
    {
      \"title\": \"Order Details\",
      \"text\": \"Amount: ${{$node.trigger.body.amount}}\",
      \"color\": \"good\"
    }
  ]
}
```

**Credentials Required:** `slack`

---

### 🗄️ Database Query

**Type:** `apps.database_query`  
**Category:** Apps  
**Color:** #8B5CF6

**Description:** Execute SQL queries on PostgreSQL, MySQL, or MongoDB.

**Configuration:**
```json
{
  \"query\": \"SELECT * FROM users WHERE id = :user_id\",
  \"parameters\": {
    \"user_id\": \"{{$node.trigger.body.user_id}}\"
  },
  \"operation\": \"select\"  // select, insert, update, delete
}
```

**Credentials Required:** `postgresql`, `mysql`, or `mongodb`

---

### 💳 Stripe

**Type:** `apps.stripe`  
**Category:** Apps  
**Color:** #635BFF

**Description:** Interact with Stripe API (create charges, customers, subscriptions).

**Configuration:**
```json
{
  \"operation\": \"create_payment_intent\",
  \"amount\": 1000,  // $10.00
  \"currency\": \"usd\",
  \"customer\": \"{{$node.trigger.body.customer_id}}\",
  \"description\": \"Order payment\"
}
```

**Credentials Required:** `stripe`

---

## Flow Control Nodes

### 🔀 If Condition

**Type:** `flow.if`  
**Category:** Flow Control  
**Color:** #F59E0B

**Description:** Branches workflow based on a condition.

**Configuration:**
```json
{
  \"conditions\": [
    {
      \"field\": \"{{$node.trigger.body.amount}}\",
      \"operator\": \"greater_than\",
      \"value\": 100
    }
  ],
  \"logic\": \"and\"  // and, or
}
```

**Operators:**
- `equals`, `not_equals`
- `greater_than`, `less_than`
- `contains`, `not_contains`
- `starts_with`, `ends_with`
- `is_empty`, `is_not_empty`

**Outputs:**
- **True branch** - Condition met
- **False branch** - Condition not met

---

### 🔄 Loop

**Type:** `flow.loop`  
**Category:** Flow Control  
**Color:** #F59E0B

**Description:** Iterates over an array and executes child nodes for each item.

**Configuration:**
```json
{
  \"items\": \"{{$node.get_users.output.users}}\",
  \"batch_size\": 10,  // Process in batches
  \"max_iterations\": 1000
}
```

**Output:**
```json
{
  \"items\": [...],  // All processed items
  \"count\": 150
}
```

---

### 🎯 Switch

**Type:** `flow.switch`  
**Category:** Flow Control  
**Color:** #F59E0B

**Description:** Routes to different branches based on value matching.

**Configuration:**
```json
{
  \"value\": \"{{$node.trigger.body.status}}\",
  \"cases\": [
    {\"match\": \"pending\", \"output\": 0},
    {\"match\": \"approved\", \"output\": 1},
    {\"match\": \"rejected\", \"output\": 2}
  ],
  \"default_output\": 3
}
```

---

### 🔗 Merge

**Type:** `flow.merge`  
**Category:** Flow Control  
**Color:** #6B7280

**Description:** Waits for multiple branches to complete and merges their outputs.

**Configuration:**
```json
{
  \"mode\": \"wait_all\"  // wait_all or wait_any
}
```

**Output:**
```json
{
  \"branch_0\": { ... },
  \"branch_1\": { ... }
}
```

---

### ⏸️ Wait

**Type:** `flow.wait`  
**Category:** Flow Control  
**Color:** #EF4444

**Description:** Pauses workflow for a duration or until webhook is received.

**Configuration:**

**Time-based:**
```json
{
  \"mode\": \"time\",
  \"duration\": \"5 minutes\"  // or \"1 hour\", \"2 days\"
}
```

**Webhook-based:**
```json
{
  \"mode\": \"webhook\",
  \"timeout\": \"7 days\"
}
```

**Use Cases:**
- Wait for external approval
- Delay between actions
- Rate limiting

---

### ⚠️ Try-Catch

**Type:** `flow.try_catch`  
**Category:** Flow Control  
**Color:** #EF4444

**Description:** Error handling - catches errors from child nodes.

**Configuration:**
```json
{
  \"continue_on_error\": true
}
```

**Outputs:**
- **Success output** - No errors
- **Error output** - Error caught

---

### 🔁 Retry

**Type:** `flow.retry`  
**Category:** Flow Control  
**Color:** #F59E0B

**Description:** Retries child nodes on failure.

**Configuration:**
```json
{
  \"max_attempts\": 3,
  \"delay\": 5,  // seconds between retries
  \"backoff\": \"exponential\"  // linear or exponential
}
```

---

## Data Transformation Nodes

### 🔤 JSON Parser

**Type:** `data.json`  
**Category:** Data  
**Color:** #6366F1

**Description:** Parse, stringify, or manipulate JSON data.

**Configuration:**
```json
{
  \"operation\": \"parse\",  // parse, stringify, extract
  \"input\": \"{{$node.http_request.body}}\",
  \"path\": \"data.user.name\"  // For extract operation
}
```

---

### 🔍 Filter

**Type:** `data.filter`  
**Category:** Data  
**Color:** #6366F1

**Description:** Filters array items based on conditions.

**Configuration:**
```json
{
  \"array\": \"{{$node.get_users.output}}\",
  \"conditions\": [
    {
      \"field\": \"age\",
      \"operator\": \"greater_than\",
      \"value\": 18
    }
  ]
}
```

---

### 📊 Transform

**Type:** `data.transform`  
**Category:** Data  
**Color:** #6366F1

**Description:** Transform data structure using JSONata expressions.

**Configuration:**
```json
{
  \"expression\": \"$map(users, function($v) { {'name': $v.first_name & ' ' & $v.last_name} })\"
}
```

---

### 🧮 Math

**Type:** `data.math`  
**Category:** Data  
**Color:** #6366F1

**Description:** Perform mathematical operations.

**Configuration:**
```json
{
  \"operation\": \"add\",  // add, subtract, multiply, divide, round, etc.
  \"value1\": \"{{$node.trigger.body.price}}\",
  \"value2\": \"{{$node.trigger.body.tax}}\"
}
```

---

### 📝 String

**Type:** `data.string`  
**Category:** Data  
**Color:** #6366F1

**Description:** String manipulation operations.

**Configuration:**
```json
{
  \"operation\": \"replace\",  // concat, split, replace, trim, uppercase, etc.
  \"input\": \"{{$node.trigger.body.text}}\",
  \"find\": \"old\",
  \"replace\": \"new\"
}
```

---

### 📅 DateTime

**Type:** `data.datetime`  
**Category:** Data  
**Color:** #6366F1

**Description:** Date and time operations.

**Configuration:**
```json
{
  \"operation\": \"format\",  // format, add, subtract, diff
  \"input\": \"{{$node.trigger.body.date}}\",
  \"format\": \"YYYY-MM-DD HH:mm:ss\",
  \"timezone\": \"America/New_York\"
}
```

---

### 📋 Array

**Type:** `data.array`  
**Category:** Data  
**Color:** #6366F1

**Description:** Array manipulation (map, filter, reduce, sort).

**Configuration:**
```json
{
  \"operation\": \"map\",  // map, filter, reduce, sort, unique, slice
  \"array\": \"{{$node.get_data.output}}\",
  \"expression\": \"item.name\"
}
```

---

## AI & LLM Nodes

### 🤖 OpenAI GPT

**Type:** `ai.openai_gpt`  
**Category:** AI  
**Color:** #10A37F

**Description:** Generate text using OpenAI GPT models.

**Configuration:**
```json
{
  \"model\": \"gpt-4o\",  // gpt-4o, gpt-4o-mini, o1, o3-mini
  \"prompt\": \"Summarize this text: {{$node.trigger.body.content}}\",
  \"max_tokens\": 500,
  \"temperature\": 0.7,
  \"system_message\": \"You are a helpful assistant.\"
}
```

**Credentials Required:** `openai`

---

### 🧠 Anthropic Claude

**Type:** `ai.anthropic_claude`  
**Category:** AI  
**Color:** #D97706

**Description:** Generate text using Anthropic Claude models.

**Configuration:**
```json
{
  \"model\": \"claude-sonnet-4-20250514\",
  \"prompt\": \"Analyze this feedback: {{$node.trigger.body.feedback}}\",
  \"max_tokens\": 1000,
  \"temperature\": 0.5
}
```

**Credentials Required:** `anthropic`

---

### 💎 Google Gemini

**Type:** `ai.google_gemini`  
**Category:** AI  
**Color:** #4285F4

**Description:** Generate text using Google Gemini models.

**Configuration:**
```json
{
  \"model\": \"gemini-2.0-flash\",
  \"prompt\": \"Translate to Spanish: {{$node.trigger.body.text}}\",
  \"temperature\": 0.3
}
```

**Credentials Required:** `google_ai`

---

## RAG Nodes

### 📄 Document Loader

**Type:** `rag.document_loader`  
**Category:** RAG  
**Color:** #8B5CF6

**Description:** Load documents from various sources.

**Configuration:**
```json
{
  \"source_type\": \"file\",  // file, url, text
  \"source\": \"{{$node.trigger.body.file_url}}\",
  \"file_type\": \"pdf\"  // pdf, docx, txt, csv
}
```

**Output:**
```json
{
  \"content\": \"Document text...\",
  \"metadata\": {
    \"pages\": 10,
    \"words\": 5000
  }
}
```

---

### ✂️ Text Chunker

**Type:** `rag.text_chunker`  
**Category:** RAG  
**Color:** #8B5CF6

**Description:** Split text into chunks for embedding.

**Configuration:**
```json
{
  \"text\": \"{{$node.document_loader.output.content}}\",
  \"chunk_size\": 500,
  \"chunk_overlap\": 50,
  \"strategy\": \"token\"  // token, sentence, paragraph
}
```

---

### 🗂️ Vector Store Writer

**Type:** `rag.vector_store_writer`  
**Category:** RAG  
**Color:** #8B5CF6

**Description:** Store embeddings in pgvector database.

**Configuration:**
```json
{
  \"chunks\": \"{{$node.chunker.output.chunks}}\",
  \"collection\": \"knowledge_base\",
  \"embedding_model\": \"text-embedding-3-small\"
}
```

---

### 🔍 RAG Query

**Type:** `rag.rag_query`  
**Category:** RAG  
**Color:** #8B5CF6

**Description:** Semantic search + LLM generation (RAG pipeline).

**Configuration:**
```json
{
  \"query\": \"{{$node.trigger.body.question}}\",
  \"collection\": \"knowledge_base\",
  \"top_k\": 5,
  \"llm_model\": \"gpt-4o\",
  \"system_prompt\": \"Answer based on the provided context.\"
}
```

**Output:**
```json
{
  \"answer\": \"Generated answer...\",
  \"sources\": [
    {\"chunk\": \"...\", \"score\": 0.85}
  ]
}
```

---

## Debug & Utility Nodes

### 🪵 Logger

**Type:** `debug.logger`  
**Category:** Debug  
**Color:** #6B7280

**Description:** Log data for debugging.

**Configuration:**
```json
{
  \"message\": \"User data: {{$json($node.get_user.output)}}\",
  \"level\": \"info\"  // debug, info, warning, error
}
```

---

### 💾 Set Variable

**Type:** `core.set_variable`  
**Category:** Core  
**Color:** #6B7280

**Description:** Store data for use in later nodes.

**Configuration:**
```json
{
  \"key\": \"user_email\",
  \"value\": \"{{$node.trigger.body.email}}\"
}
```

---

### 🔧 Code (JavaScript/Python)

**Type:** `code.function`  
**Category:** Code  
**Color:** #F59E0B

**Description:** Execute custom JavaScript or Python code.

**Configuration:**
```json
{
  \"language\": \"javascript\",
  \"code\": \"const result = input.value * 2; return { doubled: result };\",
  \"timeout\": 10
}
```

**Available Variables:**
- `input` - Node input data
- `$node` - Access other node outputs
- `$vars` - Workspace variables
- `$credentials` - Credentials (masked)

---

## Node Usage Statistics

**Most Popular Nodes:**
1. HTTP Request (45%)
2. Email (18%)
3. If Condition (12%)
4. Database Query (8%)
5. OpenAI GPT (7%)

**Average Nodes per Workflow:** 5-8 nodes

---

## Creating Custom Nodes

See [DEVELOPER_HANDBOOK.md#creating-a-new-workflow-node](./DEVELOPER_HANDBOOK.md#creating-a-new-workflow-node) for guide on creating custom nodes.

---

**For implementation details, see [ARCHITECTURE_DEEP_DIVE.md](../core/02-architecture.md)**
