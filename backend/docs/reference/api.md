# 📡 API Reference - Complete Endpoint Documentation

**Complete reference for all LinkFlow REST API endpoints**

---

## Base URL

```
Production: https://your-domain.com/api/v1
Local: http://localhost:8000/api/v1
```

## Authentication

All authenticated endpoints require a Bearer token:

```http
Authorization: Bearer {access_token}
```

## Response Format

**Success Response:**
```json
{
  "data": { ... },
  "message": "Success message",
  "meta": { ... }  // Optional pagination, etc.
}
```

**Error Response:**
```json
{
  "message": "Error message",
  "errors": { ... }  // Validation errors
}
```

## Status Codes

- `200` - OK (Success)
- `201` - Created (Resource created)
- `204` - No Content (Successful deletion)
- `400` - Bad Request (Invalid input)
- `401` - Unauthorized (Invalid/missing token)
- `403` - Forbidden (No permission)
- `404` - Not Found (Resource doesn't exist)
- `422` - Unprocessable Entity (Validation failed)
- `429` - Too Many Requests (Rate limited)
- `500` - Internal Server Error

---

## Table of Contents

1. [Authentication](#authentication)
2. [Users](#users)
3. [Workspaces](#workspaces)
4. [Workflows](#workflows)
5. [Executions](#executions)
6. [Nodes](#nodes)
7. [Credentials](#credentials)
8. [Variables](#variables)
9. [Tags](#tags)
10. [Webhooks](#webhooks)
11. [Templates](#templates)
12. [Team Members](#team-members)
13. [Notifications](#notifications)
14. [Activity Logs](#activity-logs)
15. [AI Agents](#ai-agents)
16. [Settings](#settings)

---

## Authentication

### Register

```http
POST /auth/register
```

**Body:**
```json
{
  "name": "John Doe",
  "email": "john@example.com",
  "password": "password123",
  "password_confirmation": "password123"
}
```

**Response (201):**
```json
{
  "data": {
    "user": {
      "id": "uuid",
      "name": "John Doe",
      "email": "john@example.com"
    },
    "access_token": "eyJ0eXAiOi...",
    "refresh_token": "def5020...",
    "token_type": "Bearer",
    "expires_in": 3600
  }
}
```

### Login

```http
POST /auth/login
```

**Body:**
```json
{
  "email": "john@example.com",
  "password": "password123"
}
```

**Response (200):** Same as register

### Logout

```http
POST /auth/logout
Authorization: Bearer {token}
```

**Response (200):**
```json
{
  "message": "Successfully logged out"
}
```

### Refresh Token

```http
POST /auth/refresh
```

**Body:**
```json
{
  "refresh_token": "def5020..."
}
```

**Response (200):**
```json
{
  "access_token": "new_token...",
  "expires_in": 3600
}
```

---

## Users

### Get Current User

```http
GET /user
Authorization: Bearer {token}
```

**Response (200):**
```json
{
  "data": {
    "id": "uuid",
    "name": "John Doe",
    "email": "john@example.com",
    "avatar": "https://...",
    "timezone": "America/New_York",
    "created_at": "2024-01-01T00:00:00Z"
  }
}
```

### Update Profile

```http
PUT /user
Authorization: Bearer {token}
```

**Body:**
```json
{
  "name": "John Smith",
  "timezone": "UTC"
}
```

---

## Workspaces

### List Workspaces

```http
GET /workspaces
Authorization: Bearer {token}
```

**Response (200):**
```json
{
  "data": [
    {
      "id": "uuid",
      "name": "My Workspace",
      "slug": "my-workspace",
      "role": "owner",
      "created_at": "2024-01-01T00:00:00Z"
    }
  ]
}
```

### Create Workspace

```http
POST /workspaces
Authorization: Bearer {token}
```

**Body:**
```json
{
  "name": "New Workspace",
  "description": "Optional description"
}
```

### Get Workspace

```http
GET /workspaces/{workspace}
Authorization: Bearer {token}
```

### Update Workspace

```http
PUT /workspaces/{workspace}
Authorization: Bearer {token}
```

### Delete Workspace

```http
DELETE /workspaces/{workspace}
Authorization: Bearer {token}
```

---

## Workflows

### List Workflows

```http
GET /workspaces/{workspace}/workflows
Authorization: Bearer {token}
```

**Query Parameters:**
- `search` - Search by name
- `is_active` - Filter by active status (true/false)
- `tags` - Filter by tag IDs (comma-separated)
- `page` - Page number
- `per_page` - Items per page (default: 20)

**Response (200):**
```json
{
  "data": [
    {
      "id": "uuid",
      "name": "User Onboarding",
      "description": "Sends welcome emails",
      "is_active": true,
      "execution_count": 152,
      "last_execution_at": "2024-01-15T10:30:00Z",
      "created_at": "2024-01-01T00:00:00Z"
    }
  ],
  "meta": {
    "total": 50,
    "current_page": 1,
    "per_page": 20,
    "last_page": 3
  }
}
```

### Create Workflow

```http
POST /workspaces/{workspace}/workflows
Authorization: Bearer {token}
```

**Body:**
```json
{
  "name": "New Workflow",
  "description": "Description",
  "definition": {
    "nodes": [
      {
        "id": "node1",
        "type": "trigger.webhook",
        "position": { "x": 100, "y": 100 },
        "config": {}
      }
    ],
    "connections": []
  },
  "settings": {
    "timeout": 300,
    "error_workflow": null
  }
}
```

### Get Workflow

```http
GET /workspaces/{workspace}/workflows/{workflow}
Authorization: Bearer {token}
```

### Update Workflow

```http
PUT /workspaces/{workspace}/workflows/{workflow}
Authorization: Bearer {token}
```

### Delete Workflow

```http
DELETE /workspaces/{workspace}/workflows/{workflow}
Authorization: Bearer {token}
```

### Activate/Deactivate Workflow

```http
POST /workspaces/{workspace}/workflows/{workflow}/activate
POST /workspaces/{workspace}/workflows/{workflow}/deactivate
Authorization: Bearer {token}
```

### Duplicate Workflow

```http
POST /workspaces/{workspace}/workflows/{workflow}/duplicate
Authorization: Bearer {token}
```

---

## Executions

### List Executions

```http
GET /workspaces/{workspace}/executions
Authorization: Bearer {token}
```

**Query Parameters:**
- `workflow_id` - Filter by workflow
- `status` - Filter by status (running, success, failed, cancelled, waiting)
- `mode` - Filter by mode (webhook, manual, schedule, polling)
- `from` - Start date (ISO 8601)
- `to` - End date (ISO 8601)
- `page`, `per_page` - Pagination

**Response (200):**
```json
{
  "data": [
    {
      "id": "uuid",
      "workflow_id": "uuid",
      "workflow_name": "User Onboarding",
      "status": "success",
      "mode": "webhook",
      "started_at": "2024-01-15T10:00:00Z",
      "finished_at": "2024-01-15T10:00:12Z",
      "duration_seconds": 12,
      "credits_used": 5
    }
  ]
}
```

### Get Execution Details

```http
GET /workspaces/{workspace}/executions/{execution}
Authorization: Bearer {token}
```

**Response (200):**
```json
{
  "data": {
    "id": "uuid",
    "workflow_id": "uuid",
    "status": "success",
    "input_data": { ... },
    "output_data": { ... },
    "node_executions": [
      {
        "node_id": "node1",
        "node_type": "trigger.webhook",
        "status": "success",
        "output_data": { ... },
        "started_at": "2024-01-15T10:00:00Z",
        "finished_at": "2024-01-15T10:00:01Z"
      }
    ],
    "started_at": "2024-01-15T10:00:00Z",
    "finished_at": "2024-01-15T10:00:12Z"
  }
}
```

### Execute Workflow Manually

```http
POST /workspaces/{workspace}/workflows/{workflow}/execute
Authorization: Bearer {token}
```

**Body:**
```json
{
  "input_data": {
    "user_id": 123,
    "email": "test@example.com"
  }
}
```

**Response (201):**
```json
{
  "data": {
    "execution_id": "uuid",
    "status": "running"
  }
}
```

### Retry Execution

```http
POST /workspaces/{workspace}/executions/{execution}/retry
Authorization: Bearer {token}
```

### Cancel Execution

```http
POST /workspaces/{workspace}/executions/{execution}/cancel
Authorization: Bearer {token}
```

### Delete Execution

```http
DELETE /workspaces/{workspace}/executions/{execution}
Authorization: Bearer {token}
```

---

## Nodes

### List Available Nodes

```http
GET /nodes
Authorization: Bearer {token}
```

**Query Parameters:**
- `category` - Filter by category (triggers, apps, flow, etc.)
- `search` - Search by name or description

**Response (200):**
```json
{
  "data": [
    {
      "type": "trigger.webhook",
      "name": "Webhook",
      "description": "Starts workflow on HTTP request",
      "category": "Triggers",
      "icon": "bolt",
      "color": "#F59E0B",
      "config_schema": { ... },
      "output_schema": { ... }
    }
  ]
}
```

### Get Node Details

```http
GET /nodes/{type}
Authorization: Bearer {token}
```

---

## Credentials

### List Credentials

```http
GET /workspaces/{workspace}/credentials
Authorization: Bearer {token}
```

### Create Credential

```http
POST /workspaces/{workspace}/credentials
Authorization: Bearer {token}
```

**Body:**
```json
{
  "name": "My SendGrid Account",
  "type": "sendgrid",
  "data": {
    "api_key": "SG.xxx"
  }
}
```

### Update Credential

```http
PUT /workspaces/{workspace}/credentials/{credential}
Authorization: Bearer {token}
```

### Delete Credential

```http
DELETE /workspaces/{workspace}/credentials/{credential}
Authorization: Bearer {token}
```

### Test Credential

```http
POST /workspaces/{workspace}/credentials/{credential}/test
Authorization: Bearer {token}
```

---

## Variables

### List Variables

```http
GET /workspaces/{workspace}/variables
Authorization: Bearer {token}
```

### Create Variable

```http
POST /workspaces/{workspace}/variables
Authorization: Bearer {token}
```

**Body:**
```json
{
  "key": "api_base_url",
  "value": "https://api.example.com",
  "type": "string",
  "is_secret": false
}
```

---

## Webhooks

### List Webhooks

```http
GET /workspaces/{workspace}/webhooks
Authorization: Bearer {token}
```

**Response (200):**
```json
{
  "data": [
    {
      "id": "uuid",
      "uuid": "webhook-unique-id",
      "url": "https://your-app.com/api/v1/webhook/webhook-unique-id",
      "workflow_id": "uuid",
      "workflow_name": "My Workflow",
      "method": "POST",
      "is_active": true,
      "request_count": 152
    }
  ]
}
```

### Receive Webhook (Public)

```http
POST /webhook/{uuid}
GET /webhook/{uuid}
PUT /webhook/{uuid}
DELETE /webhook/{uuid}
```

**Note:** No authentication required. The UUID serves as the secret.

---

## Templates

### List Public Templates

```http
GET /templates
```

**No authentication required**

### Get Template Details

```http
GET /templates/{template}
```

### Use Template (Clone to Workspace)

```http
POST /workspaces/{workspace}/templates/{template}/use
Authorization: Bearer {token}
```

---

## Team Members

### List Members

```http
GET /workspaces/{workspace}/members
Authorization: Bearer {token}
```

**Response (200):**
```json
{
  "data": [
    {
      "id": "uuid",
      "user_id": "uuid",
      "name": "John Doe",
      "email": "john@example.com",
      "role": "editor",
      "joined_at": "2024-01-01T00:00:00Z"
    }
  ]
}
```

### Invite Member

```http
POST /workspaces/{workspace}/invitations
Authorization: Bearer {token}
```

**Body:**
```json
{
  "email": "newuser@example.com",
  "role": "editor"
}
```

### Update Member Role

```http
PUT /workspaces/{workspace}/members/{user}
Authorization: Bearer {token}
```

**Body:**
```json
{
  "role": "admin"
}
```

### Remove Member

```http
DELETE /workspaces/{workspace}/members/{user}
Authorization: Bearer {token}
```

---

## Notifications

### List Notifications

```http
GET /notifications
Authorization: Bearer {token}
```

### Mark as Read

```http
POST /notifications/{notification}/read
Authorization: Bearer {token}
```

### Mark All as Read

```http
POST /notifications/read-all
Authorization: Bearer {token}
```

---

## Activity Logs

### List Activity Logs

```http
GET /workspaces/{workspace}/activity-logs
Authorization: Bearer {token}
```

**Query Parameters:**
- `user_id` - Filter by user
- `action` - Filter by action (created, updated, deleted)
- `resource` - Filter by resource type (workflow, execution, etc.)
- `from`, `to` - Date range

---

## AI Agents

### List Agents

```http
GET /workspaces/{workspace}/agents
Authorization: Bearer {token}
```

### Create Agent

```http
POST /workspaces/{workspace}/agents
Authorization: Bearer {token}
```

**Body:**
```json
{
  "name": "Support Bot",
  "model": "gpt-4o",
  "system_prompt": "You are a helpful support agent...",
  "temperature": 0.7
}
```

### Send Message to Agent

```http
POST /workspaces/{workspace}/agents/{agent}/conversations/{conversation}/messages
Authorization: Bearer {token}
```

**Body:**
```json
{
  "message": "What's the status of order #12345?"
}
```

---

## Rate Limiting

**Default Limits:**
- Authentication endpoints: 5 requests per minute
- API endpoints: 60 requests per minute
- Webhook endpoints: 100 requests per minute

**Rate Limit Headers:**
```
X-RateLimit-Limit: 60
X-RateLimit-Remaining: 45
X-RateLimit-Reset: 1642345678
```

---

## Pagination

All list endpoints support pagination:

**Query Parameters:**
- `page` - Page number (default: 1)
- `per_page` - Items per page (default: 20, max: 100)

**Response:**
```json
{
  "data": [...],
  "meta": {
    "total": 150,
    "current_page": 1,
    "per_page": 20,
    "last_page": 8,
    "from": 1,
    "to": 20
  },
  "links": {
    "first": "https://.../api/v1/workflows?page=1",
    "last": "https://.../api/v1/workflows?page=8",
    "prev": null,
    "next": "https://.../api/v1/workflows?page=2"
  }
}
```

---

## Error Handling

### Validation Errors (422)

```json
{
  "message": "The given data was invalid.",
  "errors": {
    "email": [
      "The email field is required."
    ],
    "password": [
      "The password must be at least 8 characters."
    ]
  }
}
```

### Authorization Errors (403)

```json
{
  "message": "You do not have permission to perform this action."
}
```

### Not Found (404)

```json
{
  "message": "Resource not found."
}
```

---

## Postman Collection

Import the complete Postman collection:

**File:** `/docs/Agent1o1-API.postman_collection.json`

**Environment:** `/docs/Agent1o1-Local.postman_environment.json`

---

**For detailed implementation examples, see [REACT_FRONTEND_INTEGRATION.md](../frontend/README.md)**
