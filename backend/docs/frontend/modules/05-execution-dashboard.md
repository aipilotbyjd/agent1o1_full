# 📊 Module 5: Execution Dashboard

**Monitor workflow executions in real-time**

**APIs:** `/api/v1/workspaces/{workspace}/executions/*`

---

## 🔗 API Endpoints

### 1. List Executions
```http
GET /api/v1/workspaces/{workspace_id}/executions
Query Params:
  - workflow_id: filter by workflow
  - status: running|completed|failed
  - page: 1
  - per_page: 20

Response:
{
  "data": [
    {
      "id": "uuid",
      "workflow_id": "uuid",
      "workflow_name": "My Workflow",
      "status": "completed",
      "started_at": "2024-01-01T00:00:00Z",
      "completed_at": "2024-01-01T00:00:30Z",
      "duration_ms": 30000,
      "trigger": "manual"
    }
  ]
}
```

### 2. Get Execution Details
```http
GET /api/v1/workspaces/{workspace_id}/executions/{id}

Response:
{
  "data": {
    "id": "uuid",
    "status": "completed",
    "nodes": {
      "node-1": {
        "status": "completed",
        "output": {...},
        "duration_ms": 120
      }
    }
  }
}
```

### 3. Get Execution Logs
```http
GET /api/v1/workspaces/{workspace_id}/executions/{id}/logs

Response:
{
  "data": [
    {
      "timestamp": "2024-01-01T00:00:00Z",
      "level": "info",
      "message": "Node executed successfully",
      "node_id": "node-1"
    }
  ]
}
```

### 4. Cancel Execution
```http
POST /api/v1/workspaces/{workspace_id}/executions/{id}/cancel
```

### 5. Retry Execution
```http
POST /api/v1/workspaces/{workspace_id}/executions/{id}/retry
```

---

## 🎨 Components

### Execution List
```jsx
import { useQuery } from '@tanstack/react-query';
import { executionsApi } from '../api/executions';

export default function ExecutionDashboard() {
  const workspaceId = useWorkspaceStore((s) => s.currentWorkspace?.id);
  
  const { data, isLoading, refetch } = useQuery({
    queryKey: ['executions', workspaceId],
    queryFn: () => executionsApi.list(workspaceId),
    refetchInterval: 3000, // Refresh every 3 seconds
  });

  return (
    <div className="p-6">
      <h1 className="text-2xl font-bold mb-6">Executions</h1>
      
      <div className="grid grid-cols-4 gap-4 mb-6">
        <StatCard title="Running" count={data?.stats?.running || 0} color="blue" />
        <StatCard title="Completed" count={data?.stats?.completed || 0} color="green" />
        <StatCard title="Failed" count={data?.stats?.failed || 0} color="red" />
        <StatCard title="Total" count={data?.stats?.total || 0} color="gray" />
      </div>

      <div className="bg-white rounded-lg border">
        <table className="w-full">
          <thead className="bg-gray-50">
            <tr>
              <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Workflow</th>
              <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
              <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Started</th>
              <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Duration</th>
              <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Actions</th>
            </tr>
          </thead>
          <tbody className="divide-y">
            {data?.data?.map((execution) => (
              <ExecutionRow key={execution.id} execution={execution} />
            ))}
          </tbody>
        </table>
      </div>
    </div>
  );
}
```

### Execution Details
```jsx
import { useParams } from 'react-router-dom';
import { useQuery } from '@tanstack/react-query';

export default function ExecutionDetails() {
  const { executionId } = useParams();
  const workspaceId = useWorkspaceStore((s) => s.currentWorkspace?.id);
  
  const { data } = useQuery({
    queryKey: ['execution', executionId],
    queryFn: () => executionsApi.get(workspaceId, executionId),
    refetchInterval: (query) => {
      const status = query.state.data?.data?.status;
      return status === 'running' ? 1000 : false; // Poll if running
    },
  });

  return (
    <div className="p-6">
      <h1 className="text-2xl font-bold mb-6">Execution Details</h1>
      
      <div className="bg-white rounded-lg border p-6 mb-6">
        <div className="grid grid-cols-2 gap-4">
          <div>
            <div className="text-sm text-gray-500">Status</div>
            <div className="text-lg font-semibold">{data?.data?.status}</div>
          </div>
          <div>
            <div className="text-sm text-gray-500">Duration</div>
            <div className="text-lg font-semibold">{data?.data?.duration_ms}ms</div>
          </div>
        </div>
      </div>

      <div className="bg-white rounded-lg border">
        <div className="p-4 border-b">
          <h2 className="font-semibold">Node Execution Results</h2>
        </div>
        <div className="p-4 space-y-4">
          {Object.entries(data?.data?.nodes || {}).map(([nodeId, nodeData]) => (
            <NodeExecutionCard key={nodeId} nodeId={nodeId} data={nodeData} />
          ))}
        </div>
      </div>
    </div>
  );
}
```

---

## 📁 API Module

```javascript
// src/api/executions.js
import apiClient from './client';

export const executionsApi = {
  list: (workspaceId, params = {}) =>
    apiClient.get(`/workspaces/${workspaceId}/executions`, { params }).then(res => res.data),
  
  get: (workspaceId, executionId) =>
    apiClient.get(`/workspaces/${workspaceId}/executions/${executionId}`).then(res => res.data),
  
  logs: (workspaceId, executionId) =>
    apiClient.get(`/workspaces/${workspaceId}/executions/${executionId}/logs`).then(res => res.data),
  
  cancel: (workspaceId, executionId) =>
    apiClient.post(`/workspaces/${workspaceId}/executions/${executionId}/cancel`).then(res => res.data),
  
  retry: (workspaceId, executionId) =>
    apiClient.post(`/workspaces/${workspaceId}/executions/${executionId}/retry`).then(res => res.data),
};
```

---

## ✅ Features
- [ ] Execution list with real-time updates
- [ ] Execution details view
- [ ] Node-level results
- [ ] Execution logs viewer
- [ ] Cancel running execution
- [ ] Retry failed execution
- [ ] Filter by status
- [ ] Export execution data
