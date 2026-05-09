# 🪝 Module 8: Webhooks & Polling Triggers

**Configure webhook endpoints and polling triggers for workflow automation**

**APIs:** `/api/v1/workspaces/{workspace}/webhooks/*`, `/api/v1/workspaces/{workspace}/polling-triggers/*`  
**Components:** WebhookList, WebhookForm, WebhookTester, PollingTriggerConfig

---

## 🔗 API Endpoints

### Webhook Management

#### 1. List Webhooks
```http
GET /api/v1/workspaces/{workspace}/webhooks
Authorization: Bearer {token}

Response (200):
{
  "data": [
    {
      "id": "uuid",
      "uuid": "unique-webhook-identifier",
      "workflow_id": "uuid",
      "workflow_name": "User Registration Handler",
      "url": "https://your-domain.com/api/v1/webhook/unique-webhook-identifier",
      "method": "POST",
      "is_active": true,
      "request_count": 152,
      "last_triggered_at": "2024-01-15T10:30:00Z",
      "created_at": "2024-01-01T00:00:00Z"
    }
  ]
}
```

#### 2. Create Webhook (created automatically with workflow)
```http
POST /api/v1/workspaces/{workspace}/workflows/{workflow}/webhook
Content-Type: application/json

{
  "method": "POST",
  "authentication": {
    "type": "none" // or "basic", "bearer", "header"
  }
}

Response (201):
{
  "data": {
    "id": "uuid",
    "uuid": "abc123xyz",
    "url": "https://your-domain.com/api/v1/webhook/abc123xyz",
    "method": "POST"
  }
}
```

#### 3. Get Webhook Details
```http
GET /api/v1/workspaces/{workspace}/webhooks/{id}

Response (200):
{
  "data": {
    "id": "uuid",
    "uuid": "abc123xyz",
    "url": "https://your-domain.com/api/v1/webhook/abc123xyz",
    "workflow_id": "uuid",
    "method": "POST",
    "allowed_ips": ["192.168.1.0/24"],
    "authentication": {
      "type": "bearer",
      "token": "secret-token"
    },
    "request_count": 152,
    "last_triggered_at": "2024-01-15T10:30:00Z",
    "recent_requests": [
      {
        "id": "uuid",
        "status": "success",
        "execution_id": "uuid",
        "received_at": "2024-01-15T10:30:00Z"
      }
    ]
  }
}
```

#### 4. Update Webhook
```http
PUT /api/v1/workspaces/{workspace}/webhooks/{id}
Content-Type: application/json

{
  "method": "POST",
  "allowed_ips": ["203.0.113.0/24"],
  "authentication": {
    "type": "bearer",
    "token": "new-secret-token"
  }
}
```

#### 5. Delete Webhook
```http
DELETE /api/v1/workspaces/{workspace}/webhooks/{id}

Response (204): No Content
```

### Public Webhook Receiver (No Auth)

#### 6. Receive Webhook Call
```http
POST /api/v1/webhook/{uuid}
Content-Type: application/json

{
  "event": "user.created",
  "data": {
    "user_id": 123,
    "email": "user@example.com"
  }
}

Response (200):
{
  "success": true,
  "execution_id": "uuid",
  "message": "Workflow triggered successfully"
}

Response (202) - Queued:
{
  "success": true,
  "message": "Request queued for processing"
}
```

### Polling Triggers

#### 7. List Polling Triggers
```http
GET /api/v1/workspaces/{workspace}/polling-triggers

Response (200):
{
  "data": [
    {
      "id": "uuid",
      "workflow_id": "uuid",
      "workflow_name": "Check New Emails",
      "interval": 300, // seconds
      "is_active": true,
      "last_polled_at": "2024-01-15T10:25:00Z",
      "next_poll_at": "2024-01-15T10:30:00Z",
      "poll_count": 1240,
      "created_at": "2024-01-01T00:00:00Z"
    }
  ]
}
```

#### 8. Create Polling Trigger
```http
POST /api/v1/workspaces/{workspace}/workflows/{workflow}/polling-trigger
Content-Type: application/json

{
  "interval": 300, // Poll every 5 minutes
  "config": {
    "endpoint": "https://api.example.com/new-items",
    "method": "GET"
  }
}

Response (201):
{
  "data": {
    "id": "uuid",
    "interval": 300,
    "is_active": true,
    "next_poll_at": "2024-01-15T10:35:00Z"
  }
}
```

#### 9. Get Polling Trigger Details
```http
GET /api/v1/workspaces/{workspace}/polling-triggers/{id}

Response (200):
{
  "data": {
    "id": "uuid",
    "workflow_id": "uuid",
    "interval": 300,
    "config": {
      "endpoint": "https://api.example.com/new-items"
    },
    "poll_count": 1240,
    "last_polled_at": "2024-01-15T10:25:00Z",
    "recent_polls": [
      {
        "polled_at": "2024-01-15T10:25:00Z",
        "items_found": 3,
        "executions_created": 3
      }
    ]
  }
}
```

#### 10. Update Polling Trigger
```http
PUT /api/v1/workspaces/{workspace}/polling-triggers/{id}
Content-Type: application/json

{
  "interval": 600, // Change to 10 minutes
  "is_active": false
}
```

#### 11. Delete Polling Trigger
```http
DELETE /api/v1/workspaces/{workspace}/polling-triggers/{id}

Response (204): No Content
```

---

## 🗄️ State Management

### API Client
```javascript
// src/api/webhooks.js
import apiClient from './client';

export const webhooksApi = {
  list: (workspaceId) => 
    apiClient.get(`/workspaces/${workspaceId}/webhooks`),
  
  get: (workspaceId, webhookId) => 
    apiClient.get(`/workspaces/${workspaceId}/webhooks/${webhookId}`),
  
  create: (workspaceId, workflowId, data) => 
    apiClient.post(`/workspaces/${workspaceId}/workflows/${workflowId}/webhook`, data),
  
  update: (workspaceId, webhookId, data) => 
    apiClient.put(`/workspaces/${workspaceId}/webhooks/${webhookId}`, data),
  
  delete: (workspaceId, webhookId) => 
    apiClient.delete(`/workspaces/${workspaceId}/webhooks/${webhookId}`),
};

export const pollingTriggersApi = {
  list: (workspaceId) => 
    apiClient.get(`/workspaces/${workspaceId}/polling-triggers`),
  
  get: (workspaceId, triggerId) => 
    apiClient.get(`/workspaces/${workspaceId}/polling-triggers/${triggerId}`),
  
  create: (workspaceId, workflowId, data) => 
    apiClient.post(`/workspaces/${workspaceId}/workflows/${workflowId}/polling-trigger`, data),
  
  update: (workspaceId, triggerId, data) => 
    apiClient.put(`/workspaces/${workspaceId}/polling-triggers/${triggerId}`, data),
  
  delete: (workspaceId, triggerId) => 
    apiClient.delete(`/workspaces/${workspaceId}/polling-triggers/${triggerId}`),
};
```

### React Query Hooks
```javascript
// src/hooks/useWebhooks.js
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { webhooksApi, pollingTriggersApi } from '../api/webhooks';
import { useWorkspaceStore } from '../stores/workspaceStore';

export function useWebhooks() {
  const workspaceId = useWorkspaceStore((state) => state.currentWorkspace?.id);

  return useQuery({
    queryKey: ['webhooks', workspaceId],
    queryFn: () => webhooksApi.list(workspaceId),
    enabled: !!workspaceId,
  });
}

export function useWebhook(webhookId) {
  const workspaceId = useWorkspaceStore((state) => state.currentWorkspace?.id);

  return useQuery({
    queryKey: ['webhooks', workspaceId, webhookId],
    queryFn: () => webhooksApi.get(workspaceId, webhookId),
    enabled: !!workspaceId && !!webhookId,
    refetchInterval: 30000, // Refresh every 30 seconds to get latest request count
  });
}

export function useCreateWebhook() {
  const queryClient = useQueryClient();
  const workspaceId = useWorkspaceStore((state) => state.currentWorkspace?.id);

  return useMutation({
    mutationFn: ({ workflowId, data }) => 
      webhooksApi.create(workspaceId, workflowId, data),
    onSuccess: () => {
      queryClient.invalidateQueries(['webhooks', workspaceId]);
    },
  });
}

export function useUpdateWebhook() {
  const queryClient = useQueryClient();
  const workspaceId = useWorkspaceStore((state) => state.currentWorkspace?.id);

  return useMutation({
    mutationFn: ({ webhookId, data }) => 
      webhooksApi.update(workspaceId, webhookId, data),
    onSuccess: (_, { webhookId }) => {
      queryClient.invalidateQueries(['webhooks', workspaceId]);
      queryClient.invalidateQueries(['webhooks', workspaceId, webhookId]);
    },
  });
}

export function useDeleteWebhook() {
  const queryClient = useQueryClient();
  const workspaceId = useWorkspaceStore((state) => state.currentWorkspace?.id);

  return useMutation({
    mutationFn: (webhookId) => webhooksApi.delete(workspaceId, webhookId),
    onSuccess: () => {
      queryClient.invalidateQueries(['webhooks', workspaceId]);
    },
  });
}

export function usePollingTriggers() {
  const workspaceId = useWorkspaceStore((state) => state.currentWorkspace?.id);

  return useQuery({
    queryKey: ['polling-triggers', workspaceId],
    queryFn: () => pollingTriggersApi.list(workspaceId),
    enabled: !!workspaceId,
  });
}

export function useCreatePollingTrigger() {
  const queryClient = useQueryClient();
  const workspaceId = useWorkspaceStore((state) => state.currentWorkspace?.id);

  return useMutation({
    mutationFn: ({ workflowId, data }) => 
      pollingTriggersApi.create(workspaceId, workflowId, data),
    onSuccess: () => {
      queryClient.invalidateQueries(['polling-triggers', workspaceId]);
    },
  });
}
```

---

## 🎨 UI Components

### Webhooks List Page
```javascript
// src/pages/Webhooks.jsx
import { useWebhooks } from '../hooks/useWebhooks';
import { Copy, Check } from 'lucide-react';
import { useState } from 'react';

export default function WebhooksPage() {
  const { data: webhooks, isLoading } = useWebhooks();
  const [copiedId, setCopiedId] = useState(null);

  const copyToClipboard = (text, id) => {
    navigator.clipboard.writeText(text);
    setCopiedId(id);
    setTimeout(() => setCopiedId(null), 2000);
  };

  if (isLoading) return <div>Loading...</div>;

  return (
    <div className="p-6">
      <div className="mb-6">
        <h1 className="text-2xl font-bold">Webhooks</h1>
        <p className="text-gray-600 text-sm mt-1">
          HTTP endpoints that trigger workflows when called
        </p>
      </div>

      <div className="space-y-4">
        {webhooks?.data?.map((webhook) => (
          <div key={webhook.id} className="bg-white border rounded-lg p-4">
            <div className="flex justify-between items-start">
              <div className="flex-1">
                <h3 className="font-semibold">{webhook.workflow_name}</h3>
                <div className="mt-2 flex items-center gap-2">
                  <code className="text-sm bg-gray-100 px-2 py-1 rounded">
                    {webhook.url}
                  </code>
                  <button
                    onClick={() => copyToClipboard(webhook.url, webhook.id)}
                    className="text-gray-500 hover:text-gray-700"
                  >
                    {copiedId === webhook.id ? (
                      <Check className="w-4 h-4 text-green-600" />
                    ) : (
                      <Copy className="w-4 h-4" />
                    )}
                  </button>
                </div>
              </div>
              <div className="text-right">
                <span
                  className={`px-2 py-1 rounded text-xs ${
                    webhook.is_active
                      ? 'bg-green-100 text-green-700'
                      : 'bg-gray-100 text-gray-700'
                  }`}
                >
                  {webhook.is_active ? 'Active' : 'Inactive'}
                </span>
              </div>
            </div>

            <div className="mt-4 flex items-center gap-4 text-sm text-gray-600">
              <span>Method: <code className="font-mono">{webhook.method}</code></span>
              <span>Requests: {webhook.request_count}</span>
              {webhook.last_triggered_at && (
                <span>Last: {new Date(webhook.last_triggered_at).toLocaleString()}</span>
              )}
            </div>
          </div>
        ))}

        {webhooks?.data?.length === 0 && (
          <div className="text-center py-12 text-gray-500">
            No webhooks configured. Add a Webhook Trigger node to your workflow.
          </div>
        )}
      </div>
    </div>
  );
}
```

### Webhook Tester Component
```javascript
// src/components/WebhookTester.jsx
import { useState } from 'react';
import { useForm } from 'react-hook-form';
import axios from 'axios';

export default function WebhookTester({ webhookUrl }) {
  const [response, setResponse] = useState(null);
  const [isLoading, setIsLoading] = useState(false);
  const { register, handleSubmit } = useForm({
    defaultValues: {
      method: 'POST',
      headers: '{}',
      body: JSON.stringify({ test: true }, null, 2),
    },
  });

  const onSubmit = async (data) => {
    setIsLoading(true);
    setResponse(null);

    try {
      const headers = JSON.parse(data.headers || '{}');
      const body = data.body ? JSON.parse(data.body) : undefined;

      const result = await axios({
        method: data.method,
        url: webhookUrl,
        headers,
        data: body,
      });

      setResponse({
        status: result.status,
        data: result.data,
        success: true,
      });
    } catch (error) {
      setResponse({
        status: error.response?.status || 'Error',
        data: error.response?.data || error.message,
        success: false,
      });
    } finally {
      setIsLoading(false);
    }
  };

  return (
    <div className="border rounded-lg p-4">
      <h3 className="font-semibold mb-4">Test Webhook</h3>

      <form onSubmit={handleSubmit(onSubmit)} className="space-y-4">
        <div>
          <label className="block text-sm font-medium mb-1">Method</label>
          <select
            {...register('method')}
            className="w-full border rounded px-3 py-2"
          >
            <option value="GET">GET</option>
            <option value="POST">POST</option>
            <option value="PUT">PUT</option>
            <option value="PATCH">PATCH</option>
            <option value="DELETE">DELETE</option>
          </select>
        </div>

        <div>
          <label className="block text-sm font-medium mb-1">Headers (JSON)</label>
          <textarea
            {...register('headers')}
            className="w-full border rounded px-3 py-2 font-mono text-sm"
            rows={3}
            placeholder='{"Content-Type": "application/json"}'
          />
        </div>

        <div>
          <label className="block text-sm font-medium mb-1">Body (JSON)</label>
          <textarea
            {...register('body')}
            className="w-full border rounded px-3 py-2 font-mono text-sm"
            rows={6}
          />
        </div>

        <button
          type="submit"
          disabled={isLoading}
          className="w-full px-4 py-2 bg-blue-600 text-white rounded disabled:opacity-50"
        >
          {isLoading ? 'Sending...' : 'Send Request'}
        </button>
      </form>

      {response && (
        <div className="mt-4">
          <div className="flex items-center gap-2 mb-2">
            <span className="text-sm font-medium">Response:</span>
            <span
              className={`px-2 py-1 rounded text-xs ${
                response.success
                  ? 'bg-green-100 text-green-700'
                  : 'bg-red-100 text-red-700'
              }`}
            >
              {response.status}
            </span>
          </div>
          <pre className="bg-gray-100 p-3 rounded text-sm overflow-auto">
            {JSON.stringify(response.data, null, 2)}
          </pre>
        </div>
      )}
    </div>
  );
}
```

### Polling Trigger Config
```javascript
// src/components/PollingTriggerConfig.jsx
import { useForm } from 'react-hook-form';
import { useCreatePollingTrigger } from '../hooks/useWebhooks';

export default function PollingTriggerConfig({ workflowId, onSuccess }) {
  const createTrigger = useCreatePollingTrigger();
  const { register, handleSubmit } = useForm({
    defaultValues: {
      interval: 300, // 5 minutes
    },
  });

  const onSubmit = async (data) => {
    await createTrigger.mutateAsync({
      workflowId,
      data: {
        interval: parseInt(data.interval),
        config: {
          endpoint: data.endpoint,
          method: data.method || 'GET',
        },
      },
    });
    onSuccess?.();
  };

  return (
    <form onSubmit={handleSubmit(onSubmit)} className="space-y-4">
      <div>
        <label className="block text-sm font-medium mb-1">Poll Interval (seconds)</label>
        <select
          {...register('interval')}
          className="w-full border rounded px-3 py-2"
        >
          <option value="60">Every 1 minute</option>
          <option value="300">Every 5 minutes</option>
          <option value="600">Every 10 minutes</option>
          <option value="1800">Every 30 minutes</option>
          <option value="3600">Every 1 hour</option>
        </select>
      </div>

      <div>
        <label className="block text-sm font-medium mb-1">Endpoint URL</label>
        <input
          {...register('endpoint', { required: true })}
          className="w-full border rounded px-3 py-2"
          placeholder="https://api.example.com/new-items"
        />
      </div>

      <button
        type="submit"
        disabled={createTrigger.isPending}
        className="w-full px-4 py-2 bg-blue-600 text-white rounded"
      >
        {createTrigger.isPending ? 'Creating...' : 'Create Polling Trigger'}
      </button>
    </form>
  );
}
```

---

## 💡 Common Use Cases

### 1. Webhook URL in External Service
Copy webhook URL and paste it into:
- Stripe webhook settings
- GitHub webhook configuration
- Zapier outgoing webhooks
- Any service that sends HTTP callbacks

### 2. Secure Webhook with Bearer Token
```javascript
const updateWebhook = useUpdateWebhook();

const addAuthentication = async (webhookId) => {
  await updateWebhook.mutateAsync({
    webhookId,
    data: {
      authentication: {
        type: 'bearer',
        token: crypto.randomUUID(), // Generate secure token
      },
    },
  });
};
```

### 3. IP Whitelist for Webhooks
```javascript
const updateWebhook = useUpdateWebhook();

const restrictToIPs = async (webhookId, ips) => {
  await updateWebhook.mutateAsync({
    webhookId,
    data: {
      allowed_ips: ips, // ['192.168.1.0/24', '203.0.113.5']
    },
  });
};
```

---

## 🔒 Security Best Practices

1. **Use Authentication**: Always configure bearer tokens or API keys for webhooks
2. **IP Whitelisting**: Restrict webhook access to known IP ranges
3. **HTTPS Only**: Never use HTTP webhooks in production
4. **Validate Payloads**: Implement signature verification in workflow
5. **Rate Limiting**: Backend should enforce rate limits on webhook endpoints

---

## 🎯 Next Steps

- Read [Module 9: Templates Marketplace](./09-templates.md)
- Implement webhook request history viewer
- Add webhook analytics dashboard
