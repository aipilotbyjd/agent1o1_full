# 📊 Module 13: Analytics & Monitoring

**Activity logs, execution analytics, and operational monitoring**

**APIs:** `/api/v1/workspaces/{workspace}/activity-logs/*`, `/api/v1/workspaces/{workspace}/executions/stats`, `/api/v1/workspaces/{workspace}/log-streaming/*`, `/api/v1/workspaces/{workspace}/git-sync/*`  
**Components:** AnalyticsDashboard, ActivityLog, ExecutionStats, LogStreaming

---

## 🔗 API Endpoints

### Execution Analytics

#### 1. Get Execution Stats
```http
GET /api/v1/workspaces/{workspace}/executions/stats
Authorization: Bearer {token}

Query Parameters:
- period: 7d, 30d, 90d (default: 7d)
- workflow_id (optional): Filter by workflow

Response (200):
{
  "data": {
    "total_executions": 1542,
    "successful_executions": 1398,
    "failed_executions": 144,
    "success_rate": 90.7,
    "avg_duration_seconds": 12.5,
    "total_credits_used": 7710,
    "executions_by_day": [
      {
        "date": "2024-01-15",
        "total": 220,
        "success": 198,
        "failed": 22
      }
    ],
    "top_workflows": [
      {
        "workflow_id": "uuid",
        "workflow_name": "User Onboarding",
        "execution_count": 540,
        "success_rate": 95.2
      }
    ],
    "failure_reasons": [
      {
        "reason": "SMTP Connection Failed",
        "count": 45
      }
    ]
  }
}
```

#### 2. Compare Executions
```http
GET /api/v1/workspaces/{workspace}/executions/compare

Query Parameters:
- execution_ids: uuid1,uuid2 (comma-separated)

Response (200):
{
  "data": [
    {
      "execution_id": "uuid1",
      "duration_seconds": 10.5,
      "nodes_executed": 5,
      "credits_used": 10,
      "status": "success"
    },
    {
      "execution_id": "uuid2",
      "duration_seconds": 15.2,
      "nodes_executed": 5,
      "credits_used": 12,
      "status": "failed"
    }
  ]
}
```

### Activity Logs

#### 3. List Activity Logs
```http
GET /api/v1/workspaces/{workspace}/activity-logs

Query Parameters:
- user_id (optional): Filter by user
- action (optional): created, updated, deleted, executed
- resource (optional): workflow, credential, member
- page (optional)

Response (200):
{
  "data": [
    {
      "id": "uuid",
      "user": {
        "id": "uuid",
        "name": "John Doe",
        "avatar": "https://..."
      },
      "action": "created",
      "resource_type": "workflow",
      "resource_id": "uuid",
      "resource_name": "User Onboarding",
      "description": "Created workflow 'User Onboarding'",
      "metadata": {
        "ip_address": "192.168.1.1",
        "user_agent": "Mozilla/5.0..."
      },
      "created_at": "2024-01-15T10:30:00Z"
    }
  ],
  "meta": {
    "total": 1240,
    "current_page": 1
  }
}
```

#### 4. Get Activity Log Details
```http
GET /api/v1/workspaces/{workspace}/activity-logs/{id}

Response (200):
{
  "data": {
    "id": "uuid",
    "action": "updated",
    "resource_type": "workflow",
    "description": "Updated workflow configuration",
    "changes": {
      "before": {
        "is_active": false
      },
      "after": {
        "is_active": true
      }
    },
    "created_at": "2024-01-15T10:30:00Z"
  }
}
```

#### 5. Export Activity Logs
```http
GET /api/v1/workspaces/{workspace}/activity-logs/export

Query Parameters:
- format: csv, json
- start_date (optional)
- end_date (optional)

Response (200):
- File download (CSV or JSON)
```

### Log Streaming Configuration

#### 6. List Log Streaming Configs
```http
GET /api/v1/workspaces/{workspace}/log-streaming

Response (200):
{
  "data": [
    {
      "id": "uuid",
      "type": "datadog",
      "name": "Datadog Production",
      "config": {
        "api_key": "dd_***",
        "site": "datadoghq.com"
      },
      "is_active": true,
      "created_at": "2024-01-01T00:00:00Z"
    }
  ]
}
```

#### 7. Create Log Streaming Config
```http
POST /api/v1/workspaces/{workspace}/log-streaming
Content-Type: application/json

{
  "type": "datadog",
  "name": "Datadog Production",
  "config": {
    "api_key": "dd_1234567890abcdef",
    "site": "datadoghq.com"
  }
}

Supported Types:
- datadog
- splunk
- elasticsearch
- webhook

Response (201):
{
  "data": {
    "id": "uuid",
    "type": "datadog",
    "name": "Datadog Production"
  }
}
```

#### 8. Update Log Streaming Config
```http
PUT /api/v1/workspaces/{workspace}/log-streaming/{id}
Content-Type: application/json

{
  "is_active": false
}
```

#### 9. Delete Log Streaming Config
```http
DELETE /api/v1/workspaces/{workspace}/log-streaming/{id}

Response (204): No Content
```

### Git Sync

#### 10. Get Git Sync Status
```http
GET /api/v1/workspaces/{workspace}/git-sync/status

Response (200):
{
  "data": {
    "is_configured": true,
    "provider": "github",
    "repository": "org/repo",
    "branch": "main",
    "last_sync_at": "2024-01-15T10:00:00Z",
    "sync_status": "success",
    "pending_changes": 0
  }
}
```

#### 11. Export Workflows to Git
```http
POST /api/v1/workspaces/{workspace}/git-sync/export
Content-Type: application/json

{
  "workflow_ids": ["uuid1", "uuid2"],
  "commit_message": "Update workflows"
}

Response (200):
{
  "message": "Workflows exported to Git successfully",
  "commit_sha": "abc123"
}
```

#### 12. Import Workflows from Git
```http
POST /api/v1/workspaces/{workspace}/git-sync/import
Content-Type: application/json

{
  "branch": "main",
  "overwrite_existing": false
}

Response (200):
{
  "message": "Workflows imported successfully",
  "imported_count": 5
}
```

---

## 🗄️ State Management

### API Client
```javascript
// src/api/analytics.js
import apiClient from './client';

export const analyticsApi = {
  // Stats
  getExecutionStats: (workspaceId, params = {}) => 
    apiClient.get(`/workspaces/${workspaceId}/executions/stats`, { params }),
  
  compareExecutions: (workspaceId, executionIds) => 
    apiClient.get(`/workspaces/${workspaceId}/executions/compare`, {
      params: { execution_ids: executionIds.join(',') },
    }),
  
  // Activity Logs
  listActivityLogs: (workspaceId, params = {}) => 
    apiClient.get(`/workspaces/${workspaceId}/activity-logs`, { params }),
  
  getActivityLog: (workspaceId, logId) => 
    apiClient.get(`/workspaces/${workspaceId}/activity-logs/${logId}`),
  
  exportActivityLogs: (workspaceId, params = {}) => 
    apiClient.get(`/workspaces/${workspaceId}/activity-logs/export`, {
      params,
      responseType: 'blob',
    }),
  
  // Log Streaming
  listLogStreaming: (workspaceId) => 
    apiClient.get(`/workspaces/${workspaceId}/log-streaming`),
  
  createLogStreaming: (workspaceId, data) => 
    apiClient.post(`/workspaces/${workspaceId}/log-streaming`, data),
  
  updateLogStreaming: (workspaceId, configId, data) => 
    apiClient.put(`/workspaces/${workspaceId}/log-streaming/${configId}`, data),
  
  deleteLogStreaming: (workspaceId, configId) => 
    apiClient.delete(`/workspaces/${workspaceId}/log-streaming/${configId}`),
  
  // Git Sync
  getGitStatus: (workspaceId) => 
    apiClient.get(`/workspaces/${workspaceId}/git-sync/status`),
  
  exportToGit: (workspaceId, data) => 
    apiClient.post(`/workspaces/${workspaceId}/git-sync/export`, data),
  
  importFromGit: (workspaceId, data) => 
    apiClient.post(`/workspaces/${workspaceId}/git-sync/import`, data),
};
```

### React Query Hooks
```javascript
// src/hooks/useAnalytics.js
import { useQuery, useMutation } from '@tanstack/react-query';
import { analyticsApi } from '../api/analytics';
import { useWorkspaceStore } from '../stores/workspaceStore';

export function useExecutionStats(params = {}) {
  const workspaceId = useWorkspaceStore((state) => state.currentWorkspace?.id);

  return useQuery({
    queryKey: ['execution-stats', workspaceId, params],
    queryFn: () => analyticsApi.getExecutionStats(workspaceId, params),
    enabled: !!workspaceId,
  });
}

export function useActivityLogs(params = {}) {
  const workspaceId = useWorkspaceStore((state) => state.currentWorkspace?.id);

  return useQuery({
    queryKey: ['activity-logs', workspaceId, params],
    queryFn: () => analyticsApi.listActivityLogs(workspaceId, params),
    enabled: !!workspaceId,
  });
}

export function useExportActivityLogs() {
  const workspaceId = useWorkspaceStore((state) => state.currentWorkspace?.id);

  return useMutation({
    mutationFn: (params) => analyticsApi.exportActivityLogs(workspaceId, params),
  });
}

export function useGitStatus() {
  const workspaceId = useWorkspaceStore((state) => state.currentWorkspace?.id);

  return useQuery({
    queryKey: ['git-status', workspaceId],
    queryFn: () => analyticsApi.getGitStatus(workspaceId),
    enabled: !!workspaceId,
  });
}
```

---

## 🎨 UI Components

### Analytics Dashboard
```javascript
// src/pages/Analytics.jsx
import { useState } from 'react';
import { useExecutionStats } from '../hooks/useAnalytics';
import { LineChart, Line, BarChart, Bar, XAxis, YAxis, CartesianGrid, Tooltip, ResponsiveContainer } from 'recharts';
import { TrendingUp, TrendingDown, Activity, Clock } from 'lucide-react';

export default function Analytics() {
  const [period, setPeriod] = useState('7d');
  const { data: stats, isLoading } = useExecutionStats({ period });

  if (isLoading) return <div>Loading...</div>;

  return (
    <div className="p-6">
      <div className="flex justify-between items-center mb-6">
        <h1 className="text-2xl font-bold">Analytics</h1>
        <select
          value={period}
          onChange={(e) => setPeriod(e.target.value)}
          className="border rounded px-3 py-2"
        >
          <option value="7d">Last 7 days</option>
          <option value="30d">Last 30 days</option>
          <option value="90d">Last 90 days</option>
        </select>
      </div>

      {/* Summary Cards */}
      <div className="grid grid-cols-1 md:grid-cols-4 gap-4 mb-8">
        <div className="bg-white border rounded-lg p-6">
          <div className="flex items-center justify-between">
            <div>
              <p className="text-gray-600 text-sm">Total Executions</p>
              <h3 className="text-3xl font-bold mt-1">
                {stats?.data?.total_executions?.toLocaleString()}
              </h3>
            </div>
            <Activity className="w-10 h-10 text-blue-500" />
          </div>
        </div>

        <div className="bg-white border rounded-lg p-6">
          <div className="flex items-center justify-between">
            <div>
              <p className="text-gray-600 text-sm">Success Rate</p>
              <h3 className="text-3xl font-bold mt-1 text-green-600">
                {stats?.data?.success_rate}%
              </h3>
            </div>
            <TrendingUp className="w-10 h-10 text-green-500" />
          </div>
        </div>

        <div className="bg-white border rounded-lg p-6">
          <div className="flex items-center justify-between">
            <div>
              <p className="text-gray-600 text-sm">Failed</p>
              <h3 className="text-3xl font-bold mt-1 text-red-600">
                {stats?.data?.failed_executions}
              </h3>
            </div>
            <TrendingDown className="w-10 h-10 text-red-500" />
          </div>
        </div>

        <div className="bg-white border rounded-lg p-6">
          <div className="flex items-center justify-between">
            <div>
              <p className="text-gray-600 text-sm">Avg Duration</p>
              <h3 className="text-3xl font-bold mt-1">
                {stats?.data?.avg_duration_seconds}s
              </h3>
            </div>
            <Clock className="w-10 h-10 text-purple-500" />
          </div>
        </div>
      </div>

      {/* Executions Over Time */}
      <div className="bg-white border rounded-lg p-6 mb-6">
        <h2 className="text-lg font-semibold mb-4">Executions Over Time</h2>
        <ResponsiveContainer width="100%" height={300}>
          <LineChart data={stats?.data?.executions_by_day}>
            <CartesianGrid strokeDasharray="3 3" />
            <XAxis dataKey="date" />
            <YAxis />
            <Tooltip />
            <Line type="monotone" dataKey="success" stroke="#10b981" strokeWidth={2} />
            <Line type="monotone" dataKey="failed" stroke="#ef4444" strokeWidth={2} />
          </LineChart>
        </ResponsiveContainer>
      </div>

      {/* Top Workflows */}
      <div className="bg-white border rounded-lg p-6">
        <h2 className="text-lg font-semibold mb-4">Top Workflows</h2>
        <div className="space-y-3">
          {stats?.data?.top_workflows?.map((workflow) => (
            <div key={workflow.workflow_id} className="flex items-center justify-between">
              <div className="flex-1">
                <p className="font-medium">{workflow.workflow_name}</p>
                <div className="flex items-center gap-4 mt-1 text-sm text-gray-600">
                  <span>{workflow.execution_count} executions</span>
                  <span className="text-green-600">{workflow.success_rate}% success</span>
                </div>
              </div>
            </div>
          ))}
        </div>
      </div>
    </div>
  );
}
```

### Activity Log Component
```javascript
// src/components/ActivityLog.jsx
import { useActivityLogs } from '../hooks/useAnalytics';
import { formatDistanceToNow } from 'date-fns';
import { FileText, Users, Key, Play } from 'lucide-react';

const ICONS = {
  workflow: FileText,
  member: Users,
  credential: Key,
  execution: Play,
};

export default function ActivityLog() {
  const { data: logs, isLoading } = useActivityLogs();

  if (isLoading) return <div>Loading...</div>;

  return (
    <div className="bg-white rounded-lg shadow">
      <div className="p-4 border-b">
        <h2 className="font-semibold">Activity Log</h2>
      </div>
      <div className="divide-y">
        {logs?.data?.map((log) => {
          const Icon = ICONS[log.resource_type] || FileText;
          return (
            <div key={log.id} className="p-4 hover:bg-gray-50">
              <div className="flex gap-3">
                <div className="w-8 h-8 bg-gray-100 rounded-full overflow-hidden flex-shrink-0">
                  {log.user.avatar ? (
                    <img src={log.user.avatar} alt="" className="w-full h-full object-cover" />
                  ) : (
                    <div className="w-full h-full flex items-center justify-center bg-blue-500 text-white text-sm">
                      {log.user.name.charAt(0)}
                    </div>
                  )}
                </div>
                <div className="flex-1 min-w-0">
                  <p className="text-sm">
                    <span className="font-medium">{log.user.name}</span>
                    {' '}{log.description}
                  </p>
                  <p className="text-xs text-gray-500 mt-1">
                    {formatDistanceToNow(new Date(log.created_at), { addSuffix: true })}
                  </p>
                </div>
                <Icon className="w-5 h-5 text-gray-400 flex-shrink-0" />
              </div>
            </div>
          );
        })}
      </div>
    </div>
  );
}
```

---

## 💡 Common Use Cases

### 1. Filter Activity Logs
```javascript
const { data: workflowLogs } = useActivityLogs({
  resource: 'workflow',
  action: 'created',
});
```

### 2. Export Logs to CSV
```javascript
const exportLogs = useExportActivityLogs();

const handleExport = async () => {
  const blob = await exportLogs.mutateAsync({ format: 'csv' });
  const url = window.URL.createObjectURL(blob.data);
  const a = document.createElement('a');
  a.href = url;
  a.download = 'activity-logs.csv';
  a.click();
};
```

### 3. Compare Workflow Performance
```javascript
const compareWorkflows = async (workflow1Id, workflow2Id) => {
  const stats1 = await analyticsApi.getExecutionStats(workspaceId, {
    workflow_id: workflow1Id,
  });
  const stats2 = await analyticsApi.getExecutionStats(workspaceId, {
    workflow_id: workflow2Id,
  });
  
  // Compare success rates, durations, etc.
};
```

---

## 🎯 Next Steps

- Read [Module 14: AI Agents System](./14-agents.md)
- Implement real-time analytics with WebSockets
- Add custom dashboard builder
