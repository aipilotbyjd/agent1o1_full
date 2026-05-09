# 📮 Postman Collection Update Guide

**Date:** 2026-04-04  
**Collection:** Agent1o1 API v1  
**What's New:** Updated endpoints + New node documentation

---

## 🔄 What Needs Updating

### ✅ Collection Already Has
Your existing Postman collection (`Agent1o1-API.postman_collection.json`) already includes:
- Health check
- Authentication (register, login, refresh, password reset)
- User profile management
- Workspaces CRUD
- Team & invitations
- Workflows CRUD
- Executions
- Credentials & OAuth
- Variables
- Tags
- Webhooks
- Credits
- Catalog (nodes, credential types)
- Templates
- Log streaming
- Git sync

### 🆕 What's New to Add

#### 1. **New Node Catalog Endpoints**
After implementing 19 new nodes, the `/catalog/nodes` endpoint now returns:

**New Data Transformation Nodes:**
- `data.json` - JSON operations
- `data.filter` - Array filtering
- `data.array` - Array operations
- `data.string` - String manipulation
- `data.math` - Mathematical calculations
- `data.datetime` - Date/time operations
- `data.variable` - Variable management
- `data.cache` - Caching operations

**New Flow Control Nodes:**
- `flow.try_catch` - Error handling
- `flow.switch` - Multi-way branching
- `flow.wait_for_event` - Event-driven workflows
- `flow.batch_processor` - Batch processing
- `flow.retry` - Retry with backoff

**New Communication Nodes:**
- `communication.email` - Email sending

**New Debug Nodes:**
- `debug.logger` - Logging and debugging

**New RAG Nodes:**
- `rag.document_loader` - Document loading
- `rag.chunker` - Text chunking
- `rag.vector_store_writer` - Vector storage
- `rag.query` - RAG query

---

## 📝 How to Update Your Collection

### Option 1: Manual Update (Recommended)

1. **Open Postman**
2. **Navigate to:** `📦 Catalog → Nodes → List All Nodes`
3. **Send Request** - You should see all 19 new nodes in the response
4. **Test individual nodes** by creating workflows with them

### Option 2: Import Updated Catalog Section

Add this folder to your collection:

```json
{
  "name": "📦 Updated Catalog",
  "description": "Updated catalog with 19 new nodes",
  "item": [
    {
      "name": "List All Nodes (Updated)",
      "event": [
        {
          "listen": "test",
          "script": {
            "type": "text/javascript",
            "exec": [
              "pm.test('Status is 200 OK', function () {",
              "    pm.response.to.have.status(200);",
              "});",
              "",
              "pm.test('Response contains nodes array', function () {",
              "    var json = pm.response.json();",
              "    pm.expect(json.data).to.be.an('array');",
              "    pm.expect(json.data.length).to.be.above(40); // Should have 40+ nodes now",
              "});",
              "",
              "// Check for new P0 nodes",
              "pm.test('Contains new P0 critical nodes', function () {",
              "    var json = pm.response.json();",
              "    var nodeTypes = json.data.map(n => n.type);",
              "    ",
              "    // Data transformation nodes",
              "    pm.expect(nodeTypes).to.include('data.json');",
              "    pm.expect(nodeTypes).to.include('data.filter');",
              "    pm.expect(nodeTypes).to.include('data.array');",
              "    pm.expect(nodeTypes).to.include('data.string');",
              "    pm.expect(nodeTypes).to.include('data.math');",
              "    pm.expect(nodeTypes).to.include('data.datetime');",
              "    ",
              "    // Flow control nodes",
              "    pm.expect(nodeTypes).to.include('flow.try_catch');",
              "    pm.expect(nodeTypes).to.include('flow.switch');",
              "    pm.expect(nodeTypes).to.include('flow.retry');",
              "    ",
              "    // Communication nodes",
              "    pm.expect(nodeTypes).to.include('communication.email');",
              "    ",
              "    // Debug nodes",
              "    pm.expect(nodeTypes).to.include('debug.logger');",
              "});",
              "",
              "// Check for RAG nodes",
              "pm.test('Contains RAG system nodes', function () {",
              "    var json = pm.response.json();",
              "    var nodeTypes = json.data.map(n => n.type);",
              "    ",
              "    pm.expect(nodeTypes).to.include('rag.document_loader');",
              "    pm.expect(nodeTypes).to.include('rag.chunker');",
              "    pm.expect(nodeTypes).to.include('rag.vector_store_writer');",
              "    pm.expect(nodeTypes).to.include('rag.query');",
              "});"
            ]
          }
        }
      ],
      "request": {
        "method": "GET",
        "header": [
          {
            "key": "Accept",
            "value": "application/json"
          }
        ],
        "url": {
          "raw": "{{base_url}}/api/v1/workspaces/{{workspace_id}}/catalog/nodes",
          "host": ["{{base_url}}"],
          "path": ["api", "v1", "workspaces", "{{workspace_id}}", "catalog", "nodes"]
        }
      }
    },
    {
      "name": "Get Node Details - JSON Node",
      "event": [
        {
          "listen": "test",
          "script": {
            "type": "text/javascript",
            "exec": [
              "pm.test('Status is 200 OK', function () {",
              "    pm.response.to.have.status(200);",
              "});",
              "",
              "pm.test('Node has correct type', function () {",
              "    var json = pm.response.json();",
              "    pm.expect(json.data.type).to.equal('data.json');",
              "});",
              "",
              "pm.test('Node has operations', function () {",
              "    var json = pm.response.json();",
              "    pm.expect(json.data.config_schema.properties.operation.enum).to.include('parse');",
              "    pm.expect(json.data.config_schema.properties.operation.enum).to.include('stringify');",
              "    pm.expect(json.data.config_schema.properties.operation.enum).to.include('extract');",
              "    pm.expect(json.data.config_schema.properties.operation.enum).to.include('merge');",
              "    pm.expect(json.data.config_schema.properties.operation.enum).to.include('validate');",
              "});"
            ]
          }
        }
      ],
      "request": {
        "method": "GET",
        "header": [
          {
            "key": "Accept",
            "value": "application/json"
          }
        ],
        "url": {
          "raw": "{{base_url}}/api/v1/workspaces/{{workspace_id}}/catalog/nodes/data.json",
          "host": ["{{base_url}}"],
          "path": ["api", "v1", "workspaces", "{{workspace_id}}", "catalog", "nodes", "data.json"]
        }
      }
    }
  ]
}
```

---

## 🧪 Test Workflow Examples

### Example 1: JSON Parse Workflow

**Create this workflow to test the JSON node:**

```json
{
  "name": "Test JSON Node",
  "nodes": [
    {
      "id": "trigger",
      "type": "core.manual_trigger",
      "position": { "x": 100, "y": 100 },
      "config": {}
    },
    {
      "id": "json_parse",
      "type": "data.json",
      "position": { "x": 300, "y": 100 },
      "config": {
        "operation": "parse",
        "json_string": "{\"name\": \"John\", \"age\": 30}"
      }
    }
  ],
  "edges": [
    {
      "source": "trigger",
      "target": "json_parse"
    }
  ]
}
```

**Expected Response:**
```json
{
  "execution_id": "...",
  "status": "completed",
  "nodes": {
    "json_parse": {
      "output": {
        "data": {
          "name": "John",
          "age": 30
        },
        "success": true
      }
    }
  }
}
```

---

### Example 2: Filter & Email Workflow

**Test Filter + Email nodes:**

```json
{
  "name": "Filter and Email",
  "nodes": [
    {
      "id": "trigger",
      "type": "core.manual_trigger",
      "position": { "x": 100, "y": 100 },
      "config": {
        "input": {
          "users": [
            {"name": "John", "status": "active", "email": "john@example.com"},
            {"name": "Jane", "status": "inactive", "email": "jane@example.com"},
            {"name": "Bob", "status": "active", "email": "bob@example.com"}
          ]
        }
      }
    },
    {
      "id": "filter",
      "type": "data.filter",
      "position": { "x": 300, "y": 100 },
      "config": {
        "operation": "filter_by_condition",
        "field": "status",
        "operator": "equals",
        "value": "active",
        "mode": "keep"
      }
    },
    {
      "id": "email",
      "type": "communication.email",
      "position": { "x": 500, "y": 100 },
      "config": {
        "operation": "send_bulk",
        "recipients": "{{ $nodes.filter.output.items }}",
        "subject": "Welcome!",
        "body": "Hello {{ name }}!",
        "from": "noreply@example.com"
      }
    }
  ],
  "edges": [
    {
      "source": "trigger",
      "target": "filter"
    },
    {
      "source": "filter",
      "target": "email"
    }
  ]
}
```

---

## 📚 New Node Documentation

### Quick Reference: All 19 New Nodes

#### Data Transformation (8 nodes)

| Node Type | Operations | Use Cases |
|-----------|-----------|-----------|
| `data.json` | parse, stringify, extract, merge, validate | API responses, data transformation |
| `data.filter` | filter_by_condition, filter_by_value, remove_duplicates, remove_empty | Data filtering, validation |
| `data.array` | map, reduce, sort, group_by, unique, flatten, slice, chunk | Array manipulation |
| `data.string` | concat, split, replace, regex, case, trim, substring, template, length | Text processing |
| `data.math` | calculate, aggregate, round, random, formula | Calculations, statistics |
| `data.datetime` | parse, format, add, subtract, diff, compare, now, timezone | Date/time operations |
| `data.variable` | set, get, increment, decrement, append, delete | State management |
| `data.cache` | get, set, has, delete, clear, remember | Performance optimization |

#### Flow Control (5 nodes)

| Node Type | Purpose | Use Cases |
|-----------|---------|-----------|
| `flow.try_catch` | Error handling | Graceful error recovery |
| `flow.switch` | Multi-way branching | Route by value/type/regex |
| `flow.wait_for_event` | Event-driven workflows | Approval workflows, webhooks |
| `flow.batch_processor` | Batch processing | Bulk operations |
| `flow.retry` | Retry with backoff | API failures, network errors |

#### Communication (1 node)

| Node Type | Operations | Use Cases |
|-----------|-----------|-----------|
| `communication.email` | send, send_bulk | Notifications, campaigns |

#### Debug (1 node)

| Node Type | Operations | Use Cases |
|-----------|-----------|-----------|
| `debug.logger` | log, debug, inspect | Debugging, monitoring |

#### RAG System (4 nodes)

| Node Type | Purpose | Use Cases |
|-----------|---------|-----------|
| `rag.document_loader` | Load documents | Ingest PDFs, text, URLs |
| `rag.chunker` | Split text | Prepare for embedding |
| `rag.vector_store_writer` | Store embeddings | Vector database |
| `rag.query` | RAG queries | Q&A, search |

---

## 🔍 Verification Checklist

After updating, verify these:

- [ ] `GET /api/v1/workspaces/{id}/catalog/nodes` returns 40+ nodes
- [ ] All 19 new node types appear in the catalog
- [ ] Can create workflow with `data.json` node
- [ ] Can create workflow with `data.filter` node
- [ ] Can create workflow with `communication.email` node
- [ ] Can create workflow with `flow.try_catch` node
- [ ] Can create workflow with RAG nodes
- [ ] Workflow execution works with new nodes
- [ ] Node configuration schemas are complete

---

## 🚀 Quick Start Script

Run these in order to verify everything works:

```bash
# 1. Seed the new nodes (run this first!)
php artisan db:seed --class=NodeSeeder

# 2. Verify node count
php artisan tinker
>>> Node::count();
# Should be 40+

# 3. Check specific new nodes
>>> Node::whereIn('type', ['data.json', 'data.filter', 'communication.email'])->count();
# Should return 3

# 4. Verify RAG migration
>>> Schema::hasTable('document_embeddings');
# Should return true

# 5. Check that pgvector is installed
>>> DB::select("SELECT * FROM pg_extension WHERE extname = 'vector'");
# Should return vector extension info
```

---

## 📝 Notes

### Important:
1. **Run migrations first:** `php artisan migrate`
2. **Seed nodes:** `php artisan db:seed --class=NodeSeeder`
3. **Clear cache:** `php artisan cache:clear`
4. **Restart queue:** `php artisan queue:restart`

### Node Configuration
All new nodes have:
- Complete `config_schema` with validation
- `input_schema` and `output_schema`
- Comprehensive operation enums
- Default values
- Descriptions

### Testing Tips
- Use the Logger node to debug workflows
- Use Try/Catch node to handle errors gracefully
- Test email node with a test email service (Mailtrap, MailHog)
- RAG nodes require pgvector extension installed

---

## 🔗 Related Documentation

- `/app/docs/ALL_P0_NODES_COMPLETE.md` - Complete node implementation details
- `/app/docs/IMPLEMENTATION_COMPLETE.md` - Loop & RAG implementation
- `/app/docs/PLATFORM_ENHANCEMENT_ROADMAP.md` - Future enhancements

---

**Your collection is already comprehensive! The main updates are the 19 new nodes in the catalog endpoint.** 🎉
